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

    class ExecuteElemCheck implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public $timeout = 700; // С запасом под магистрали

        public function __construct(
            protected UserLdap $user,
            protected string $element,
            protected int $messageId,
            protected int $sessionId
        ) {}

        public function handle(OtkApiService $otk, Transport $bot): void {
            $element = $this->element;
            $uid = $this->user->uid;
            $sid = $this->sessionId;

            $report = "🔍 <b>Проверка элемента $element</b>\n";

            try {
                // --- ШАГ 1: ПЕРВЫЙ ТЯЖЕЛЫЙ ЗАПРОС ---
                $bot->update($uid, $this->messageId, $report . "📡 Поиск недоступных коммутаторов...");
                $otk->request("/elem/logs/$sid/update", ['status' => 1], true);

                $getAvail = $otk->request("/elem/$element/avail");

                if (($getAvail['error']['id'] ?? -1) !== 0) {
                    $bot->update($uid, $this->messageId, $report . "❌ Элемент $element не найден в базе.");
                    return;
                }

                $avail = $getAvail['result']['avail'] ?? [];
                $unavail = $getAvail['result']['unavail'] ?? [];
                $otk->request("/elem/logs/$sid/update", ['status' => 2, 'unavail' => $unavail, 'avail' => $avail], true);

                $report .= "• Доступность: " . (empty($unavail) ? "✅" : "⚠️ " . count($unavail) . " offline") . "\n";
                $bot->update($uid, $this->messageId, $report . "🔄 Запуск проверки STP...");

                // --- ШАГ 2: STP ---
                $otk->request("/elem/logs/$sid/update", ['status' => 3], true);
                $chSTP = $otk->request("/elem/$element/stp", ['avail' => $avail], true);

                if (($chSTP['error']['id'] ?? -1) === 0) {
                    $otk->request("/elem/logs/$sid/update", [
                        'status' => 4,
                        'stp' => ['alternates' => $chSTP['result']['alternates'] ?? [], 'verdict' => $chSTP['verdict'] ?? '']
                    ], true);

                    $report .= "• STP: " . ($chSTP['verdict'] ?? "OK") . "\n";
                }
                $bot->update($uid, $this->messageId, $report . "⏱ Магистрали (2-3 минуты)...");

                // --- ШАГ 3: ОШИБКИ (САМЫЙ ДОЛГИЙ) ---
                $otk->request("/elem/logs/$sid/update", ['status' => 5], true);
                $chErr = $otk->request("/elem/$element/errors", ['avail' => $avail], true);

                if (($chErr['error']['id'] ?? -1) === 0) {
                    $otk->request("/elem/logs/$sid/update", [
                        'status' => 6,
                        'errors' => $chErr['verdict'],
                        'skipped' => $chErr['skipped'] ?? []
                    ], true);

                    $report .= "• Ошибки: " . (empty($chErr['verdict']) ? "✅" : "❌") . "\n\n";
                    $report .= "<b>Результат:</b>\n" . implode("\n", $chErr['verdict']);

                    $bot->update($uid, $this->messageId, $report);
                    $bot->send($uid, "✅ Проверка элемента $element завершена.");
                }

            } catch (Exception $e) {
                $bot->update($uid, $this->messageId, $report . "🚨 Ошибка воркера: " . $e->getMessage());
            }
        }
    }
