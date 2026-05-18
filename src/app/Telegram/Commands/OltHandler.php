<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class OltHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $swnm = $params[0] ?? null;
            if (!$swnm)
                return;

            $this->startReply($user->uid, "📟 Ищу OLT <code>$swnm</code>...");
            $res = app(\App\Services\Otk\OtkApiService::class)->request("/switch/pon/$swnm/olt/serials");

            if (($res['error']['id'] ?? -1) === 0 && !empty($res['result']['serials'])) {
                $out = "📡 <b>PON $swnm</b> ({$res['result']['location']}):\n";
                $buttons = [];

                foreach ($res['result']['serials'] as $ser) {
                    $buttons[] = [
                        ['text' => "📄 ONT $ser", 'callback_data' => "/ont $swnm $ser"],
                        ['text' => "📊 Сравнить $ser", 'callback_data' => "/pon $swnm $ser"]
                    ];

                    // Telegram лимит на количество кнопок или длину сообщения
                    if (count($buttons) >= 20) {
                        $this->bot->sendInline($user->uid, $out, $buttons);
                        $buttons = [];
                    }
                }
                if (!empty($buttons))
                    $this->bot->sendInline($user->uid, $out, $buttons);
            } else {
                $this->appendReply($user->uid, "❌ Ошибка или данных нет: ".($res['error']['msg'] ?? ''));
            }
        }
    }
