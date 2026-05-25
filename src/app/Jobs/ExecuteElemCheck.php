<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Exception;

    class ExecuteElemCheck implements ShouldQueue
    {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public $timeout = 700;

        public function __construct(
            protected UserLdap $user,
            protected string $element,
            protected int $messageId,
            protected int $sessionId
        ) {}

        public function handle (OtkApiService $otk, Transport $bot):void {
            $element = $this->element;
            $uid = $this->user->uid;
            $sid = $this->sessionId;
            $head = "в элементе $element:";

            $report = "🔍 <b>Результаты проверки $head</b>\n\n";

            try {
                $this->updateStatus($otk, $sid, 1);
                // --- 1. ПРОВЕРКА ДОСТУПНОСТИ ---
                $getAvail = $otk->request("/elem/$element/avail");

                if (($getAvail['error']['id'] ?? -1) !== 0) {
                    $bot->update($uid, $this->messageId, "❌ Элемент $element не найден.");
                    return;
                }

                $this->updateStatus($otk, $sid, 2);
                $avail = $getAvail['result']['avail'] ?? [];
                $unavail = $getAvail['result']['unavail'] ?? [];

                $unavailText = empty($unavail) ? 'отсутствуют.' : join("\n", $unavail);
                $report .= "<b>Недоступные коммутаторы:</b>\n$unavailText\n\n";

                $bot->update($uid, $this->messageId, $report."🔄 Запуск проверки STP...");

                // --- 2. ПРОВЕРКА STP ---
                $this->updateStatus($otk, $sid, 3, ['unavail' => $unavail, 'avail' => $avail]);
                $chSTP = $otk->request("/elem/$element/stp", ['avail' => $avail], true);

                if (($chSTP['error']['id'] ?? -1) === 0) {
                    $this->updateStatus($otk, $sid, 4);

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
                        $this->updateStatus($otk, $sid, 4, [
                            'stp' => [
                                'alternates' => $chSTP['result']['alternates'],
                                'verdict' => $chSTP['verdict']
                            ]
                        ]);
                    }

                    $report .= "<b>STP:</b>\n{$chSTP['verdict']}\n$dbg_text\n\n";
                }

                $bot->update($uid, $this->messageId, $report."⏱ Проверка магистралей (2-3 мин)...");

                // --- 3. ПРОВЕРКА ОШИБОК ---
                $this->updateStatus($otk, $sid, 5);
                $chErr = $otk->request("/elem/$element/errors", ['avail' => $avail], true);

                if (($chErr['error']['id'] ?? -1) === 0) {
                    $this->updateStatus($otk, $sid, 6, [
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
                    $bot->update($uid, $this->messageId, $report);

                    // Отдельный пуш о завершении
                    $bot->send($uid, "✅ <b>Проверка элемента $element завершена</b>");
                } else {
                    $bot->update($uid, $this->messageId, $report."❌ Ошибка при проверке магистралей.");
                }

            } catch (Exception $e) {
                $bot->update($uid, $this->messageId, $report."\n🚨 <b>Критическая ошибка Job:</b>\n".$e->getMessage());
            }
        }

        private function updateStatus (OtkApiService $otk, int $sessionId, int $status, array $additional = []):void {
            $params = array_merge(['status' => $status], $additional);
            $otk->request("/elem/logs/$sessionId/update", $params, true);
        }
    }
