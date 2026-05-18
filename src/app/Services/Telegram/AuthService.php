<?php

    namespace App\Services\Telegram;

    use App\Models\UserLdap;

    class AuthService {
        public function checkStatus (int $uid):string {
            $user = UserLdap::find($uid);

            if (!$user || (!$user->authorized && !$user->attempt)) {
                return 'guest'; // Нужна кнопка Login
            }

            if ($user->attempt && !$user->authorized) {
                return 'awaiting_login'; // Ждем текст логина
            }

            if ($user->last_logon && (time() - $user->last_logon > 86400)) {
                $user->update(['authorized' => false, 'attempt' => false]);
                return 'guest';
            }

            return 'authorized';
        }

        public function startAttempt (int $uid, string $name):void {
            UserLdap::updateOrCreate(['uid' => $uid], [
                'attempt'      => true,
                'authorized'   => false,
                'tg_full_name' => $name
            ]);
        }
    }
