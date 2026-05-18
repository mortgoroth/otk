<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Models\Log;

    class HistoryHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $commands = Log::getLastCommands($user->uid);

            if (empty($commands)) {
                $this->bot->send($user->uid, "История пуста.");
                return;
            }

            // Формируем кнопки: каждая команда в отдельном ряду
            $buttons = [];
            foreach ($commands as $cmd) {
                $buttons[] = [['text' => $cmd, 'callback_data' => $cmd]];
            }

            $this->bot->sendInline($user->uid, "Последние 5 команд:", $buttons);
        }
    }
