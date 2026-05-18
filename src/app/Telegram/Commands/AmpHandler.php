<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;

    class AmpHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Усилитель не задан.");
                return;
            }

            $ampName = $params[0];

            // 2. Начало опроса
            $this->startReply($user->uid, "🔌 Опрашиваю усилитель <code>$ampName</code>...");

            // 3. Запрос к API
            $res = $this->otk->request("/ktv/amp/$ampName/stats");

            // 4. Обработка ошибок
            $errorId = $res['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    1 => '❌ ОШИБКА! Некорректное имя устройства',
                    2 => '❌ ОШИБКА! Устройство не найдено',
                    3 => '⚠️ Хост недоступен',
                    4 => '❌ ОШИБКА! Порт не задан или неправильный',
                    91 => '❌ ОШИБКА! Усилитель не найден',
                    92 => '⚠️ Усилитель недоступен',
                    default => '❌ Неизвестная ошибка API',
                };
                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Формирование отчета
            $rs = $res['stats'];
            $insigB = !empty($rs['insig_B']) ? $rs['insig_B']." dB" : "—";

            $report = "📡 <b>Усилитель $ampName:</b>\n"."🏠 Адрес: <i>{$res['addr']}</i>\n"."📉 Вх.сигнал А: <b>{$rs['insig_A']} dB</b>\n"."📉 Вх.сигнал B: <b>$insigB</b>\n"."📈 Исх.сигнал: <b>{$rs['outsig']} dB</b>\n"."⏱ Uptime: <code>{$rs['uptm']}</code>";

            $this->appendReply($user->uid, $report);
        }
    }
