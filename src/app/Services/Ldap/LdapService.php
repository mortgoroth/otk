<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Services\Telegram\DebugController;

    class LdapService {
        public const ACCESSED_DEPARTMENTS = [
            'Управление Эксплуатации' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
            'Управление Технической  Поддержки Абонентов' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
            'Управление Технической Поддержки Абонентов' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
            'Управление Клиентского Обслуживания' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
            'Администрация' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
            'Корпоративное управление' => [
                'Подрядчики' => ['порты','ports','help','sos','?','помощь'],
            ],
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
            // Если департамента нет в списке ограничений — доступ разрешен всем подразделениям
            if (!array_key_exists($dept, self::ACCESSED_DEPARTMENTS)) {
                return true;
            }

            // Если департамент есть, но конкретного подразделения нет в списке ограничений — доступ разрешен
            if (!array_key_exists($sub, self::ACCESSED_DEPARTMENTS[$dept])) {
                return true;
            }

            // Если и департамент, и подразделение в списке — доступ разрешен (авторизация проходит),
            // но команды будут фильтроваться уже в Dispatcher.
            return true;
        }
    }
