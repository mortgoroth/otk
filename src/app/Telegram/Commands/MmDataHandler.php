<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class MmDataHandler extends BaseHandler {
        public function handle (UserLdap $user, array $params):void {
            $host = $params ?? null;
            if (!$host) {
                $this->bot->send($user->uid, "⚠️ ММ не задан");
                return;
            }

            $this->startReply($user->uid, "📡 Опрашиваю ММ <code>$host</code>...");
            $res = app(\App\Services\Otk\OtkApiService::class)->request("/tg/mm/data/$host");

            $errorId = $res['error']['id'] ?? -1;
            if ($errorId === 0) {
                $msg = "🏢 <b>{$res['addr']}:</b>\n";
                $msg .= "🔌 Коммутатор: <code>{$res['switch']}/{$res['port']}</code>\n";

                if (!($res['stats']['avail'] ?? false)) {
                    $msg .= "🔴 <b>ММ недоступен</b>";
                } else {
                    $msg .= "📟 Модель: {$res['model']}\n";
                    $msg .= "📉 Вх.сигнал: <b>{$res['stats']['insig']} dB</b>\n";
                    $msg .= "📈 Исх.сигнал: <b>{$res['stats']['outsig']} dB</b>\n";
                    if (!empty($res['stats']['temper']) && $res['stats']['temper'] !== 'null') {
                        $msg .= "🌡 Температура: <b>{$res['stats']['temper']}°C</b>";
                    }
                }
                $this->appendReply($user->uid, $msg);
            } else {
                $msg = match ($errorId) {
                    90 => 'По данному имени ММ не найдены',
                    91 => 'ММ недоступен',
                    92 => 'Неизвестное устройство',
                    default => 'Ошибка API'
                };
                $this->appendReply($user->uid, "❌ ОШИБКА! $msg");
            }
        }
    }
