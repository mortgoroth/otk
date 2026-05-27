<?php

    namespace App\Telegram\Commands\Long;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Console;
    use App\Telegram\Commands\BaseHandler;
    use Exception;

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


            $report = "🔍 <b>Результаты проверки $head</b>\n\n";

            try {
                $this->updateStatus($this->otk, (int) $sessionId, 1);
                // --- 1. ПРОВЕРКА ДОСТУПНОСТИ ---
                $getAvail = $this->otk->request("/elem/$element/avail");

                if (($getAvail['error']['id'] ?? -1) !== 0) {
                    $this->appendReply($user->uid, "❌ Элемент $element не найден.");
                    return;
                }

                $this->updateStatus($this->otk, (int) $sessionId, 2);
                $avail = $getAvail['result']['avail'] ?? [];
                $unavail = $getAvail['result']['unavail'] ?? [];

                $unavailText = empty($unavail) ? 'отсутствуют.' : join("\n", $unavail);
                $report .= "<b>Недоступные коммутаторы:</b>\n$unavailText\n\n";

                $this->appendReply($user->uid, $report."🔄 Запуск проверки STP...");

                // --- 2. ПРОВЕРКА STP ---
                $this->updateStatus($this->otk, (int) $sessionId, 3, ['unavail' => $unavail, 'avail' => $avail]);
                $chSTP = $this->otk->request("/elem/$element/stp", ['avail' => $avail], true);

                if (($chSTP['error']['id'] ?? -1) === 0) {
                    $this->updateStatus($this->otk, (int) $sessionId, 4);

                    // Пропущенные (STP)
                    if (!empty($chSTP['skipped'])) {
                        $report .= "<b>Пропущенные (STP):</b>\n";
                        foreach ($chSTP['skipped'] as $swnm => $reason) {
                            $report .= " • $swnm: $reason\n";
                        }
                        $report .= "\n";
                    }

                    // Альтернативные порты
                    $dbg_text = "";
                    if (!empty($chSTP['result']['alternates'])) {
                        $dbg_text = "\n<b>Найденные альтернативные порты:</b>\n".join("\n", $chSTP['result']['alternates']);
                        $this->updateStatus($this->otk, (int) $sessionId, 4, [
                            'stp' => [
                                'alternates' => $chSTP['result']['alternates'],
                                'verdict' => $chSTP['verdict']
                            ]
                        ]);
                    }

                    $report .= "<b>STP:</b>\n{$chSTP['verdict']}\n$dbg_text\n\n";
                }

                $this->appendReply($user->uid, $report."⏱ Проверка магистралей (2-3 мин)...");

                // --- 3. ПРОВЕРКА ОШИБОК ---
                $this->updateStatus($this->otk, (int) $sessionId, 5);
                $chErr = $this->otk->request("/elem/$element/errors", ['avail' => $avail], true);

                if (($chErr['error']['id'] ?? -1) === 0) {
                    $this->updateStatus($this->otk, (int) $sessionId, 6, [
                        'errors' => $chErr['verdict'],
                        'skipped' => $chErr['skipped'] ?? []
                    ]);

                    // Пропущенные (Ошибки)
                    if (!empty($chErr['skipped'])) {
                        $report .= "<b>Пропущенные (Ошибки):</b>\n";
                        foreach ($chErr['skipped'] as $swnm => $reason) {
                            $report .= " • $swnm: $reason\n";
                        }
                        $report .= "\n";
                    }

                    $report .= "<b>Ошибки:</b>\n".join("\n", $chErr['verdict'])."\n\n";
                    $report .= "<b>Ошибки на А3:</b>\n".join("\n", $chErr['verdict_a3'] ?? [])."\n";

                    // Финальное обновление основного сообщения
                    $this->appendReply($user->uid, $report);

                    // Отдельный пуш о завершении
                    $this->appendReply($user->uid, "✅ <b>Проверка элемента $element завершена</b>");
                } else {
                    $this->appendReply($user->uid, $report."❌ Ошибка при проверке магистралей.");
                }

            } catch (Exception $e) {
                $this->appendReply($user->uid, $report."\n🚨 <b>Критическая ошибка Job:</b>\n".$e->getMessage());
            }

            $this->logAction($user, 'elem', $params);
        }

        private function updateStatus (OtkApiService $otk, int $sessionId, int $status, array $additional = []):void {
            $params = array_merge(['status' => $status], $additional);
            $otk->request("/elem/logs/$sessionId/update", $params, true);
        }

    }
