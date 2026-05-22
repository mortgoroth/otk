<?php

    namespace App\Telegram\Commands\Admin;

    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class ManagersHandler extends BaseHandler {

        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            if (!$user->alert && !$user->is_admin)
                return;

            $managers = UserLdap::where('alert', true)
                ->get();

            if ($managers->isEmpty()) {
                $this->bot->send($user->uid, "Список менеджеров пуст.");
                return;
            }

            foreach ($managers as $m) {
                $text = "👤 <b>{$m->ldap_full_name}</b>\n└ @{$m->tg_full_name}";
                $buttons = [
                    [
                        ['text' => "🔕 Отключить уведомления {$m->username}", 'callback_data' => "/alert_off {$m->username}"]
                    ]
                ];
                $this->bot->sendInline($user->uid, $text, $buttons);
            }
        }
    }
