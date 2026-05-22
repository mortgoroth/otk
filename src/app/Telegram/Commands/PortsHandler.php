<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;

    class PortsHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];

            // 1. Анонс
            $this->startReply($user->uid, "🔌 Опрашиваю состояние портов на <code>$swnm</code>...");

            // 2. Запрос к API
            $res = $this->otk->request("/tg/$swnm/ports");
            Console::debug("PORTS REPLY: ".json_encode($res, JSON_UNESCAPED_UNICODE));
            $errorId = $res['error']['id'] ?? -1;
            if ($errorId !== 0) {
                Console::debug("ERR <> 0: $errorId");
                $msg = match ($errorId) {
                    1   => 'ОШИБКА! Некорректное имя',
                    2   => 'Коммутатор не найден',
                    3   => '⚠️ Коммутатор недоступен',
                    110 => "Данные о портах для $swnm не найдены",
                    default => 'Ошибка опроса'
                };
                $this->appendReply($user->uid, "❌ $msg");
                Console::debug("ERR <> 0: $msg");
                return;
            }

            // 3. Формирование отчета
            $result = $res['result'];
            $model  = htmlspecialchars($result['swtype'] ?? 'Unknown');
            Console::debug("FORM 1");

            $addr = htmlspecialchars($result['addr'] ?? 'Не указан');
            $swnm = htmlspecialchars($swnm);
            $topo = htmlspecialchars($result['topo'] ?? 'не указана');
            Console::debug("ADDR: $addr");
            $reply = "✅ <b>A4-$swnm</b> ($model)\n"."📍 $addr\n"."📐 Топология: $topo\n";
            Console::debug("REPLY1: $reply");

            if ($model !== 'TRK-300') {
                $reply .= "\n📊 <b>Распределение портов:</b>\n";
                $reply .= $this->formatPortData($result['ports'] ?? []);
                Console::debug("REPLY2 TRK300: $reply");
            }

            Console::debug("BEFORE APPEND: $reply");
            Console::debug("BEFORE APPEND: $reply");

            if ($this->messageId > 0) {
                usleep(300000);
                $this->appendReply($user->uid, $reply);
            } else {
                // Если по какой-то причине ID нет, просто отправляем новым сообщением
                $this->bot->send($user->uid, $this->accumulatedText . $reply);
            }

//            $this->appendReply($user->uid, $reply);
            Console::debug("AFTER REPLY: $reply");
            $this->logAction($user,'ports', $params);
            Console::debug("AFTER LOG: $reply");
        }

        /**
         * Преобразование сырых данных портов в текст с диапазонами
         */
        private function formatPortData (array $data):string {
            $translations = [
                'broken' => 'неисправные',
                'used'   => 'занятые',
                'serv'   => 'служебные',
                'free'   => 'свободные',
                'ul'     => 'ЮЛ',
                'vip'    => 'VIP',
                'svip'   => 'SVIP',
                'tel'    => 'телефония',
                'erth'   => 'ЭРТХ',
                'qinq'   => 'QinQ',
                'dom'    => 'Домофоны',
                'm100'   => 'Тариф 100+',
            ];
//{
//  "broken":"",
//  "used":"1,2,3,4,6,7,9,12,13,15,17,21",
//  "free":"5,8,10,11,14,16,18,19,20,22",
//  "serv":"1",
//  "ul":"",
//  "vip":"",
//  "svip":"",
//  "tel":"",
//  "erth":"",
//  "qinq":"",
//  "dom":"",
//  "m100":"2,4,6,7,12,17"
//}
            $output = "";
            foreach ($data as $key => $val) {
                $label = $translations[$key] ?? $key;
                $ports = array_filter(explode(',', $val));
                $count = count($ports);
                $ranges = $count > 0 ? $this->compressRanges($ports) : 'нет';
                $safeLabel = htmlspecialchars($label);
                $safeRanges = htmlspecialchars($ranges); // Особенно тут!
                $output .= " • $safeLabel ($count): <code>$safeRanges</code>\n";
            }
            return $output;
        }

        /**
         * Логика сжатия массива портов в диапазоны (1,2,3,5 -> 1-3,5)
         */
        private function compressRanges (array $ports):string {
            sort($ports, SORT_NUMERIC);
            $ranges = [];
            $start = $ports[0];
            $end = $start;

            for ($i = 1; $i <= count($ports); $i++) {
                if (isset($ports[$i]) && $ports[$i] - $end == 1) {
                    $end = $ports[$i];
                } else {
                    $ranges[] = ($start == $end) ? $start : "$start-$end";
                    if (isset($ports[$i])) {
                        $start = $ports[$i];
                        $end = $start;
                    }
                }
            }

            return implode(',', $ranges);
        }
    }


