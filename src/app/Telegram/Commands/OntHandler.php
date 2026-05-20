<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class OntHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $swnm = $params[0] ?? null;
            $serial = $params[1] ?? null;

            if (!$swnm || !$serial) {
                $this->bot->send($user->uid, "⚠️ Не задан коммутатор или SN");
                return;
            }

            $this->startReply($user->uid, "🔎 Ищу ONT <code>$serial</code>...");
            $res = $this->otk->request("/switch/pon/$swnm/ont/$serial");

            if (($res['error']['id'] ?? -1) === 0 && !empty($res['result'])) {
                $out = "🏠 <b>PON $swnm</b> ({$res['location']})\n";
                $out .= "🆔 SN: <code>$serial</code>\n".str_repeat("-", 20)."\n";

                foreach ($res['result'] as $key => $val) {
                    $out .= "<b>$key</b>: <code>$val</code>\n";
                }
                $this->appendReply($user->uid, $out);
            } else {
                $this->appendReply($user->uid, "❌ Ошибка: ".($res['error']['msg'] ?? 'не найдено'));
            }
            $this->logAction($user,'ont', $params);
        }
    }
