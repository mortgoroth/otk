<?php

    namespace App\Telegram\Commands;

    namespace App\Telegram\Commands;

    use App\Models\Command;
    use App\Models\UserLdap;
    use App\Services\Ldap\LdapService;

    class HelpHandler extends BaseHandler {
        public bool $needToStore = false;

        public function handle (UserLdap $user, array $params):void {
            // 1. Получаем список разрешенных команд для пользователя
            $access = LdapService::ACCESSED_DEPARTMENTS[$user->department] ?? [];
            $allowedCommands = $access[$user->subdivision] ?? [];
            $isCutted = !empty($allowedCommands);

            // 2. Если запрошена помощь по конкретной команде: /help ping
            if (isset($params[0]) && $params[0] !== 'all') {
                $cmdToHelp = strtolower($params[0]);

                // Проверка прав на эту конкретную команду
                if ($isCutted && !in_array($cmdToHelp, $allowedCommands)) {
                    $this->bot->send($user->uid, "У вас нет прав на просмотр справки по команде <b>$cmdToHelp</b>");
                    return;
                }

                $helpText = $this->prepareHelp($cmdToHelp);
                $this->bot->send($user->uid, $helpText);
                return;
            }

            // 3. Если запрошен общий список кнопок (генерация Inline меню)
            // Если доступ ограничен — берем только разрешенные, иначе — все из БД/конфига
            $commandsSource = $isCutted ? $allowedCommands : Command::where('showhelp', true)
                ->pluck('name')
                ->toArray();

            $inline = [];
            foreach ($commandsSource as $cmdName) {
                $inline[] = [
                    'text' => "/help $cmdName", 'callback_data' => "/help $cmdName"
                ];
            }

            // Формируем сетку: кнопка "Все команды" сверху + остальные по 2 в ряд
            $keyboard = array_merge(
                [[['text' => '📖 Показать всё описание', 'callback_data' => '/help all']]],
                array_chunk($inline, 2)
            );

            // Блок для тех, кто подписан на алерты (alert = true)
            if ($user->alert) {
                $keyboard[] = [
                    ['text' => '🔔 Alert ON', 'callback_data' => '/alert_on '],
                    ['text' => '🔕 Alert OFF', 'callback_data' => '/alert_off ']
                ];
            }

            // Блок для Администраторов (is_admin = true)
            if ($user->is_admin) {
                // Кнопки управления админами
                $keyboard[] = [
                    ['text' => '👨‍💻 Admin ON', 'callback_data' => '/admin_on '],
                    ['text' => '🚫 Admin OFF', 'callback_data' => '/admin_off ']
                ];

                // Если админ не подписан на алерты, ему всё равно нужны кнопки управления ими
                if (!$user->alert) {
                    $keyboard[] = [
                        ['text' => '🔔 Alert ON', 'callback_data' => '/alert_on '],
                        ['text' => '🔕 Alert OFF', 'callback_data' => '/alert_off ']
                    ];
                }
            }

            $this->bot->sendInline($user->uid, "Выберите команду для справки.\n\n💡 Также можно написать: <code>команда ?</code>", $keyboard);
        }

        /**
         * Формирование текста справки (бывший prepareHelp)
         */
        public function prepareHelp (string $cmdName):string {
            if ($cmdName === 'all') {
                return "Здесь будет полный список всех доступных вам инструкций...";
            }

            // Ищем описание в БД (таблица telegram.commands)
            $command = Command::where('name', $cmdName)
                ->first();

            if (!$command) {
                return "Инструкция для команды <b>$cmdName</b> не найдена.";
            }

            return "❓ <b>Справка по команде /$cmdName</b>\n\n"."Описание: <i>$command->description</i>\n"."Пример: <code>/".($command->example ?? '')."</code>";
        }
    }
