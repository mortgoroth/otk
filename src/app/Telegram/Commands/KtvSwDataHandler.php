<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class KtvSwDataHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $host = $params[0] ?? null;
            if (!$host) {
                $this->bot->send($user->uid, "⚠️ КТВ-переключатель не задан");
                return;
            }

            $this->startReply($user->uid, "📡 Опрашиваю КТВ-переключатель <code>$host</code>...");
            $res = $this->otk->request("/ktv/sw/$host/stats");

            if (($res['error']['id'] ?? -1) === 0) {
                $stats = $res['stats'];
                $text = "🏠 <b>Адрес:</b> {$res['location']}\n"."📟 <b>Устройство:</b> {$res['ktvnm']} ({$res['ktvip']})\n";

                if (!($stats['avail'] ?? false)) {
                    $text .= "🔴 <b>Статус:</b> недоступен";
                } else {
                    $text .= "📦 <b>Модель:</b> {$res['model']}\n"."📉 <b>Сигнал А:</b> {$stats['siga']} dB\n"."📉 <b>Сигнал В:</b> {$stats['sigb']} dB\n"."⚙️ <b>Режим:</b> ".($stats['mode'] == 1 ? 'ручной 👤' : 'авто 🤖')."\n"."🔌 <b>Активный канал:</b> ".($stats['role'] == 1 ? '🅰️' : '🅱️')."\n"."⏱ <b>Uptime:</b> <code>{$stats['uptm']}</code>";
                }
                $this->appendReply($user->uid, $text);
            } else {
                $msg = ($res['error']['id'] == 3) ? 'Ничего не найдено' : 'Неизвестное устройство';
                $this->appendReply($user->uid, "❌ ОШИБКА! $msg");
            }
            $this->logAction($user,'ktvsw', $params);

        }
    }
