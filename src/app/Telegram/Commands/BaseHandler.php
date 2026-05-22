<?php

    namespace App\Telegram\Commands;

    use App\Models\Command;
    use App\Models\UserLdap;
    use App\Models\Log;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\Transport;
    use App\Traits\Telegram\HasAlerts;
    use Exception;

    abstract class BaseHandler {

        use HasAlerts;

        protected int $messageId = 0;
        protected string $accumulatedText = '';
        public bool $needToStore = true;

        public function __construct (
            protected Transport $bot,
            protected OtkApiService $otk
        ) {}

        abstract public function handle (UserLdap $user, array $params):void;

        protected function dispatchAsync(UserLdap $user, string $cmd, array $params, string $initialText): void {
            // Отправляем первое сообщение и получаем ID
            $msgId = $this->bot->send($user->uid, $initialText);

            // Кидаем в очередь
            \App\Jobs\ExecuteLongCommand::dispatch($user, $cmd, $params, $msgId);
        }

        /**
         * Начало выполнения: отправляет первое сообщение и запоминает ID
         */
        protected function startReply (int $uid, string $text):void {
            $this->accumulatedText = $text."\n";
            $this->messageId = $this->bot->send($uid, $this->accumulatedText, []); // Пустой массив из аргументов НЕ УДАЛЯТЬ!!!!!
            Console::debug("START REPLY ID: " . $this->messageId);
        }

        /**
         * Дополнение сообщения (аналог __update)
         */
        protected function appendReply (int $uid, string $newText):void {
            $this->accumulatedText .= "\n$newText\n";
            $this->bot->update($uid, $this->messageId, $this->accumulatedText);
        }

        protected function logAction (UserLdap $user, string $cmdName, array $params):void {
            if (!$this->needToStore)
                return;

            $command = Command::where('name', $cmdName)->first();
            $commandId = $command ? $command->id : 0;

            Log::create([
                'created_at'     => now(),
                'uid'            => $user->uid,
                'command_id'     => $commandId,
                'command_params' => implode(' ', $params),
                'response'       => mb_substr($this->accumulatedText, 0, 1000),
            ]);
        }
    }
