<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use Otk\Libs\Facades\DB\Topo;

    // фасад из composer.json

    class SwListHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $location = implode(' ', $params);

            if (!str_contains($location, ',')) {
                $this->bot->send($user->uid, '❌ ОШИБКА! Не задан номер дома!');
                return;
            }

            $this->startReply($user->uid, "🔍 Ищу все коммутаторы на <code>$location</code>...");

            $swlist = app(OtkApiService::class)->request('/tg/swlist', [
                'addr' => str_replace(' ', '+', $location), 'strict' => false
            ]);

            $errorId = $swlist['error']['id'] ?? -1;
            if ($errorId !== 0) {
                $msg = match ($errorId) {
                    1 => 'ОШИБКА! Некорректное имя',
                    2 => 'Коммутатор не найден',
                    131 => "Коммутаторов по адресу <code>$location</code> не найдено",
                    default => 'Ошибка поиска'
                };
                $this->appendReply($user->uid, "❌ $msg");
                return;
            }

            $topoTerms = Topo::allById();

            foreach ($swlist['result']['data'] as $address => $loc_data) {
                foreach ($loc_data as $house => $ad_data) {
                    $inline = [];
                    $str = "🏠 <b>$address</b>\n";

                    foreach ($ad_data as $podName => $switches) {
                        $podNum = str_replace('подъезд', '', $podName);
                        foreach ($switches as $swnm => $swdata) {
                            $flats = $swdata['flats'];
                            $topoName = $topoTerms[(int) $swdata['topo']]->name ?? 'н/д';
                            $str .= "  • <b>$swnm</b> {$swdata['type']} ($topoName)\n";

                            // Кнопка для портов
                            $cleanSwnm = str_replace(['A4-', 'a4-'], '', $swnm);
                            $inline[] = [['text' => "🔌 Порты $swnm", 'callback_data' => "/ports $cleanSwnm"]];
                        }
                        // Кнопка абонентов в конце блока дома/подъезда
                        $inline[] = [
                            [
                                'text' => "👥 Абоненты ($flats)", 'callback_data' => "/abons $house;$podNum $flats"
                            ]
                        ];
                    }

                    $this->bot->sendInline($user->uid, $str, $inline);
                }
            }
        }
    }
