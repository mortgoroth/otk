<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Models\Log;
    use App\Services\Telegram\DebugController;
    use App\Services\Telegram\Transport;
    use Exception;

    abstract class BaseHandler {
        protected int $messageId = 0;
        protected string $accumulatedText = '';
        public bool $needToStore = true;

        public function __construct (protected Transport $bot) {
        }

        abstract public function handle (UserLdap $user, array $params):void;

        protected function alert(string $message, UserLdap $user): void {
            try {
                // Выбираем из базы всех, кому нужны уведомления
                $recipients = UserLdap::where('alert', true)->get(['uid', 'username']);

                if ($recipients->isEmpty()) {
                    return;
                }

                foreach ($recipients as $recipient) {
                    try {
                        // Формируем сообщение
                        $text = "🔔 <b>{$user->username}</b>: $message";

                        $this->bot->send($recipient->uid, $text);
                    } catch (Exception $e) {
                        DebugController::write(
                            "Не удалось отправить алерт для {$recipient->username} (UID: {$recipient->uid}): " . $e->getMessage(),
                            'ALERT_SEND_ERROR'
                        );
                    }
                }
            } catch (Exception $e) {
                DebugController::write("Ошибка при получении списка админов для алертов: " . $e->getMessage());
            }
        }

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
            $this->messageId = $this->bot->send($uid, $this->accumulatedText);
        }

        /**
         * Дополнение сообщения (аналог __update)
         */
        protected function appendReply (int $uid, string $newText):void {
            $this->accumulatedText .= $newText."\n";
            $this->bot->update($uid, $this->messageId, $this->accumulatedText);
        }

        protected function logAction (UserLdap $user, string $cmdName, array $params):void {
            if (!$this->needToStore)
                return;

            Log::create([
                'created_at'     => now(),
                'uid' => $user->uid,
                'command_id' => $cmdName,
                'command_params' => implode(' ', $params),
                'response' => mb_substr($this->accumulatedText, 0, 1000), // Ограничение для БД
            ]);
        }
    }
