<?php

    namespace App\Services\Telegram;

    use App\Services\Auth\LdapAuthService;
    use App\Services\Ldap\LdapService;

    class BotEngine {
        public function __construct (
            protected LdapAuthService $auth, protected LdapService $ldap, protected Transport $bot, protected CommandDispatcher $dispatcher
        ) {
        }

        public function handle (array $update):void {
            set_time_limit(0); // Снимаем ограничение времени выполнения скрипта
            ini_set('default_socket_timeout', 600); // Таймаут для сокетов

            $uid = null;
            $text = '';
            $name = '';

            // 1. ПАРСИНГ: Обычное сообщение или нажатие кнопки?
            if (isset($update['message'])) {
                $uid = $update['message']['chat']['id'];
                $text = trim($update['message']['text'] ?? '');
                $name = $update['message']['from']['first_name'] ?? 'User';
            } elseif (isset($update['callback_query'])) {
                $uid = $update['callback_query']['message']['chat']['id'];
                // Берем данные из callback_data (там лежит текст команды из истории)
                $text = trim($update['callback_query']['data'] ?? '');
                $name = $update['callback_query']['from']['first_name'] ?? 'User';

                // Отправляем уведомление в Telegram, что "нажатие принято" (убирает часики с кнопки)
                $this->answerCallback($update['callback_query']['id']);
            }

            if (!$uid)
                return;

            // 2. ЛОГИКА АВТОРИЗАЦИИ (из требований)
            $status = $this->auth->getStatus($uid);

            if ($status === 'guest') {
                if (strtolower($text) === 'login') {
                    $this->auth->initAttempt($uid, $name);
                    $this->bot->send($uid, "Введите ваш AD логин:", [], true);
                } else {
                    $this->bot->send($uid, "Нажмите кнопку для входа:", [['login']]);
                }
                return;
            }

            if ($status === 'awaiting_login') {
                $res = $this->ldap->authenticate($uid, $text);
                if ($res['success']) {
                    $this->bot->send($uid, "Ок, вошли!", [['help', 'history', 'logout']]);
                } else {
                    $this->bot->send($uid, "Ошибка: ".$res['message'], [['login']]);
                }
                return;
            }

            // 3. ДИСПЕТЧЕРИЗАЦИЯ КОМАНД
            if ($status === 'authorized') {
                if (strtolower($text) === 'logout') {
                    \App\Models\UserLdap::where('uid', $uid)
                        ->update(['authorized' => false]);
                    $this->bot->send($uid, "Вы вышли.", [['login']]);
                    return;
                }

                // Отдаем команду в диспетчер
                $this->dispatcher->dispatch(\App\Models\UserLdap::find($uid), $text, $this);
            }
        }

        /**
         * Подтверждение нажатия кнопки (чтобы кнопка не "зависала")
         */
        private function answerCallback (string $callbackQueryId):void {
            $conf = config('telegram');
            \Illuminate\Support\Facades\Http::post(
                "{$conf['api_url']}/{$conf['otk_service_bot']['token']}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId]
            );
        }
    }
