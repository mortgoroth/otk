<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Models\Log;
    use App\Services\Telegram\Console;

    class HistoryHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            $commands = Log::getLastCommands($user->uid);

            if (empty($commands)) {
                Console::warn("History is empty for user $user->username");
                $this->bot->send($user->uid, "📜 Ваша история команд пока пуста.");
                return;
            }

            $buttons = [];
            foreach ($commands as $cmdText) {
                // Важно: в кнопке должен быть массив [кнопка] для создания ряда
                $buttons[] = [
                    ['text' => $cmdText, 'callback_data' => $cmdText]
                ];
            }

            Console::info("Sending history inline keyboard to $user->username");

            $this->bot->sendInline(
                $user->uid,
                "📋 <b>Последние 5 команд:</b>",
                $buttons
            );
        }
    }
