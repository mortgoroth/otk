<?php

    namespace App\Services\Telegram;

    use App\Models\UserLdap;
    use App\Services\Auth\LdapAuthService;
    use App\Services\Ldap\LdapService;
    use Illuminate\Support\Facades\Http;

    class BotEngine {
        public function __construct (
            protected LdapAuthService $auth,
            protected LdapService $ldap,
            protected Transport $bot,
            protected CommandDispatcher $dispatcher
        ) {
        }

        public function getBot(): Transport {
            return $this->bot;
        }

        public function handle (array $update):void {
            set_time_limit(0);
            ini_set('default_socket_timeout', 600);

            $uid = null;
            $text = '';
            $name = '';

            // 1. ПАРСИНГ
            if (isset($update['message'])) {
                $uid = $update['message']['chat']['id'];
                $text = trim($update['message']['text'] ?? '');
                $name = $update['message']['from']['first_name'] ?? 'User';
            } elseif (isset($update['callback_query'])) {
                $uid = $update['callback_query']['message']['chat']['id'];
                $text = trim($update['callback_query']['data'] ?? '');
                $name = $update['callback_query']['from']['first_name'] ?? 'User';
                $this->answerCallback($update['callback_query']['id']);
            }

            if (!$uid) return;

            request()->merge(['current_tg_uid' => $uid]); // для логгирования через дебаг

            // 2. ЛОГИКА АВТОРИЗАЦИИ
            $status = $this->auth->getStatus($uid);

            // 2. Обработка нажатия кнопки LOGIN (инициация)
            if (strtolower($text) === 'login') {
                $this->auth->initAttempt($uid, $name);
                $this->bot->send($uid, "Введите ваш AD логин:", [], true);
                return;
            }
            // 3. Обработка ВВОДА ЛОГИНА (состояние гостя или ожидания)
            if ($status === 'guest' || $status === 'awaiting_login') {
                // Если юзер прислал текст, но НЕ нажимал login — проверяем его в LDAP
                // Это и есть "мгновенная" регистрация для новых
                Console::info("Auth process => $name::$uid::$text");

                $res = $this->ldap->authenticate($uid, $text, $name);

                if ($res['success']) {
                    // Теперь он СРАЗУ авторизован
                    $this->bot->send($uid, "✅ Авторизация успешна. Доступ открыт.", [['help', 'history', 'logout']]);
                } else {
                    // Если не нашли или ошибка — возвращаем кнопку
                    $this->bot->send($uid, "❌ " . $res['message'], [['login']]);
                }
                return;
            }

            // --- СОСТОЯНИЕ: АВТОРИЗОВАН ---
            if ($status === 'authorized') {
                // 1. Извлекаем объект пользователя, чтобы проверить его флаги alert и is_admin
                $user = UserLdap::whereUid($uid)->first();

                // 2. Логика выхода
                if (strtolower($text) === 'logout') {
                    $user->update(['authorized' => false, 'attempt' => false]);
                    $this->bot->send($uid, "Вы вышли из системы.", [['login']]);
                    return;
                }

                // 3. Формируем динамическое меню кнопок
                $keyboardRow = ['help', 'history', 'logout'];

                if ($user->alert) {
                    $keyboardRow[] = 'managers';
                }

                if ($user->is_admin) {
                    // Если вдруг alert=false, но он админ — добавляем managers тоже
                    if (!in_array('managers', $keyboardRow)) {
                        $keyboardRow[] = 'managers';
                    }
                    $keyboardRow[] = 'admins';
                }

                Transport::setStaticKeyboard(array_chunk($keyboardRow, 3));
                // Теперь любой $this->bot->send() внутри любого хендлера
                // автоматически приклеит эти кнопки к сообщению.
                $this->dispatcher->dispatch($user, $text, $this);
            }
        }

        /**
         * Подтверждение нажатия кнопки (чтобы кнопка не "зависала")
         */
        private function answerCallback (string $callbackQueryId):void {
            $conf = config('telegram');
            Http::post(
                "{$conf['api_url']}/bot{$conf['otk_service_bot']['token']}/answerCallbackQuery",
                ['callback_query_id' => $callbackQueryId]
            );
        }
    }
