<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class AkbHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $swname = $params[0] ?? null;
            if (!$swname) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $this->startReply($user->uid, "🔌 Опрашиваю коммутатор <code>$swname</code>...");
            $res = $this->otk->request("/switch/$swname/getdata");

            if (($res['error']['id'] ?? -1) === 0) {
                $rs = $res['result'];
                $isSupported = $rs['battery']['support'] ?? false;

                // Логика определения питания через match
                $powerStatus = match ($rs['power']['status'] ?? null) {
                    1, 3, 4 => 'от сети 🔌',
                    2 => 'от АКБ 🔋',
                    default => 'неизвестно ❓',
                };

                $text = "📍 <b>Адрес:</b> {$rs['addr']}\n"."🌐 <b>IP:</b> <code>{$rs['swip']}</code>\n"."📟 <b>Модель:</b> {$rs['get_type']}\n"."🛠 <b>Поддержка АКБ:</b> ".($isSupported ? 'да' : 'нет')."\n";

                if ($isSupported) {
                    $hasAkb = ($rs['battery']['exists'] ?? false) ? 'есть ✅' : 'нет ❌';
                    $text .= "🔋 <b>АКБ:</b> $hasAkb\n";
                }

                $text .= "⚡️ <b>Питание:</b> $powerStatus";

                $this->appendReply($user->uid, $text);
            } else {
                $this->appendReply($user->uid, "❌ ОШИБКА: ".($res['error']['msg'] ?? 'не удалось получить данные'));
            }
        }
    }
