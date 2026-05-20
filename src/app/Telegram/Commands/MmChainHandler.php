<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class MmChainHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $chain = $params[0] ?? null;
            if (!$chain) {
                $this->bot->send($user->uid, "⚠️ КТВ-звено не задано");
                return;
            }

            $this->startReply($user->uid, "⛓ Ищу ММ в звене <code>$chain</code>...");
            $res = $this->otk->request("/tg/mm/chain/$chain");

            if (($res['error']['id'] ?? -1) === 0) {
                foreach ($res['result'] as $data) {
                    $mmName = empty($data['mmnam']) ? 'отсутствует' : $data['mmnam'];
                    $text = "📍 <b>Адрес:</b> {$data['raddr']}\n"."📡 <b>ММ:</b> $mmName\n"."📥 <b>Приемник:</b> {$data['recvr']}\n"."🔊 <b>Усилитель:</b> {$data['usktv']} ({$data['usadd']})";

                    $buttons = [];
                    if (!empty($data['usktv'])) {
                        $buttons[] = ['text' => "⚡️ Усилитель", 'callback_data' => "/amp {$data['usktv']}"];
                    }
                    if (!empty($data['mmnam'])) {
                        $buttons[] = ['text' => "📊 Данные ММ", 'callback_data' => "/mmdata {$data['mmnam']}"];
                    }

                    $this->bot->sendInline($user->uid, $text, [$buttons]);
                }
            } else {
                $this->appendReply($user->uid, "❌ В данном звене ММ не найдены");
            }
            $this->logAction($user,'mmchain', $params);

        }
    }
