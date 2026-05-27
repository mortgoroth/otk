<?php

    namespace App\Telegram\Commands\Long;

    use App\Models\UserLdap;
    use App\Services\Telegram\Console;
    use App\Telegram\Commands\BaseHandler;
    use Exception;

    class SrchShortHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            // Очищаем адрес от кавычек, если они пришли из инлайна
            $location = str_replace("'", "", join(' ', $params));

            if (empty($location)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Адрес для поиска КЗ не задан.");
                return;
            }

            $this->startReply($user->uid, "⚡️ Ищу коротыши на: <code>$location</code>");
            $swlst = $this->otk->request(
                '/tg/swlist5',
                [
                    'addr' => $location
                ],
                true
            );
            if (empty($swlst['result'])) {
                $this->appendReply($user->uid, "❌ Коммутаторы по адресу <code>$location</code> не найдены.");
                return;
            }


            try {
                foreach ($swlst['result'] as $data) {
                    $cleanSwnm = str_replace('A4-', '', $data['swnm']);
                    $this->appendReply($user->uid, "📡 Опрашиваю <b>$cleanSwnm</b>...");

                    if (!ping($data['swip'])) {
                        $this->appendReply($user->uid, "🔸 <b>{$data['swnm']}:</b>\n  • недоступен");
                        continue;
                    }

                    if (!pingSnmp($data['swip'])) {
                        $this->appendReply($user->uid, "🔸 <b>{$data['swnm']}:</b>\n  • пингуется, но недоступен по SNMP");
                        continue;
                    }

                    $srchPort = $this->otk->request(
                        '/tg/srchshort',
                        [
                            'swnm' => $cleanSwnm,
                            'swip' => $data['swip']
                        ],
                        true
                    );

                    if (($srchPort['error']['id'] ?? -1) === 0) {
                        $shorted = $srchPort['result']['shorted'];
                        $report = "🔸 <b>{$data['swnm']}:</b>\n";

                        if (is_array($shorted)) {
                            $report .= $this->formatShorts($shorted);
                        } else {
                            $report .= " — $shorted\n";
                        }
                        $this->appendReply($user->uid, $report);
                    } else {
                        $err = $srchPort['error']['msg'] ?? 'Ошибка API';
                        $this->appendReply($user->uid, "⚠️ <b>{$data['swnm']}:</b> $err");
                    }
                }
                $this->appendReply($user->uid, "✅ Поиск коротышей на <code>$location</code> завершен.");

            } catch (Exception $e) {
                $this->appendReply($user->uid, "❌ Произошла ошибка при опросе оборудования: {$e->getMessage()}");
            }
            $this->logAction($user,'srchshort', $params);

        }

        /**
         * Форматирование структуры КЗ (бывший $pars)
         */
        private function formatShorts (array $arrShorted):string {
            $str = "";
            foreach ($arrShorted as $port => $portData) {
                $str .= " 🔌 <b>Порт $port:</b>\n";
                if (is_array($portData)) {
                    foreach ($portData as $pair => $status) {
                        $str .= "  • $pair: <code>$status</code>\n";
                    }
                } else {
                    $str .= "  • $portData\n";
                }
            }
            return $str;
        }

    }
