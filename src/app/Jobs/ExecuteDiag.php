<?php

    namespace App\Jobs;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\Transport;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Exception;

    class ExecuteDiag implements ShouldQueue {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public $timeout = 700;

        public function __construct(
            protected UserLdap $user,
            protected string   $switchName,
            protected string   $portNum,
            protected int      $messageId,
        ) {}

        public function handle (OtkApiService $otk, Transport $bot):void {
            // 2. Начало выполнения
            $uid = $this->user->uid;

            // 3. Запрос к API (может длиться около минуты)
            $abonDiag = $otk->request("/tg/$this->switchName/diag/$this->portNum");

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
                $bot->update($uid, $this->messageId, $errorMsg);
                return;
            }

            // 5. Формирование отчета
            $report = "📊 <b>Результат для $this->switchName / $this->portNum:</b>\n"."Модель: <code>".($abonDiag['swtype'] ?? 'н/д')."</code>\n"."Адрес: <i>".($abonDiag['addr'] ?? 'н/д')."</i>\n"."Адрес (SNMP): <code>".($abonDiag['snmp_addr'] ?? 'н/д')."</code>\n"."Линк: ".($abonDiag['link'] ?? 'н/д')."\n"."Порт: ".($abonDiag['pstatus'] ?? 'н/д')."\n"."Скорость: ".($abonDiag['portSettings'] ?? 'н/д')."\n";

            // Добавляем подразделы через парсеры
            $report .= $this->parseSimpleArray($abonDiag['errors']['diff'] ?? [], "Разница ошибок за минуту");
            $report .= $this->parseSimpleArray($abonDiag['bandwidth'] ?? [], "Bandwidth");
            $report .= $this->parseCablePairs($abonDiag['cabdiag']['pairs'] ?? []);

            $report .= "\n📦 <b>Пакеты на порту:</b>\n"." — Входящие: <code>".($abonDiag['packets']['in'] ?? 0)."</code>\n"." — Исходящие: <code>".($abonDiag['packets']['out'] ?? 0)."</code>";

            // 6. Финальное обновление сообщения
            $bot->update($uid, $this->messageId, $report);

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
