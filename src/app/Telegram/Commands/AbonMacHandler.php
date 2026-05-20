<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class AbonMacHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            if (!isset($params) || !isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор или порт не заданы.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];

            $this->startReply($user->uid, "🔎 Ищу маки на порту <code>$port</code> коммутатора <code>$swnm</code>...");

            $res = $this->otk->request("/tg/$swnm/mac/$port");

            if (isset($res['error']['id']) && $res['error']['id'] > 0) {
                $this->appendReply($user->uid, "❌ Ошибка: ".($res['error']['msg'] ?? 'API error'));
                return;
            }

            if (!empty($res['result'])) {
                $msg = "📋 <b>Результаты поиска:</b>\n";
                foreach ($res['result'] as $portNumber => $vlans) {
                    $msg .= "📍 <b>Порт $portNumber:</b>\n";
                    foreach ($vlans as $vlan => $macs) {
                        $msg .= "  🔹 VLAN $vlan:\n";
                        foreach ($macs as $mac) {
                            $msg .= "    <code>$mac</code>\n";
                        }
                    }
                }
                $this->appendReply($user->uid, $msg."\n✅ Поиск завершен.");
            } else {
                $this->appendReply($user->uid, "📭 Маков не найдено.\n✅ Поиск завершен.");
            }
            $this->logAction($user, 'mac', $params);
        }
    }
