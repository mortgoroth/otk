<?php

    namespace App\Services\Auth;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class LdapAuthService {
        /**
         * Проверка текущего статуса пользователя ( guest | awaiting_login | authorized )
         */
        public function getStatus (int $uid):string {
            $user = UserLdap::find($uid);
            Console::debug("LdapAuthService::getStatus user search result => ".json_encode($user, JSON_UNESCAPED_UNICODE));

            // 1. Юзера нет в базе или он вообще не начинал вход
            if (!$user || (!$user->authorized && !$user->attempt)) {
                return 'guest';
            }

            // 2. Юзер авторизован — проверяем время сессии (24 часа)
            if ($user->authorized) {
                if (time() - $user->last_logon > 86400) {
                    $user->update([
                        'authorized' => false, 'attempt' => true // Сразу ставим в режим ввода логина после таймаута
                    ]);
                    return 'awaiting_login';
                }
                return 'authorized';
            }
            Console::debug("LdapAuthService::getStatus => ".json_encode($user, JSON_UNESCAPED_UNICODE));

            // 3. Юзер нажал кнопку login, но еще не прислал текст логина
            if ($user->attempt && !$user->authorized) {
                return 'awaiting_login';
            }

            return 'guest';
        }

        /**
         * Активация режима ввода логина (по кнопке "login")
         */
        public function initAttempt (int $uid, string $tgName):void {
            Console::debug("LdapAuthService::initAttempt => $uid :: $tgName");
            UserLdap::updateOrCreate(
                ['uid' => $uid],
                [
                    'tg_full_name'   => $tgName,
                    'username'       => $tgName,
                    'ldap_full_name' => $tgName,
                    'department'     => $tgName,
                    'subdivision'    => $tgName,
                    'mobile'         => '[]',
                    'attempt'        => true,
                    'authorized'     => false,
                    'updated_at'     => date('Y-m-d H:i:s', time()),
                ]);
        }
    }
