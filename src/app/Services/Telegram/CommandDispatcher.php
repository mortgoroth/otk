<?php

    namespace App\Services\Telegram;

    use App\Models\UserLdap;
    use App\Services\LDAP\LdapService;
    use App\Telegram\Commands\{AbonMacHandler, AbonsHandler, AcsHandler, Admin\AdminManageHandler, Admin\AdminsHandler, Admin\AlertManageHandler, Admin\HstatHandler, Admin\MalyavaHandler, Admin\ManagersHandler, AkbHandler, AmpHandler, BrokenHandler, CostHandler, DoorHandler, ElemMagErrorsHandler, ElemMagErrorsNewHandler, KtvSwDataHandler, LldpHandler, Long\BlinkHandler, Long\CabHandler, Long\ClrpsHandler, Long\DiagHandler, Long\ElemHandler, Long\ProbePingHandler, Long\SrchShortHandler, Long\SwConfHandler, MagHandler, MmChainHandler, MmDataHandler, MmHandler, NegotHandler, OltHandler, OntHandler, PingHandler, PonCompareHandler, PonListHandler, PortChangeHandler, PortsHandler, QuarHandler, SaveHandler, Sh16Handler, ShortHandler, Simple\HelpHandler, Simple\HistoryHandler, Simple\IdHandler, SwConfKillHandler, SwListHandler, TdHandler, UlHandler};
    use Exception;

    class CommandDispatcher {
        /**
         * Карта соответствия текстовых команд и их обработчиков.
         * Ключи — это то, что пишет юзер (без слэша), значения — классы хендлеров.
         */
        protected array $map = [
            // =============================== БАЛОВСТВО =======================================
            'malyava'     => MalyavaHandler::class,
            'малява'      => MalyavaHandler::class,
            '16-1'        => Sh16Handler::class,
            '16-2'        => Sh16Handler::class,
            'door'        => DoorHandler::class,

            // ============================ ПРОСТЫЕ КОМАНДЫ ====================================
            'id'          => IdHandler::class,
            'help'        => HelpHandler::class,
            'помощь'      => HelpHandler::class,
            'хелп'        => HelpHandler::class,
            'sos'         => HelpHandler::class,
            '?'           => HelpHandler::class,
            'history'     => HistoryHandler::class,
            'killswc'     => SwConfKillHandler::class,

            // =============================== КОМАНДЫ =========================================
            'пинг'        => PingHandler::class,
            'ping'        => PingHandler::class,
            'элем'        => ElemHandler::class,
            'elem'        => ElemHandler::class,
            'мигать'      => BlinkHandler::class,
            'blink'       => BlinkHandler::class,
            'конф'        => SwConfHandler::class,
            'config'      => SwConfHandler::class,
            'conf2'       => SwConfHandler::class,
            'кар'         => QuarHandler::class,
            'quar'        => QuarHandler::class,
            'диаг'        => DiagHandler::class,
            'diag'        => DiagHandler::class,
            'каб'         => CabHandler::class,
            'cab'         => CabHandler::class,
            'юл'          => UlHandler::class,
            'ul'          => UlHandler::class,
            'скорость'    => NegotHandler::class,
            'negot'       => NegotHandler::class,
            'списокком'   => SwListHandler::class,
            'swlist'      => SwListHandler::class,
            'ацс'         => AcsHandler::class,
            'acs'         => AcsHandler::class,
            'тд'          => TdHandler::class,
            'td'          => TdHandler::class,
            'порты'       => PortsHandler::class,
            'ports'       => PortsHandler::class,
            'портсек'     => ClrpsHandler::class,
            'clrps'       => ClrpsHandler::class,
            'абоны'       => AbonsHandler::class,
            'abons'       => AbonsHandler::class,
            'корот'       => ShortHandler::class,
            'short'       => ShortHandler::class,
            'srchshort'   => SrchShortHandler::class,

            // Группа Broken (добавление/удаление)
            'broken'      => BrokenHandler::class,
            'битый'       => BrokenHandler::class,
            'unbroken'    => BrokenHandler::class,
            'небитый'     => BrokenHandler::class,

            // Группа переноса
            'abportconf'  => PortChangeHandler::class,
            'перенос'     => PortChangeHandler::class,
            'change'      => PortChangeHandler::class,
            'portchange'  => PortChangeHandler::class,

            // КТВ и ММ
            'mm'          => MmHandler::class,
            'мм'          => MmHandler::class,
            'mmchain'     => MmChainHandler::class,
            'ммзвено'     => MmChainHandler::class,
            'mmdata'      => MmDataHandler::class,
            'amp'         => AmpHandler::class,

            // Опасные и системные
            'mag'         => MagHandler::class,
            'маг'         => MagHandler::class,
            'cost'        => CostHandler::class,
            'кост'        => CostHandler::class,
            'save'        => SaveHandler::class,
            'lldp'        => LldpHandler::class,
            'ллдп'        => LldpHandler::class,

            // PON
            'ponlist'     => PonListHandler::class,
            'списокпон'   => PonListHandler::class,
            'ponstat'     => PonCompareHandler::class,
            'понстат'     => PonCompareHandler::class,
            'pon'         => PonCompareHandler::class,
            'пон'         => PonCompareHandler::class,
            'olt'         => OltHandler::class,
            'олт'         => OltHandler::class,
            'онт'         => OntHandler::class,
            'ont'         => OntHandler::class,

            // Прочие
            'probe'       => ProbePingHandler::class,
            'пробник'     => ProbePingHandler::class,
            'mac'         => AbonMacHandler::class,
            'мак'         => AbonMacHandler::class,
            'elemerr'     => ElemMagErrorsHandler::class,
            'элемер'      => ElemMagErrorsHandler::class,
            'errelem'     => ElemMagErrorsHandler::class,
            'elemerr2'    => ElemMagErrorsNewHandler::class,
            'акб'         => AkbHandler::class,
            'akb'         => AkbHandler::class,
            'ктвсв'       => KtvSwDataHandler::class,
            'ktvsw'       => KtvSwDataHandler::class,
            'hstat'       => HstatHandler::class,

            'managers'    => ManagersHandler::class,
            'admins'      => AdminsHandler::class,

            // подписать|отписать юзера на уведомления
            'alert_on'    => AlertManageHandler::class,
            'alert_off'   => AlertManageHandler::class,
            // подписать|отписать юзера в админы
            'admin_on'    => AdminManageHandler::class,
            'admin_off'   => AdminManageHandler::class,
        ];

        public function dispatch (UserLdap $user, string $text, BotEngine $engine):void {
            // Парсим команду и параметры
            $parts = explode(' ', $text);
            $cmdName = str_replace('/', '', mb_strtolower(current($parts)));
            $params = array_slice($parts, 1);

            // 1. Проверка на вывод помощи по команде: "команда ?"
            if (isset($params[0]) && $params[0] === '?') {
                // Используем HelpHandler для генерации текста
                $helpHandler = app(HelpHandler::class);
                $helpText = $helpHandler->prepareHelp($cmdName);
                $engine->getBot()->send($user->uid, $helpText);
                return;
            }

            // 2. Проверка прав (логика ACCESSED_DEPARTMENTS)
            if (!$this->checkAccess($user, $cmdName)) {
                Console::warn("Access denied for UID {$user->uid} to command $cmdName");
                $engine->getBot()->send($user->uid, "⚠️ У вас нет прав на выполнение команды $cmdName!");
                return;
            }

            // 3. Запуск хендлера
            $handlerClass = $this->map[$cmdName] ?? null;

            if ($handlerClass) {
                $handler = app($handlerClass);
                try {
                    request()->merge(['command_name' => $cmdName]); // Сохраняем имя команды в глобальный запрос
                    $handler->handle($user, $params);
                } catch (Exception $e) {
                    $engine->getBot()->send($user->uid, "Ошибка: ".$e->getMessage());
                }
            } else {
                $engine->getBot()->send($user->uid, "Сам такой! Команда не найдена.");
            }
        }

        private function checkAccess (UserLdap $user, string $cmd):bool {
            // Базовые команды — для всех
            if (in_array($cmd, ['id', 'help', 'history', 'logout', 'помощь', 'sos', '?'])) {
                return true;
            }

            // Получаем разрешения
            $permissions = LdapService::getPermissions($user->department, $user->subdivision);

            // Если вернулось true — ограничений нет, разрешаем любую команду
            if ($permissions === true) {
                return true;
            }

            // Если вернулся массив — проверяем, есть ли в нем текущая команда
            return is_array($permissions) && in_array($cmd, $permissions);
        }

    }
