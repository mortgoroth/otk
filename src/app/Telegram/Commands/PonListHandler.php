<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class PonListHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $input = implode(' ', $params);
            $this->startReply($user->uid, "🔍 Ищу все PON коммутаторы на <code>$input</code>...");

            $res = app(\App\Services\Otk\OtkApiService::class)->request('/switch/pon/list/'.urlencode($input));

            if (($res['error']['id'] ?? -1) === 0 && !empty($res['result'])) {
                foreach ($res['result'] as $data) {
                    $swnm = str_replace(['A4-', 'a4-'], '', $data['host']);
                    $text = "📍 <b>{$data['location']}</b>\n<code>$swnm</code> ({$data['model']})";

                    $this->bot->sendInline($user->uid, $text, [
                        [
                            ['text' => "📡 OLT Serials", 'callback_data' => "/olt $swnm"]
                        ]
                    ]);
                }
            } else {
                $this->appendReply($user->uid, "❌ Коммутаторы PON не найдены в <code>$input</code>");
            }
        }
    }
