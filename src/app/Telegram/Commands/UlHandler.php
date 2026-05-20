<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\DebugController;

    class UlHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params;

            // 2. Начало выполнения
            $this->startReply($user->uid, "🏢 Поиск возможности подключения ЮЛ на <code>$swnm</code>...");

            // 3. Запрос к API
            $connUl = $this->otk->request("/tg/ul/$swnm");
            DebugController::write($connUl, 'UL_SEARCH_RESULT');

            // 4. Обработка ошибок
            $errorId = $connUl['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Некорректное имя коммутатора',
                    2 => '❌ ОШИБКА! Ошибка подключения к БД.',
                    3 => "❌ Не найден узел с коммутатором <code>$swnm</code>",
                    4 => "❌ Коммутатор <code>$swnm</code> недоступен по SNMP",
                    222 => "❌ Не найден узел для коммутатора <code>$swnm</code>",
                    223 => '⚠️ Нет свободных портов на единственном в узле коммутаторе',
                    224 => '⚠️ Все коммутаторы в узле заполнены ЮЛ и/или ФЛ с тарифом 300+',
                    225 => '⚠️ В узле нет коммутаторов со свободными портами',
                    default => '❌ Неизвестная ошибка API',
                };
                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Успешный результат
            $flReplace = $connUl['fl_need_replace'] ?? false;
            $suffix = "ЮЛ в <code>{$connUl['destSwitch']}</code> ({$connUl['destSwType']}), порт <b>{$connUl['destPort']}</b>";

            if ($flReplace) {
                $mcastErr = empty($connUl['mcast_error']) ? '' : "⚠️ Проблемы с настройкой multicast на порту ФЛ.\n";
                $reply = "{$mcastErr}🔄 Необходимо переподключить ФЛ: <code>{$connUl['fl_result']}</code>\n📍 Место: $suffix";
            } else {
                $reply = "✅ Можно подключать.\n📍 Место: $suffix";
            }

            $this->appendReply($user->uid, $reply);
            $this->logAction($user,'ul', $params);
        }
    }
