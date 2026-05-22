<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class IdHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            $text = "Ваш ID: <code>$user->uid</code>\n";

            $this->bot->send($user->uid, $text);
        }
    }
