<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\DebugController;

    class LdapService {
        public const ACCESSED_DEPARTMENTS = [
            'Управление Эксплуатации'                    => ['Подрядчики'],
            'Управление Технической Поддержки Абонентов' => ['Подрядчики'],
            'Управление Клиентского Обслуживания'        => ['Подрядчики'],
            'Администрация'                              => ['Подрядчики'],
            'Корпоративное управление'                   => ['Подрядчики'],
        ];

        public function __construct (
            protected LdapProvider $provider
        ) {
        }

        /**
         * Главный метод аутентификации через LDAP
         */
        public function authenticate (int $uid, string $username):array {
            // 1. Поиск в LDAP (AD или OpenLDAP)
            Console::info("LDAP <= $username");
            $ldapUser = $this->provider->findUser($username);
            Console::info("LDAP search: ".json_encode($ldapUser, JSON_UNESCAPED_UNICODE));

            if (!$ldapUser) {
                DebugController::write("Пользователь $username не найден в LDAP", 'LDAP_AUTH');
                return [
                    'success' => false,
                    'message' => "Пользователь $username не найден в LDAP"
                ];
            }

            // 2. Проверка доступа по департаменту и подразделению
            $dept = $ldapUser['department'];
            $sub  = $ldapUser['subdivision'];

            if (!$this->hasAccess($dept, $sub)) {
                DebugController::write("Доступ запрещен: $dept -> $sub", 'LDAP_AUTH');
                return ['success' => false, 'message' => "У вас нет прав доступа (Отдел: $dept)"];
            }

            // 3. Обновление записи в БД
            $user = UserLdap::where('uid', $uid)
                ->first();
            Console::info("LDAP => ".json_encode($user, JSON_UNESCAPED_UNICODE));

            $user->update([
                'username'       => $ldapUser['username'],
                'ldap_full_name' => $ldapUser['ldap_full_name'],
                'mobile'         => $ldapUser['mobile'], // Сохранится как JSON благодаря casts в модели
                'department'     => $dept,
                'subdivision'    => $sub,
                'authorized'     => true,
                'attempt'        => false,
                'last_logon'     => time()
            ]);

            DebugController::write($user->toArray(), 'LDAP_AUTH_SUCCESS');

            return [
                'success' => true,
                'user'    => $user,
            ];
        }

        /**
         * Проверка вхождения в ACCESSED_DEPARTMENTS
         */
        private function hasAccess (string $dept, string $sub):bool {
            if (!array_key_exists($dept, self::ACCESSED_DEPARTMENTS)) {
                return false;
            }

            return in_array($sub, self::ACCESSED_DEPARTMENTS[$dept]);
        }
    }
