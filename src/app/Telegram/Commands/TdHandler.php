<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class TdHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $contract = $params ?? null;
            if (!$contract) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Номер договора не задан");
                return;
            }

            $this->startReply($user->uid, "🔑 Ищу точки доступа по договору <code>$contract</code>...");
            $res = $this->otk->request("/tg/td/$contract");

            if (($res['error']['id'] ?? -1) === 0) {
                $result = $res['result'];
                $inline = [];

                $text = "📍 <b>Точки доступа:</b>\n";
                foreach ($result['str'] as $type => $ap) {
                    $text .= "<b>".strtoupper($type)."</b>\n";
                    $text .= is_array($ap) ? " • ".implode("\n • ", $ap) : " • $ap";
                    $text .= "\n";
                }

                foreach ($result['sw'] as $swnm => $port) {
                    $inline[] = [
                        ['text' => "🧪 Diag $swnm", 'callback_data' => "/diag $swnm $port"],
                        ['text' => "📏 Cab $swnm", 'callback_data' => "/cab $swnm $port"]
                    ];
                }

                $this->bot->sendInline($user->uid, $text, $inline);
            } else {
                $this->appendReply($user->uid, "❌ ТД по договору $contract не найдены");
            }
            $this->logAction($user,'td', $params);
        }
    }
