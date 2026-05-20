<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use App\Services\Telegram\Console;
    use LdapRecord\Container;
    use LdapRecord\Models\ActiveDirectory\User;

    class ActiveDirectoryProvider implements LdapProvider {
        public function findUser (string $username):?array {
            try {
                // 1. Ищем пользователя по samaccountname (логину)
                // LdapRecord автоматически использует соединение 'default' из config/ldap.php
                $ldapUser = User::where('samaccountname', '=', $username)->first();

                if (!$ldapUser) {
                    Console::error("Пользователь $username не найден в Active Directory");
                    return null;
                }

                // 2. Проверяем, не заблокирована ли учетка (UserAccountControl)
                if ($ldapUser->isDisabled()) {
                    Console::warn("Учетная запись $username отключена в AD");
                    return null;
                }

                // 3. Извлекаем атрибуты (используем getFirstAttribute для строк)
                $displayName = $ldapUser->getFirstAttribute('cn') ?? $ldapUser->getFirstAttribute('displayname');
                $department  = $ldapUser->getFirstAttribute('department') ?? 'Не указан';
                $subdivision = $ldapUser->getFirstAttribute('company') ?? 'Не указано'; // Или 'title' / 'description' в зависимости от вашей схемы

                // Собираем телефоны в массив (mobile, telephonenumber, ipPhone)
                $phones = array_filter([
                    $ldapUser->getFirstAttribute('mobile'),
                    $ldapUser->getFirstAttribute('telephonenumber')
                ]);

                Console::info("Данные AD для $username успешно получены");

                return [
                    'username'       => (string) $ldapUser->getFirstAttribute('samaccountname'),
                    'ldap_full_name' => (string) $displayName,
                    'department'     => (string) $department,
                    'subdivision'    => (string) $subdivision,
                    'mobile'         => $phones
                ];

            } catch (\Exception $e) {
                Console::error("Ошибка связи с LDAP: " . $e->getMessage());
                return null;
            }
        }
    }
