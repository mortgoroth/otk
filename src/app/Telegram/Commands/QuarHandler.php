<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;

    class QuarHandler extends BaseHandler {

        public function handle (UserLdap $user, array $params):void {
            // 1. Проверка параметра
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Коммутатор не задан.");
                return;
            }

            $swnm = $params[0];

            // Регулярка из кода для проверки формата XXXXX-X[XX]
            if (!preg_match('/^\d{5}-\d{1,3}$/', str_replace(['A4-', 'a4-'], '', $swnm))) {
                $this->bot->send($user->uid, "❌ Неверное имя коммутатора. Формат: XXXXX-X[XX]");
                return;
            }

            // 2. Начало выполнения
            $this->startReply($user->uid, "🧱 Постановка в карантин <code>$swnm</code>...");

            // 3. Запрос к API
            $quarantine = $this->otk->request(
                "/switch/quar/add/$swnm/true",
                [
                    'uname' => $user->username,
                    'host' => $swnm,
                    'node' => true,
                ],
                true
            );

            // 4. Обработка результата
            if (!empty($quarantine['result'])) {
                $report = "";
                foreach ($quarantine['result'] as $sw => $dt) {
                    $status = (($dt['error']['id'] ?? -1) === 0) ? '✅ добавлен в карантин' : '❌ '.($dt['error']['msg'] ?? 'ошибка');

                    $report .= "• $sw: $status\n";
                }
                $this->appendReply($user->uid, $report);
            } else {
                // Если API вернул общую ошибку
                $errorId = $quarantine['error']['id'] ?? 'unknown';
                $errorMsg = $quarantine['error']['msg'] ?? 'неизвестная ошибка';
                $this->appendReply($user->uid, "❌ Ошибка $errorId: $errorMsg");
            }

            // 5. Логируем
            $this->logAction($user, 'quar', $params);
        }
    }
