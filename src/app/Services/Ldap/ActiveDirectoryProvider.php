<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use LdapRecord\Container;

    class ActiveDirectoryProvider implements LdapProvider {
        public function findUser (string $username):?array {
            // В продакшене тут будет вызов LdapRecord или native ldap_search
            // Возвращаем структуру, которую ожидает наш LdapService
            return [
                'username'    => $username,
                'ldap_full_name' => 'Имя из AD',
                'department' => 'Управление Эксплуатации',
                'subdivision' => 'Подрядчики',
                'mobile' => ['+79991112233']
            ];
        }
    }
