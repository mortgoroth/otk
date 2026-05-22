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

            $this->updateStatus($sessionId, 1);

            ExecuteElemCheck::dispatch($user, $element, $this->messageId, (int)$sessionId);

            $this->logAction($user, 'elem', $params);

//----------------------------------
//            // 4. ПРОВЕРКА ДОСТУПНОСТИ
//            $getAvail = $this->otk->request("/elem/$element/avail");
//
//            if (($getAvail['error']['id'] ?? -1) !== 0) {
//                $this->appendReply($user->uid, "❌ Элемент $element не найден.");
//                return;
//            }
//
//            $this->updateStatus($sessionId, 2);
//            $avail = $getAvail['result']['avail'] ?? [];
//            $unavail = $getAvail['result']['unavail'] ?? [];
//
//            $unavailText = empty($unavail) ? 'отсутствуют.' : implode("\n", $unavail);
//            $this->appendReply($user->uid, "<b>Недоступные коммутаторы $head</b>\n$unavailText");
//
//            // 5. ПРОВЕРКА STP
//            $this->appendReply($user->uid, "🔄 Запуск проверки STP $head...");
//            $this->updateStatus($sessionId, 3, ['unavail' => $unavail, 'avail' => $avail]);
//
//            $chSTP = $this->otk->request("/elem/$element/stp", ['avail' => $avail], true);
//
//            if (($chSTP['error']['id'] ?? -1) !== 0) {
//                $this->appendReply($user->uid, "❌ Ошибка при проверке STP.");
//                return;
//            }
//
//            $this->updateStatus($sessionId, 4);
//
//            if (!empty($chSTP['skipped'])) {
//                $skippedText = "<b>Пропущенные (STP):</b>\n";
//                foreach ($chSTP['skipped'] as $swnm => $reason) {
//                    $skippedText .= " • $swnm: $reason\n";
//                }
//                $this->appendReply($user->uid, $skippedText);
//            }
//
//            $dbg_text = "";
//            if (!empty($chSTP['result']['alternates'])) {
//                $dbg_text = "<b>Найденные альтернативные порты:</b>\n".implode("\n", $chSTP['result']['alternates']);
//                $this->updateStatus($sessionId, 4, [
//                    'stp' => [
//                        'alternates' => $chSTP['result']['alternates'], 'verdict' => $chSTP['verdict']
//                    ]
//                ]);
//            }
//
//            $this->appendReply($user->uid, "<b>STP $head</b>\n{$chSTP['verdict']}\n$dbg_text");
//
//            // 6. ПРОВЕРКА ОШИБОК (Магистральные порты)
//            $this->updateStatus($sessionId, 5);
//            $this->appendReply($user->uid, "⏱ Проверка ошибок на магистралях (2-3 минуты)...");
//
//            $chErr = $this->otk->request("/elem/$element/errors", ['avail' => $avail], true);
//
//            if (($chErr['error']['id'] ?? -1) === 0) {
//                $this->updateStatus($sessionId, 6, [
//                    'errors' => $chErr['verdict'], 'skipped' => $chErr['skipped'] ?? []
//                ]);
//
//                if (!empty($chErr['skipped'])) {
//                    $skippedErrText = "<b>Пропущенные (Ошибки):</b>\n";
//                    foreach ($chErr['skipped'] as $swnm => $reason) {
//                        $skippedErrText .= " • $swnm: $reason\n";
//                    }
//                    $this->appendReply($user->uid, $skippedErrText);
//                }
//
//                $this->appendReply($user->uid, "<b>Ошибки $head</b>\n".implode("\n", $chErr['verdict']));
//                $this->appendReply($user->uid, "<b>Ошибки на А3 $head</b>\n".implode("\n", $chErr['verdict_a3'] ?? []));
//
//                // Финальная точка
//                $this->bot->send($user->uid, "✅ <b>Проверка элемента $element завершена</b>");
//            } else {
//                $this->appendReply($user->uid, "❌ Ошибка при проверке магистралей.");
//            }
//            $this->logAction($user,'elem', $params);

        }

        /**
         * Вспомогательный метод для обновления статуса проверки в API
         */
        private function updateStatus (int $sessionId, int $status, array $additional = []):void {
            $params = array_merge(['status' => $status], $additional);
            $this->otk->request("/elem/logs/$sessionId/update", $params, true);
        }
    }
