<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use Exception;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Throwable;

    class ExecuteSrchShort implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAlerts;

        // Время жизни задачи (должно быть меньше retry_after в config/queue.php)
        public $timeout = 1700;

        public function __construct(
            protected UserLdap $user,
            protected array    $swlist,
            protected string   $location,
            protected int      $messageId,
            protected string   $startMessage
        ) {}

        /**
         * @throws Throwable
         */
        public function handle(OtkApiService $otk, Transport $bot): void {
            $uid   = $this->user->uid;

            try {
                foreach ($this->swlist as $data) {
                    $cleanSwnm = str_replace('A4-', '', $data['swnm']);
                    $bot->update($uid, $this->messageId, "📡 Опрашиваю <b>$cleanSwnm</b>...");

                    $srchPort = $otk->request(
                        '/tg/srchshort',
                        [
                            'swnm' => $cleanSwnm,
                            'swip' => $data['swip']
                        ],
                        true
                    );

                    if (($srchPort['error']['id'] ?? -1) === 0) {
                        $shorted = $srchPort['result']['shorted'];
                        $report = "🔸 <b>{$data['swnm']}:</b>\n";

                        if (is_array($shorted)) {
                            $report .= $this->formatShorts($shorted);
                        } else {
                            $report .= " — $shorted\n";
                        }
                        $bot->update($uid, $this->messageId, $report);
                    } else {
                        $err = $srchPort['error']['msg'] ?? 'Ошибка API';
                        $bot->update($uid, $this->messageId, "⚠️ <b>{$data['swnm']}:</b> $err");
                    }
                }
                $bot->update($uid, $this->messageId, "✅ Поиск коротышей на <code>$this->location</code> завершен.");

            } catch (Exception $e) {
                $bot->update($uid, $this->messageId, "❌ Произошла ошибка при опросе оборудования.");
            }
        }

        /**
         * Форматирование структуры КЗ (бывший $pars)
         */
        private function formatShorts (array $arrShorted):string {
            $str = "";
            foreach ($arrShorted as $port => $portData) {
                $str .= " 🔌 <b>Порт $port:</b>\n";
                if (is_array($portData)) {
                    foreach ($portData as $pair => $status) {
                        $str .= "  • $pair: <code>$status</code>\n";
                    }
                } else {
                    $str .= "  • $portData\n";
                }
            }
            return $str;
        }

    }
