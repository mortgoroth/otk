<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Throwable;

    class ExecuteProbePing implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAlerts;

        // Время жизни задачи (должно быть меньше retry_after в config/queue.php)
        public $timeout = 1700;

        public function __construct(
            protected UserLdap $user,
            protected array    $hostData,
            protected int      $messageId,
            protected string   $startMessage
        ) {}

        /**
         * @throws Throwable
         */
        public function handle(OtkApiService $otk, Transport $bot): void {
            $uid   = $this->user->uid;
            foreach ($this->hostData as $datum) {
                $ip = $datum->ip_address;

                // Запрос к API для проверки пинга конкретного IP
                $isAvail = (bool) $otk->request("/a2/probe/$ip/ping");

                $statusIcon = $isAvail ? '🟢' : '🔴';
                $statusText = $isAvail ? 'доступен' : 'недоступен';

                $line = "$statusIcon <code>$datum->parent_host</code> / $datum->parent_port ($ip) -> $statusText";
                $bot->update($uid, $this->messageId, $line);
            }

        }

    }
