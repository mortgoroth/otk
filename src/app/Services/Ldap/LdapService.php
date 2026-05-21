<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class LdapService {
        public const array ACCESSED_DEPARTMENTS = [
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
            $username = strtolower(trim($username));

            // 1. ПРОВЕРКА НА УГОН: Не привязан ли этот логин AD к ДРУГОМУ телеграм-аккаунту?
            $existingOwner = UserLdap::whereUsername($username)->first();

            if ($existingOwner && $existingOwner->uid !== $uid) {
                Console::error("Попытка угона! UID $uid пытался войти под логином $username (владелец UID: {$existingOwner->uid})");

                // Сбрасываем попытку входа для злоумышленника, чтобы не висел в awaiting_login
                UserLdap::where('uid', $uid)->update(['attempt' => false]);

                return [
                    'success' => false,
                    'message' => "Этот не ваш аккаунт! Обратитесь к администратору."
                ];
            }

            // 1. Поиск в LDAP (AD или OpenLDAP)
            Console::debug("LDAP <= $username");
            $ldapUser = $this->provider->findUser($username);
            Console::debug("LDAP search: ".json_encode($ldapUser, JSON_UNESCAPED_UNICODE));

            if (!$ldapUser) {
                Console::warn("LDAP_AUTH: Пользователь $username не найден в LDAP");
                return [
                    'success' => false,
                    'message' => "Пользователь $username не найден в LDAP"
                ];
            }

            // 2. Проверка доступа по департаменту и подразделению
            $dept = $ldapUser['department'];
            $sub  = $ldapUser['subdivision'];

            if (!$this->hasAccess($dept, $sub)) {
                Console::warn("Доступ запрещен: $dept -> $sub");
                return [
                    'success' => false,
                    'message' => "У вас нет прав доступа (Отдел: $dept)"
                ];
            }

            // 3. Обновление записи в БД
            $user = UserLdap::updateOrCreate(
                ['uid' => $uid], // Найти по UID
                [
                    'username'       => $ldapUser['username'],
                    'ldap_full_name' => $ldapUser['ldap_full_name'],
                    'mobile'         => $ldapUser['mobile'], // Сохранится как JSON благодаря casts в модели
                    'department'     => $dept,
                    'subdivision'    => $sub,
                    'authorized'     => true,
                    'attempt'        => false,
                    'last_logon'     => time(),
                    'created_at'     => time(),
                    'updated_at'     => time(),
                ]
            );
            Console::debug("LDAP => ".json_encode($user, JSON_UNESCAPED_UNICODE));

            return [
                'success' => true,
                'user'    => $user,
            ];
        }

        /**
         * Проверка вхождения в ACCESSED_DEPARTMENTS
         */
        private function hasAccess (string $dept, string $sub):bool {
            // 1. Проверяем, есть ли департамент в списке ключей
            return array_key_exists($dept, self::ACCESSED_DEPARTMENTS);
        }

        public static function getPermissions (string $dept, string $sub):mixed {
            // 1. Если подразделение явно указано в списке для этого департамента — возвращаем массив команд
            if (isset(self::ACCESSED_DEPARTMENTS[$dept][$sub])) {
                return self::ACCESSED_DEPARTMENTS[$dept][$sub];
            }

            // 2. Если подразделения в списке нет (п.2 твоих требований) — доступ ПОЛНЫЙ
            return true;
        }

    }
