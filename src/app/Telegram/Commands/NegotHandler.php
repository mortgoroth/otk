<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class NegotHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметров
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1] ?? 'all';

            // 2. Начало выполнения
            $this->startReply($user->uid, "⚙️ Устанавливаю <b>Negotiation Auto</b> на <code>$swnm</code> (порт: $port)...");

            $negot = $this->otk->request("/tg/$swnm/negot/$port", [], true);

            // 4. Обработка ошибок
            $errorId = $negot['error']['id'] ?? -1;

            if ($errorId !== 0) {
                $errorMsg = match ($errorId) {
                    2 => '❌ ОШИБКА! Некорректное имя коммутатора',
                    3 => '❌ ОШИБКА! Некорректный номер порта',
                    default => '❌ Неизвестная ошибка API',
                };
                $this->appendReply($user->uid, $errorMsg);
                return;
            }

            // 5. Формирование списка портов
            $results = $negot['result']['negot_auto'] ?? [];
            if (empty($results)) {
                $this->appendReply($user->uid, "⚠️ Нет данных по портам.");
                return;
            }

            $reply = "✅ <b>Результат для $swnm:</b>\n";
            foreach ($results as $pnum => $res) {
                $status = $res ? 'готово' : 'ошибка';
                $icon = $res ? '🔹' : '🔸';
                $reply .= "$icon Порт <b>$pnum</b>: <i>$status</i>\n";
            }

            // Обновляем сообщение финальным списком
            $this->appendReply($user->uid, $report ?? $reply);
            $this->logAction($user,'negot', $params);
        }
    }
