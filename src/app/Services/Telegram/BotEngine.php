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
            set_time_limit(0); // Снимаем ограничение времени выполнения скрипта
            ini_set('default_socket_timeout', 600); // Таймаут для сокетов

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


            // 2. ЛОГИКА АВТОРИЗАЦИИ
            $status = $this->auth->getStatus($uid);

            // --- СОСТОЯНИЕ: ГОСТЬ ---
            if ($status === 'guest') {
                Console::info("guest => $name::$uid::$text");
                if (strtolower($text) === 'login') {
                    $this->auth->initAttempt($uid, $name);
                    $this->bot->send($uid, "Ок! Введите ваш логин AD (например: hohlovav):", [], true);
                } else {
                    // 1. Отправляем инлайновую кнопку
                    // 2. Флагом true удаляем любую старую нижнюю клавиатуру
                    $buttons = [[['text' => '🔐 Авторизоваться (LOGIN)', 'callback_data' => 'login']]];
                    $this->bot->sendInline($uid, "⚠️ <b>Доступ ограничен.</b>\nДля работы с ботом необходимо войти в систему:", $buttons);

                    // Посылаем пустую команду удаления клавиатуры, чтобы очистить низ экрана
                    $this->bot->send($uid, "Используйте кнопку выше ⬆️", [], true);
                }
                return;
            }

            // --- СОСТОЯНИЕ: ОЖИДАНИЕ ЛОГИНА ---
            if ($status === 'awaiting_login') {
                Console::info("awaiting_login => $name::$uid::$text");
                $res = $this->ldap->authenticate($uid, $text);
                if ($res['success']) {
                    $this->bot->send($uid, "Авторизация успешна! Добро пожаловать.", [['help', 'history', 'logout']]);
                } else {
                    // Если логин неверный — НЕ даем кнопку login обратно,
                    // а просим ввести логин еще раз, оставляя поле ввода открытым
                    $this->bot->send($uid, "❌ Ошибка: " . $res['message'] . "\nПопробуйте ввести логин еще раз:", [], true);
                }
                return;
            }

// --- СОСТОЯНИЕ: АВТОРИЗОВАН ---
            if ($status === 'authorized') {
                Console::info("authorized => $name::$uid::$text");
                if (strtolower($text) === 'logout') {
                    UserLdap::where('uid', $uid)
                        ->update([
                            'authorized' => false,
                            'attempt' => false
                        ]);
                    $this->bot->send($uid, "Вы вышли из системы.", [['login']]);
                    return;
                }

                // Отдаем команду в диспетчер
                $this->dispatcher->dispatch(UserLdap::find($uid), $text, $this);
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
