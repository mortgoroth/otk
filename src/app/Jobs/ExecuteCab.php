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

    class ExecuteCab implements ShouldQueue {
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

            $cable = $otk->request("/tg/$this->switchName/cab/$this->portNum");

            if (is_null($cable)) {
                $bot->update($uid, $this->messageId, "❌ Ошибка получения ответа от API.");
                return;
            }

            // 4. Обработка ошибок
            $errorId = $cable['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Некорректное имя коммутатора',
                    2 => '❌ Коммутатор не найден',
                    3 => '⚠️ Коммутатор недоступен',
                    4 => '❌ ОШИБКА! Порт не задан или неправильный',
                    63 => "❌ ОШИБКА! Порт $this->portNum не абонентский",
                    64 => "❌ ОШИБКА! Некорректный диапазон портов: $this->portNum",
                    65 => '❌ ОШИБКА! Порт лист пустой',
                    66 => '⚠️ Ошибка получения кабельной диагностики',
                    default => '❌ Неизвестная ошибка API',
                };
                $bot->update($uid, $this->messageId, $errorMsg);
                return;
            }

            // 5. Формирование отчета
            $result = $cable['result'];
            $report = "✅ <b>A4-{$this->switchName}/{$this->portNum}</b> ({$result['model']})\n";
            $report .= $this->parsePorts($result['ports'] ?? []);

            $bot->update($uid, $this->messageId, $report);

        }

        /**
         * Парсинг данных по портам (бывший $pars)
         */
        private function parsePorts (array $ports):string {
            if (empty($ports))
                return "\nДанные по портам отсутствуют.";

            $str = "\n🔌 <b>Результаты опроса:</b>\n";
            foreach ($ports as $portNumber => $data) {
                $str .= "  <b>Порт $portNumber:</b>\n";
                foreach ($data as $key => $val) {
                    // Логика linkStatus (есть/нет)
                    if ($key === 'linkStatus') {
                        $val = ($val === true) ? 'есть' : 'нет';
                    }
                    $str .= "   — <i>$key</i>: $val\n";
                }
                $str .= "\n";
            }

            return $str."🔚 ==========<b>THE END</b>==========";
        }

    }
