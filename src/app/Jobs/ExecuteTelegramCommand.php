<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\Transport;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Exception;

    class ExecuteTelegramCommand implements ShouldQueue {
        // Полный комплект трейтов Laravel для работы с очередями и моделями
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public int $timeout = 900;

        public function __construct (
            protected UserLdap $user,       // Благодаря SerializesModels превратится в ID и не потащит за собой PDO
            protected string   $cmdName,
            protected string   $handlerClass, // Передаем ИМЯ класса (строку), а не сам объект!
            protected array    $params
        ) {
            // Динамическое распределение по очередям
            $this->queue = in_array($cmdName, ['config', 'blink']) ? 'long' : 'default';
        }

        /**
         * @throws Exception
         */
        public function handle ():void {
            // Восстанавливаем request-контекст для логгера Console
            request()->merge([
                'current_tg_uid' => $this->user->uid,
                'command_name'   => $this->cmdName,
            ]);

            Console::info("Запуск команды [$this->cmdName] из очереди для UID: {$this->user->uid}");

            try {
                // Разворачиваем хендлер прямо внутри воркера через контейнер
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
