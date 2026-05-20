<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Telegram\DebugController;

    class CostHandler extends BaseHandler {
        public bool $needToStore = true;

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметров
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Порт не задан.");
                return;
            }
            if (!isset($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Стоимость порта не задана.");
                return;
            }

            $swnm = $params[0];
            $port = $params[1];
            $cost = (int) $params;

            DebugController::write("swnm: $swnm, port: $port, cost: $cost", 'COST_DEBUG');

            // 2. Валидация стоимости (бизнес-логика STP)
            if ($cost !== 19 && $cost !== 2000) {
                $this->bot->send($user->uid, "❌ Стоимость может быть либо <b>19</b>, либо <b>2000</b>.");
                return;
            }

            $this->startReply($user->uid, "📐 Устанавливаю стоимость <b>$cost</b> на <code>$swnm / $port</code>...");

            // 3. Запрос к API
            $res = $this->otk->request(
                '/switch/stp/cost',
                [
                    'host' => $swnm,
                    'port' => $port,
                    'cost' => $cost
                ],
                true
            );

            DebugController::write($res, 'COST_API_RESPONSE');

            // 4. Обработка результата через match
            $errorId = $res['error']['id'] ?? -1;

            if ($errorId === 0 && ($res['result'] ?? false)) {
                $msg = "✅ Стоимость <b>$cost</b> успешно установлена на <code>$swnm / $port</code>.";
                $this->appendReply($user->uid, $msg);
                $this->alert("установил стоимость $cost на $swnm / $port", $user);
            } else {
                $errorMsg = match ($errorId) {
                    0 => $res['error']['msg'] ?? 'Ошибка выполнения',
                    322 => '❌ Некорректное имя коммутатора',
                    323 => '❌ Некорректный порт',
                    324 => '❌ Некорректная стоимость',
                    default => '❌ Ошибка установки стоимости порта'
                };
                $this->appendReply($user->uid, $errorMsg);
            }
            $this->logAction($user,'cost', $params);

        }
    }
