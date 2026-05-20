<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class AcsHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            $abonip = $params[0] ?? null;
            if (!$abonip) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! IP абонента не задан");
                return;
            }

            $this->startReply($user->uid, "📡 Ищу в ACS абонента <code>$abonip</code>...");
            $res = $this->otk->request("/tg/acs/$abonip");

            $errorId = $res['error']['id'] ?? -1;
            if ($errorId === 0) {
                $devices = $res['result']['device'];
                $output = "✅ <b>Устройства ACS для $abonip:</b>\n";

                if (is_array($devices)) {
                    foreach ($devices as $num => $data) {
                        $output .= "\n📦 <b>Device $num:</b>\n";
                        foreach ($data as $key => $val) {
                            $output .= " — <i>$key</i>: <code>$val</code>\n";
                        }
                    }
                } else {
                    $output .= $devices;
                }
                $this->appendReply($user->uid, $output);
            } else {
                $msg = ($errorId == 21) ? "Устройств для пользователя $abonip не найдено" : "Ошибка IP";
                $this->appendReply($user->uid, "❌ $msg");
            }
        }
    }
