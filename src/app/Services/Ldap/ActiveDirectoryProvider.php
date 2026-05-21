<?php

    namespace App\Services\Ldap;

    use App\Contracts\LdapProvider;
    use App\Services\Telegram\Console;
    use Exception;
    use LdapRecord\Container;
    use LdapRecord\Models\ActiveDirectory\User;

    class ActiveDirectoryProvider implements LdapProvider {
        public function findUser (string $username):?array {
            try {
                $query = User::query()
                    ->where('objectCategory', '=', 'person')
                    // Убираем диспетчеров (whereNotContains 'samaccountname')
                    ->whereNotContains('samaccountname', 'disp_')
                    ->whereNotContains('samaccountname', 'disp-')
                    // Ищем конкретного юзера
                    ->where('samaccountname', '=', $username);

                $ldapUser = $query->first();

                if (!$ldapUser || $ldapUser->isDisabled()) {
                    Console::warn("Пользователь $username не найден или отключен в AD");
                    return null;
                }

                $dnParts = explode(',', $ldapUser->getDn());
                $subdivision = isset($dnParts[1])
                    ? str_replace('OU=', '', $dnParts[1])
                    : '';

                $rawMobile = $ldapUser->getFirstAttribute('mobile');
                $mobile = $rawMobile ? $this->parsePhoneNumber($rawMobile) : [];

                Console::info("AD данные для $username успешно обработаны");

                return [
                    'username'       => (string) $ldapUser->getFirstAttribute('samaccountname'),
                    'ldap_full_name' => (string) ($ldapUser->getFirstAttribute('cn') ?? $ldapUser->getName()),
                    'department'     => (string) ($ldapUser->getFirstAttribute('department') ?? ''),
                    'subdivision'    => (string) $subdivision,
                    'mobile'         => $mobile
                ];

            } catch (Exception $e) {
                Console::error("Ошибка связи с LDAP: " . $e->getMessage());
                return null;
            }
        }

        private function parsePhoneNumber(string $phone): array {
            // Убираем всё кроме цифр
            $clean = preg_replace('/[^0-9]/', '', $phone);

            if (empty($clean)) return [];

            // Если начинается с 8 или 7 и длина 11 цифр — приводим к стандарту +7
            if (strlen($clean) === 11 && ($clean[0] === '7' || $clean[0] === '8')) {
                return ['+7' . substr($clean, 1)];
            }

            return [$phone];
        }
    }
