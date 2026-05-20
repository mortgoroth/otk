<?php

    namespace App\Services\Telegram;

    use App\Models\UserLdap;
    use App\Services\LDAP\LdapService;
    use App\Telegram\Commands\{IdHandler, HelpHandler, HistoryHandler, ElemHandler, PingHandler};

    class CommandDispatcher {
        // Маппинг старого switch в новые классы
        /**
         * Карта соответствия текстовых команд и их обработчиков.
         * Ключи — это то, что пишет юзер (без слэша), значения — классы хендлеров.
         */
        protected array $map = [
            // =============================== БАЛОВСТВО =======================================
            'malyava'     => \App\Telegram\Commands\MalyavaHandler::class,
            'малява'      => \App\Telegram\Commands\MalyavaHandler::class,
            '16-1'        => \App\Telegram\Commands\Sh16Handler::class,
            '16-2'        => \App\Telegram\Commands\Sh16Handler::class,
            'door'        => \App\Telegram\Commands\DoorHandler::class,

            // ============================ ПРОСТЫЕ КОМАНДЫ ====================================
            'id'          => \App\Telegram\Commands\IdHandler::class,
            'help'        => \App\Telegram\Commands\HelpHandler::class,
            'помощь'      => \App\Telegram\Commands\HelpHandler::class,
            'хелп'        => \App\Telegram\Commands\HelpHandler::class,
            'sos'         => \App\Telegram\Commands\HelpHandler::class,
            '?'           => \App\Telegram\Commands\HelpHandler::class,
            'history'     => \App\Telegram\Commands\HistoryHandler::class,
            'killswc'     => \App\Telegram\Commands\SwConfKillHandler::class,

            // =============================== КОМАНДЫ =========================================
            'пинг'        => \App\Telegram\Commands\PingHandler::class,
            'ping'        => \App\Telegram\Commands\PingHandler::class,
            'элем'        => \App\Telegram\Commands\ElemHandler::class,
            'elem'        => \App\Telegram\Commands\ElemHandler::class,
            'мигать'      => \App\Telegram\Commands\BlinkHandler::class,
            'blink'       => \App\Telegram\Commands\BlinkHandler::class,
            'конф'        => \App\Telegram\Commands\SwConfHandler::class,
            'config'      => \App\Telegram\Commands\SwConfHandler::class,
            'conf2'       => \App\Telegram\Commands\SwConfHandler::class,
            'кар'         => \App\Telegram\Commands\QuarHandler::class,
            'quar'        => \App\Telegram\Commands\QuarHandler::class,
            'диаг'        => \App\Telegram\Commands\DiagHandler::class,
            'diag'        => \App\Telegram\Commands\DiagHandler::class,
            'каб'         => \App\Telegram\Commands\CabHandler::class,
            'cab'         => \App\Telegram\Commands\CabHandler::class,
            'юл'          => \App\Telegram\Commands\UlHandler::class,
            'ul'          => \App\Telegram\Commands\UlHandler::class,
            'скорость'    => \App\Telegram\Commands\NegotHandler::class,
            'negot'       => \App\Telegram\Commands\NegotHandler::class,
            'списокком'   => \App\Telegram\Commands\SwListHandler::class,
            'swlist'      => \App\Telegram\Commands\SwListHandler::class,
            'ацс'         => \App\Telegram\Commands\AcsHandler::class,
            'acs'         => \App\Telegram\Commands\AcsHandler::class,
            'тд'          => \App\Telegram\Commands\TdHandler::class,
            'td'          => \App\Telegram\Commands\TdHandler::class,
            'порты'       => \App\Telegram\Commands\PortsHandler::class,
            'ports'       => \App\Telegram\Commands\PortsHandler::class,
            'портсек'     => \App\Telegram\Commands\ClrpsHandler::class,
            'clrps'       => \App\Telegram\Commands\ClrpsHandler::class,
            'абоны'       => \App\Telegram\Commands\AbonsHandler::class,
            'abons'       => \App\Telegram\Commands\AbonsHandler::class,
            'корот'       => \App\Telegram\Commands\ShortHandler::class,
            'short'       => \App\Telegram\Commands\ShortHandler::class,
            'srchshort'   => \App\Telegram\Commands\SrchShortHandler::class,

            // Группа Broken (добавление/удаление)
            'broken'      => \App\Telegram\Commands\BrokenHandler::class,
            'битый'       => \App\Telegram\Commands\BrokenHandler::class,
            'unbroken'    => \App\Telegram\Commands\BrokenHandler::class,
            'небитый'     => \App\Telegram\Commands\BrokenHandler::class,

            // Группа переноса
            'abportconf'  => \App\Telegram\Commands\PortChangeHandler::class,
            'перенос'     => \App\Telegram\Commands\PortChangeHandler::class,
            'change'      => \App\Telegram\Commands\PortChangeHandler::class,
            'portchange'  => \App\Telegram\Commands\PortChangeHandler::class,

            // КТВ и ММ
            'mm'          => \App\Telegram\Commands\MmHandler::class,
            'мм'          => \App\Telegram\Commands\MmHandler::class,
            'mmchain'     => \App\Telegram\Commands\MmChainHandler::class,
            'ммзвено'     => \App\Telegram\Commands\MmChainHandler::class,
            'mmdata'      => \App\Telegram\Commands\MmDataHandler::class,
            'amp'         => \App\Telegram\Commands\AmpHandler::class,

            // Опасные и системные
            'mag'         => \App\Telegram\Commands\MagHandler::class,
            'маг'         => \App\Telegram\Commands\MagHandler::class,
            'cost'        => \App\Telegram\Commands\CostHandler::class,
            'кост'        => \App\Telegram\Commands\CostHandler::class,
            'save'        => \App\Telegram\Commands\SaveHandler::class,
            'lldp'        => \App\Telegram\Commands\LldpHandler::class,
            'ллдп'        => \App\Telegram\Commands\LldpHandler::class,

            // PON
            'ponlist'     => \App\Telegram\Commands\PonListHandler::class,
            'списокпон'   => \App\Telegram\Commands\PonListHandler::class,
            'ponstat'     => \App\Telegram\Commands\PonCompareHandler::class,
            'понстат'     => \App\Telegram\Commands\PonCompareHandler::class,
            'pon'         => \App\Telegram\Commands\PonCompareHandler::class,
            'пон'         => \App\Telegram\Commands\PonCompareHandler::class,
            'olt'         => \App\Telegram\Commands\OltHandler::class,
            'олт'         => \App\Telegram\Commands\OltHandler::class,
            'онт'         => \App\Telegram\Commands\OntHandler::class,
            'ont'         => \App\Telegram\Commands\OntHandler::class,

            // Прочие
            'probe'       => \App\Telegram\Commands\ProbePingHandler::class,
            'пробник'     => \App\Telegram\Commands\ProbePingHandler::class,
            'mac'         => \App\Telegram\Commands\AbonMacHandler::class,
            'мак'         => \App\Telegram\Commands\AbonMacHandler::class,
            'elemerr'     => \App\Telegram\Commands\ElemMagErrorsHandler::class,
            'элемер'      => \App\Telegram\Commands\ElemMagErrorsHandler::class,
            'errelem'     => \App\Telegram\Commands\ElemMagErrorsHandler::class,
            'elemerr2'    => \App\Telegram\Commands\ElemMagErrorsNewHandler::class,
            'акб'         => \App\Telegram\Commands\AkbHandler::class,
            'akb'         => \App\Telegram\Commands\AkbHandler::class,
            'ктвсв'       => \App\Telegram\Commands\KtvSwDataHandler::class,
            'ktvsw'       => \App\Telegram\Commands\KtvSwDataHandler::class,
            'hstat'       => \App\Telegram\Commands\HstatHandler::class,

            // подписать|отписать юзера на уведомления
            'alert_on'  => \App\Telegram\Commands\AlertManageHandler::class,
            'alert_off' => \App\Telegram\Commands\AlertManageHandler::class,
        ];

        public function dispatch (UserLdap $user, string $text, $engine):void {
            // Парсим команду и параметры (аналог setCmdParams)
            $parts = explode(' ', $text);
            $cmdName = str_replace('/', '', mb_strtolower(current($parts)));
            $params = array_slice($parts, 1);

            // 1. Проверка на вывод помощи по команде: "команда ?"
            if (isset($params[0]) && $params[0] === '?') {
                $engine->getBot()->send($user->uid, "Справка по команде $cmdName..."); // Тут вызов help для команды
                return;
            }

            // 2. Проверка прав (логика ACCESSED_DEPARTMENTS)
            if (!$this->checkAccess($user, $cmdName)) {
                $engine->getBot()->send($user->uid, "У вас нет прав на выполнение команды $cmdName!");
                return;
            }

            // 3. Запуск хендлера
            $handlerClass = $this->map[$cmdName] ?? null;

            if ($handlerClass) {
                $handler = app($handlerClass);
                try {
                    request()->merge(['command_name' => $cmdName]); // Сохраняем имя команды в глобальный запрос
                    $handler->handle($user, $params);
                    // Логирование выполняется внутри BaseHandler или здесь после выполнения
                } catch (\Exception $e) {
                    $engine->getBot()->send($user->uid, "Ошибка: ".$e->getMessage());
                }
            } else {
                $engine->getBot()->send($user->uid, "Сам такой! Команда не найдена.");
            }
        }

        private function checkAccess (UserLdap $user, string $cmd):bool {
            // Базовые команды доступны всем авторизованным
            if (in_array($cmd, ['id', 'help', 'history', 'logout']))
                return true;

            $access = LdapService::ACCESSED_DEPARTMENTS[$user->department] ?? [];
            $allowed = $access[$user->subdivision] ?? [];

            return in_array($cmd, $allowed);
        }
    }
