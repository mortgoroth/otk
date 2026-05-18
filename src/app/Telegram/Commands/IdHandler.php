<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class IdHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $text = "Ваш ID: <code>{$user->uid}</code>\n";
//            $text .= "Ваш логин: <code>{$user->username}</code>";

            $this->bot->send($user->uid, $text);
            $this->logAction($user, 'id', $params, $text);
        }
    }
