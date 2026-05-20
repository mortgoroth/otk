<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class ShortHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $location = implode(' ', $params);
            if (empty($location)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Адрес не задан.");
                return;
            }

            // Проверка формата Улица,дом
            if (!preg_match('/[а-я]*,\d*/i', $location)) {
                $this->bot->send($user->uid, "❌ ОШИБКА! Неверный адрес. Формат: Улица,дом");
                return;
            }

            $this->startReply($user->uid, "🔎 Ищу узлы по запросу: <code>$location</code>");

            $res = $this->otk->request('/tg/short', [
                'location' => $location, 'strict' => false
            ]);

            if (($res['error']['id'] ?? -1) === 0) {
                foreach ($res['result']['addresses'] as $address => $data) {
                    $msg = "📍 <b>$address</b>\n";
                    foreach ($data['switches'] as $swdata) {
                        $msg .= " • {$swdata['name']} ({$swdata['model']})\n";
                    }

                    // Кнопка для запуска поиска КЗ по конкретному адресу
                    $inline = [
                        [
                            ['text' => "⚡️ Найти КЗ на этом узле", 'callback_data' => "/srchshort $address"]
                        ]
                    ];

                    $this->bot->sendInline($user->uid, $msg, $inline);
                }
            } else {
                $this->appendReply($user->uid, "❌ Узлов не нашел...");
            }
            $this->logAction($user,'short', $params);
        }
    }
