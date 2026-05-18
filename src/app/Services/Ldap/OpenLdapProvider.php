<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;

    class OpenLdapProvider implements LdapProvider {
        public function findUser (string $username):?array {
            // Здесь логика из старого метода OpenLDAP::query()
            // В Laravel 13 лучше использовать нативные функции ldap_*
            // или библиотеку LdapRecord с настроенной схемой OpenLDAP.

            /* Пример реализации:
            $connection = ldap_connect(config('ldap.host'));
            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);

            $bind = ldap_bind($connection, config('ldap.user'), config('ldap.pass'));
            $filter = "(uid=$username)"; // В OpenLDAP обычно uid вместо samaccountname
            $sr = ldap_search($connection, config('ldap.base_dn'), $filter);
            $entry = ldap_first_entry($connection, $sr);

            if (!$entry) return null;

            $attrs = ldap_get_attributes($connection, $entry);
            */

            // Возвращаем данные в едином формате для LdapService
            return [
                'username'    => $username,
                'ldap_full_name' => 'Имя из OpenLDAP', // например, $attrs['cn'][0]
                'department'  => 'Управление Эксплуатации', // берем из атрибутов
                'subdivision' => 'Подрядчики', 'mobile' => []
            ];
        }
    }
