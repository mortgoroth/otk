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

    class ExecuteBlink implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public $timeout = 700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected int      $messageId,
        ) {}

        public function handle (OtkApiService $otk, Transport $bot):void {
            // 2. Начало выполнения
            $uid = $this->user->uid;

            $report = "🔍 Проверяю коммутатор <code>$this->switchName</code>...";
            // 3. Проверка типа коммутатора (поддерживает ли он blink)
            $check = $otk->request("/switch/$this->switchName/blink/check");

            if (($check['error']['id'] ?? -1) === 0 && ($check['result'] ?? false)) {
                $report .= "\n💡 Всё ок! Мигаю индикаторами...";
                $bot->update($uid, $this->messageId, $report);

                // 4. Выполнение команды (POST-запрос)
                $otk->request("/switch/$this->switchName/blink", [], true);

                $report .= "\n✅ Готово!";
                $bot->update($uid, $this->messageId, $report);
            } else {
                // Если API вернул ошибку или неподдерживаемый тип
                $errorMsg = $check['error']['msg'] ?? 'Неподдерживаемый тип коммутатора.';
                $bot->update($uid, $this->messageId, "report\n\n❌ $errorMsg");
            }
        }

    }
