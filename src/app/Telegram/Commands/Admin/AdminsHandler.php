<?php

    namespace App\Telegram\Commands\Admin;

    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class AdminsHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            if (!$user->is_admin)
                return;

            $admins = UserLdap::where('is_admin', true)
                ->get();

            foreach ($admins as $a) {
                $text = "👨‍💻 <b>{$a->ldap_full_name}</b>\n└ @{$a->tg_full_name}";
                $buttons = [
                    [
                        ['text' => "🚫 Снять права {$a->username}", 'callback_data' => "/admin_off {$a->username}"]
                    ]
                ];
                $this->bot->sendInline($user->uid, $text, $buttons);
            }
        }
    }
