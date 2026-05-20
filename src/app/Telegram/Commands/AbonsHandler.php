<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class AbonsHandler extends BaseHandler {

        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Парсинг адреса, подъезда и квартир (логика из цикла)
            $location = '';
            $pod = false;
            $lc = [];

            foreach ($params as $str) {
                $lc[] = $str;
                if (str_contains($str, ',')) {
                    $location = implode(' ', $lc);
                    if (str_contains($location, ';')) {
                        $d = explode(';', $location);
                        $location = $d[0];
                        $pod = $d[1];
                    }
                    break;
                }
            }

            $diff = array_diff($params, $lc);
            $kv = !empty($diff) ? implode('', $diff) : false;

            if (empty($location)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Адрес не задан.");
                return;
            }

            // 2. Анонс поиска
            $statusMsg = "👥 Ищу абонентов: <code>$location</code>";
            $statusMsg .= $pod ? ", подъезд <b>$pod</b>" : "";
            $statusMsg .= $kv ? ", кв. <b>$kv</b>" : "";
            $this->startReply($user->uid, $statusMsg);

            // 3. Запрос к API
            $requestParams = ['addr' => $location, 'kv' => $kv];
            if ($pod)
                $requestParams['pod'] = $pod;

            $res = $this->otk->request('/tg/abons', $requestParams, true);

            // 4. Обработка результата
            if (($res['error']['id'] ?? -1) === 0) {
                $this->processAbonents($user->uid, $res['result']['found'] ?? []);
            } else {
                $this->appendReply($user->uid, "❌ Адрес <code>$location</code> не найден в отчете.");
            }
        }

        /**
         * Формирование карточек абонентов (бывший $pars)
         */
        private function processAbonents (int $uid, array $data):void {
            foreach ($data as $nodeAddr => $contracts) {
                foreach ($contracts as $contract => $abonData) {
                    $inlineKeyboard = [];
                    $text = "📍 <b>$nodeAddr</b>, кв. <b>{$abonData['kvar']}</b>\n";
                    $text .= "👤 Договор: <code>$contract</code> ({$abonData['abon_name']})\n\n";

                    foreach ($abonData['devices'] as $prov => $dev) {
                        $text .= "🔹 ".strtoupper($prov).": ";

                        $text .= match ($prov) {
                            'ntk_net' => "{$dev['dev_name']} / ".($dev['port_num'] ?? '??')."\n   Тариф: <b>{$dev['tarif']}</b>"."\n   Скорость: <b>".($dev['speed'] / 1024)." Мбит/с</b>\n",
                            'erth_net' => "{$dev['dev_name']} / ".($dev['port_num'] ?? '??')."\n",
                            'erth_ktv' => "\n   Тариф: <b>{$dev['tarif']}</b>\n",
                            default => "{$dev['dev_name']}\n",
                        };

                        // Если устройство - коммутатор A4, добавляем кнопки диагностики
                        if (str_starts_with($dev['dev_name'], 'A4-')) {
                            $sw = explode('-', $dev['dev_name']);
                            $swnm = $sw[1].'-'.$sw[2];
                            $diagParams = "$swnm ".$dev['port_num'];

                            $inlineKeyboard[] = [
                                ['text' => "🧪 Diag", 'callback_data' => "/diag $diagParams"],
                                ['text' => "📏 Cab", 'callback_data' => "/cab $diagParams"]
                            ];
                        }
                    }

                    // Отправляем карточку абонента отдельным сообщением с кнопками
                    $this->bot->sendInline($uid, $text, $inlineKeyboard);
                }
            }
            $this->bot->send($uid, "✅ Поиск завершен.");
        }
    }
