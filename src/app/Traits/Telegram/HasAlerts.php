<?php

    namespace App\Traits\Telegram;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\Transport;
    use Exception;

    trait HasAlerts {
        protected function alert(string $message, UserLdap $user): void {
            try {
                $recipients = UserLdap::whereAlert(true)->get(['uid', 'username']);
                if ($recipients->isEmpty()) return;

                // В Job может не быть свойства $this->bot, берем из контейнера
                $bot = app(Transport::class);

                foreach ($recipients as $recipient) {
                    try {
                        $text = "🔔 <b>$user->username</b>: $message";
                        $bot->send($recipient->uid, $text);
                    } catch (Exception $e) {
                        Console::error("Не удалось отправить алерт для $recipient->username: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                Console::error("Ошибка алертов: " . $e->getMessage());
            }
        }
    }
