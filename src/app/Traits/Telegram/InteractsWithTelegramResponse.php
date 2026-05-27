<?php

    namespace App\Traits\Telegram;

    use App\Models\Command;
    use App\Models\Log;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    trait InteractsWithTelegramResponse {
        protected int $messageId = 0;
        protected string $accumulatedText = '';

        /**
         * Начало выполнения: отправляет первое сообщение и запоминает ID
         */
        protected function startReply (int $uid, string $text):void {
            // $this->bot должен быть доступен в классе, использующем трейт
            $this->accumulatedText = $text."\n";
            $this->messageId = $this->bot->send($uid, $this->accumulatedText, []); // Пустой массив из аргументов НЕ УДАЛЯТЬ!!!!!
            Console::debug("START REPLY ID: ".$this->messageId);
        }

        /**
         * Дополнение сообщения (динамический апдейт)
         */
        protected function appendReply (int $uid, string $newText):void {
            $this->accumulatedText .= "\n$newText\n";
            $this->bot->update($uid, $this->messageId, $this->accumulatedText);
        }

        /**
         * Логирование действия в БД
         */
        protected function logAction (UserLdap $user, string $cmdName, array $params):void {
            // Проверяем флаг, если он объявлен в классе-родителе
            if (isset($this->needToStore) && !$this->needToStore) return;

            $command = Command::where('name', $cmdName)->first();
            $commandId = $command ? $command->id : 0;

            Log::create([
                'created_at'     => now(),
                'uid'            => $user->uid,
                'command_id'     => $commandId,
                'command_params' => join(' ', $params),
                'response'       => mb_substr($this->accumulatedText, 0, 1000),
            ]);
        }
    }
