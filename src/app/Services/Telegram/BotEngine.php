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
                $name = $update['message']['from']['username'] ?? 'User';
            } elseif (isset($update['callback_query'])) {
                $uid = $update['callback_query']['message']['chat']['id'];
                $text = trim($update['callback_query']['data'] ?? '');
                $name = $update['callback_query']['from']['username'] ?? 'User';
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
                    $user = $res['user'];

                    // 2. Генерируем кнопки на основе прав
                    $keyboard = $this->renderKeyboard($user);

                    // 3. Отправляем сообщение с правильной клавиатурой
                    $this->bot->send($uid, "✅ Авторизация успешна", $keyboard);
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

                // 2. Обновляем статическую клавиатуру для текущего контекста
                // Это гарантирует, что Dispatcher и все вложенные хендлеры
                // будут использовать актуальные кнопки этого конкретного юзера.
                $this->renderKeyboard($user);

                // 3. Передаем управление диспетчеру
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

        public function renderKeyboard (UserLdap $user):array {
            $buttons = ['help', 'history', 'logout'];

            if ($user->alert || $user->is_admin) {
                $buttons[] = 'managers';
            }

            if ($user->is_admin) {
                $buttons[] = 'admins';
            }

            $keyboard = array_chunk($buttons, 3);

            // Сохраняем в транспорт, чтобы последующие ответы в этой итерации видели эти кнопки
            Transport::setStaticKeyboard($keyboard);

            return $keyboard;
        }
    }
