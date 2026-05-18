<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;

    class PortChangeHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка номера договора
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Номер договора не задан");
                return;
            }

            $contract = $params[0];

            // 2. Начало процесса
            $this->startReply($user->uid, "🔄 Перенос абонента <code>$contract</code> в другой порт...");
            $this->appendReply($user->uid, "🔎 Ищу информацию об абоненте в БД...");

            // 3. Запрос к API
            $res = $this->otk->request("/tg/portchange/$contract", [
                'uid' => $user->uid, 'uname' => $user->username
            ]);

            // 4. Обработка результата
            $errorId = $res['error']['id'] ?? -1;

            if ($errorId === 0) {
                // Успешный перенос
                $this->appendReply($user->uid, "✅ ".($res['result_msg'] ?? 'Перенос выполнен успешно.'));
            } else {
                // Обработка ошибок
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Номер договора не задан или неверный',
                    2 => '❌ ОШИБКА! БД недоступна',
                    default => '❌ ОШИБКА! '.($res['error']['msg'] ?? 'Неизвестная ошибка')
                };
                $this->appendReply($user->uid, $errorMsg);
            }

            // 5. Логируем действие
            $this->logAction($user, 'portchange', $params);
        }
    }
