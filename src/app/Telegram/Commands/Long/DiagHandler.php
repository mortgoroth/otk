<?php

    namespace App\Telegram\Commands\Long;

    use App\Models\UserLdap;
    use App\Telegram\Commands\BaseHandler;

    class DiagHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметров (Коммутатор и Порт)
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }
            if (!isset($params[1])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];

            // 2. Начало выполнения
            $this->startReply($user->uid, "🧪 Кабельная диагностика и ошибки <code>$swnm</code> / порт <code>$port</code>. Ожидание: 1 мин.");

            // 3. Запрос к API (может длиться около минуты)
            $abonDiag = $this->otk->request("/tg/$swnm/diag/$port");

            // 4. Обработка ошибок через match
            $errorId = $abonDiag['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Некорректное имя коммутатора',
                    2 => '❌ Коммутатор не найден',
                    3 => '⚠️ Коммутатор недоступен',
                    4 => '❌ Порт не задан или неправильный',
                    default => '❌ Неизвестная ошибка API',
                };
                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Формирование отчета
            $report = "📊 <b>Результат для $swnm / $port:</b>\n"."Модель: <code>".($abonDiag['swtype'] ?? 'н/д')."</code>\n"."Адрес: <i>".($abonDiag['addr'] ?? 'н/д')."</i>\n"."Адрес (SNMP): <code>".($abonDiag['snmp_addr'] ?? 'н/д')."</code>\n"."Линк: ".($abonDiag['link'] ?? 'н/д')."\n"."Порт: ".($abonDiag['pstatus'] ?? 'н/д')."\n"."Скорость: ".($abonDiag['portSettings'] ?? 'н/д')."\n";

            // Добавляем подразделы через парсеры
            $report .= $this->parseSimpleArray($abonDiag['errors']['diff'] ?? [], "Разница ошибок за минуту");
            $report .= $this->parseSimpleArray($abonDiag['bandwidth'] ?? [], "Bandwidth");
            $report .= $this->parseCablePairs($abonDiag['cabdiag']['pairs'] ?? []);

            $report .= "\n📦 <b>Пакеты на порту:</b>\n"." — Входящие: <code>".($abonDiag['packets']['in'] ?? 0)."</code>\n"." — Исходящие: <code>".($abonDiag['packets']['out'] ?? 0)."</code>";

            // 6. Финальное обновление сообщения
            $this->appendReply($user->uid, $report);
            $this->logAction($user,'diag', $params);
        }

        /**
         * Парсинг плоских массивов (бывший $pars)
         */
        private function parseSimpleArray (array $array, string $type):string {
            if (empty($array))
                return "";

            $str = "\n📌 <b>$type:</b>\n";
            foreach ($array as $key => $value) {
                if (!is_array($value)) {
                    $str .= "  <code>$key</code>: $value\n";
                }
            }
            return $str;
        }

        /**
         * Парсинг результатов TDR (бывший $pars2)
         */
        private function parseCablePairs (array $pairs):string {
            if (empty($pairs))
                return "";

            $str = "\n🔌 <b>Диагностика кабеля:</b>\n";
            foreach ($pairs as $pair => $pdata) {
                $status = $pdata['status'] ?? 'н/д';
                $length = $pdata['length'] ?? '0';
                $str .= "Пара $pair: <code>$status</code>, <b>$length м</b>\n";
            }
            return $str;
        }

    }
