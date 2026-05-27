<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\Transport;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Queue\Queueable;
    use Exception;

    class ExecuteTelegramCommand implements ShouldQueue {
        use Queueable;

        /**
         * Задача может выполняться долго (до 15 минут),
         * чтобы соответствовать таймауту OtkApiService (10 минут)
         */
        public int $timeout = 900;

        public function __construct (
            protected UserLdap $user, protected string $cmdName, protected string $handlerClass, protected array $params
        ) {
        }

        /**
         * @throws Exception
         */
        public function handle ():void {
            // Восстанавливаем request-контекст для корректного логирования в очереди
            request()->merge([
                'current_tg_uid' => $this->user->uid,
                'command_name'   => $this->cmdName,
            ]);

            Console::info("Запуск команды [$this->cmdName] из очереди для UID: {$this->user->uid}");

            try {
                // Разрешаем хендлер через контейнер (внедрятся Transport и OtkApiService)
                $handler = app($this->handlerClass);
                $handler->handle($this->user, $this->params);
            } catch (Exception $e) {
                Console::error("Ошибка выполнения команды [$this->cmdName] в очереди: ".$e->getMessage());

                // Оповещаем пользователя о фатальном сбое
                /** @var Transport $bot */
                $bot = app(Transport::class);
                $bot->send($this->user->uid, "⚠️ Произошла внутренняя ошибка при выполнении команды.");

                throw $e; // Пробрасываем дальше, чтобы Laravel пометил задачу как failed
            }
        }
    }
