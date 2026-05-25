<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Telegram\Transport;
    use App\Services\Otk\OtkApiService;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;

    class ExecuteLongCommand implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        // Задача может висеть 10+ минут
        public $timeout = 700;

        public function __construct (
            protected UserLdap $user,
            protected string $commandName,
            protected array $params,
            protected int $messageId // ID того самого сообщения "⏳ Работаю..."
        ) {
        }

        public function handle (OtkApiService $otk, Transport $bot):void {
            // 1. Делаем тяжелый запрос (он заблокирует только этот процесс воркера)
            $data = $otk->request("/{$this->commandName}/do", $this->params, true);

            // 2. Формируем ответ
            $result = $data['success'] ? "✅ Готово!\n".$data['report'] : "❌ Ошибка: ".$data['message'];

            // 3. Редактируем исходное сообщение через транспорт
            $bot->update($this->user->uid, $this->messageId, $result);

            // 4. Логируем финал
            \App\Models\Log::create([
                'uid'            => $this->user->uid, 'command_id' => $this->commandName,
                'command_params' => join(' ', $this->params), 'response' => mb_substr($result, 0, 1000),
                'created_at'     => now()
            ]);
        }
    }
