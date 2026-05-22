<?php

    namespace App\Telegram\Commands;

    use App\Jobs\ExecuteElemCheck;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class ElemHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка номера элемента
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Номер элемента не задан.");
                return;
            }

            $element = $params[0];
            $head = "в элементе $element:";

            $this->startReply($user->uid, "🔍 Поиск недоступных коммутаторов $head...");

            // 3. Создаем запись в логе API и получаем ID созданной записи
            $initData = [
                'ts'      => time(),
                'uname'   => $user->username,
                'elem'    => $element,
                'status'  => 0,
            ];

            $logSession = $this->otk->request('/elem/logs', $initData, true);
            Console::debug("LOGSESSION: ".json_encode($logSession, JSON_UNESCAPED_UNICODE));
            $sessionId = current($logSession) ?? null;

            if (!$sessionId) {
                $this->appendReply($user->uid, "❌ Ошибка: не удалось создать сессию проверки.");
                return;
            }


            ExecuteElemCheck::dispatch($user, $element, $this->messageId, (int)$sessionId);

            $this->logAction($user, 'elem', $params);
        }

    }
