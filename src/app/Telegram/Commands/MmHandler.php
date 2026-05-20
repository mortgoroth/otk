<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class MmHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $addr = $params[0] ?? null;
            if (!$addr) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Адрес не задан.");
                return;
            }

            $this->startReply($user->uid, "🔎 Ищу ММ по адресу <code>$addr</code>...");
            $res = $this->otk->request(
                '/tg/mm/addr',
                [
                    'addr' => $addr
                ],
                true
            );

            if (($res['error']['id'] ?? -1) === 0) {
                foreach ($res['result'] as $data) {
                    $text = "🏠 <b>Адрес:</b> {$data['raddr']}\n"."📡 <b>ММ:</b> {$data['mmnam']}\n"."⛓ <b>КТВ-звено:</b> {$data['chain']}\n"."📥 <b>Приемник:</b> {$data['recvr']}\n"."🔊 <b>Усилитель:</b> {$data['usktv']} ({$data['usadd']})";

                    $buttons = [];
                    $buttons[] = ['text' => "📊 Данные ММ", 'callback_data' => "/mmdata {$data['mmnam']}"];

                    if (!empty($data['usktv'])) {
                        $buttons[] = ['text' => "⚡️ Усилитель", 'callback_data' => "/amp {$data['usktv']}"];
                    }

                    $this->bot->sendInline($user->uid, $text, [$buttons]);
                }
            } else {
                $msg = match ($res['error']['id'] ?? 0) {
                    230 => 'ММ по данному адресу не найдены',
                    231 => 'Неправильный адрес (нет номера дома)',
                    default => 'Ошибка поиска'
                };
                $this->appendReply($user->uid, "❌ ОШИБКА! $msg");
            }
            $this->logAction($user,'mm', $params);
        }
    }
