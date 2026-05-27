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

    class ExecuteClrps implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAlerts;

        // Время жизни задачи (должно быть меньше retry_after в config/queue.php)
        public $timeout = 1700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected array    $range,
            protected int      $messageId,
            protected string   $startMessage
        ) {}

        /**
         * @throws Throwable
         */
        public function handle(OtkApiService $otk, Transport $bot): void {
            $uid   = $this->user->uid;

            // 4. Цикл по портам
            foreach ($this->range as $port) {
                $bot->update($uid, $this->messageId, "🔍 Чищу порт <code>$port</code>...");

                // POST запрос к API
                $res = $otk->request("/tg/$this->switchName/clrps/$port", [], true);

                $errorId = $res['error']['id'] ?? -1;

                $status = match ($errorId) {
                    0 => "✅ <code>$this->switchName/$port</code>: Ok",
                    3 => "⚠️ Коммутатор недоступен",
                    50 => "❌ Ошибка. Port-security порта $port не очищен",
                    1 => "❌ Некорректное имя коммутатора",
                    2 => "❌ Коммутатор не найден",
                    4 => "❌ Некорректный порт",
                    default => "❌ Ошибка API (ID: $errorId)"
                };

                $bot->update($uid, $this->messageId,  " — $status");

                // Если фатальная ошибка коммутатора — прекращаем цикл
                if (in_array($errorId, [1, 2, 3])) {
                    break;
                }
            }
        }

    }
