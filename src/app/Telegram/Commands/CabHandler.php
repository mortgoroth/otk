<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class CabHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка коммутатора
            if (!isset($params[0])) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1] ?? 'all';

            // 2. Анонс (без HTML, как в оригинале, или используем <code> для чистоты)
            $this->startReply($user->uid, "📏 Определение длины линии <code>$swnm</code> / порт: <code>$port</code>...");

            // 3. Запрос к API
            $cable = $this->otk->request("/tg/$swnm/cab/$port");

            if (is_null($cable)) {
                $this->appendReply($user->uid, "❌ Ошибка получения ответа от API.");
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
                    63 => "❌ ОШИБКА! Порт $port не абонентский",
                    64 => "❌ ОШИБКА! Некорректный диапазон портов: $port",
                    65 => '❌ ОШИБКА! Порт лист пустой',
                    66 => '⚠️ Ошибка получения кабельной диагностики',
                    default => '❌ Неизвестная ошибка API',
                };
                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Формирование отчета
            $result = $cable['result'];
            $report = "✅ <b>A4-{$swnm}/{$port}</b> ({$result['model']})\n";
            $report .= $this->parsePorts($result['ports'] ?? []);

            $this->appendReply($user->uid, $report);
            $this->logAction($user,'broken', $params);
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
