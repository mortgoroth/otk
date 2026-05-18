<?php

    namespace App\Telegram\Commands;

    use App\Models\UserLdap;
    use App\Services\Otk\OtkApiService;
    use App\Services\Telegram\DebugController;

    class ElemMagErrorsNewHandler extends BaseHandler {
        public bool $needToStore = true;

        public function __construct (
            protected \App\Services\Telegram\Transport $bot, protected OtkApiService $otk
        ) {
            parent::__construct($bot);
        }

        public function handle (UserLdap $user, array $params):void {
            // 1. Валидация параметров
            if (empty($params)) {
                $this->bot->send($user->uid, "⚠️ ОШИБКА! Элемент не задан.");
                return;
            }

            $elem = trim($params);
            $interval = $params ?? 60;

            // 2. Начало выполнения
            $this->startReply($user->uid, "📊 Поиск ошибок на магистралях в элементе <b>$elem</b> за $interval мин.");

            // 3. Запрос ошибок на A4
            $this->appendReply($user->uid, "\n🔎 <b>На магистральных портах A4:</b>");
            $errors = $this->otk->request("/zabbix/element/$elem/errors/interval/$interval");

            if (isset($errors['result']) && $errors['result'] === false) {
                $this->appendReply($user->uid, "❌ Ошибка: ".($errors['error']['msg'] ?? 'API error'));
            } elseif (empty($errors)) {
                $this->appendReply($user->uid, "   ... не найдено");
            } else {
                foreach ($errors as $errorData) {
                    DebugController::write($errorData, 'ZABBIX_A4_ERROR');
                    $this->appendReply($user->uid, " • <code>{$errorData['host']}</code> / {$errorData['port']} : <b>{$errorData['errors']}</b>");
                }
            }

            // 4. Запрос ошибок на A3
            $this->appendReply($user->uid, "\n🔎 <b>На портах A3 в элемент:</b>");
            $errorsA3 = $this->otk->request("/zabbix/element/$elem/errors/a3/interval/$interval");

            if (isset($errorsA3['result']) && $errorsA3['result'] === false) {
                $this->appendReply($user->uid, "❌ Ошибка: ".($errorsA3['error']['msg'] ?? 'API error'));
            } elseif (empty($errorsA3)) {
                $this->appendReply($user->uid, "    ... не найдено");
            } else {
                $str = '';
                foreach ($errorsA3 as $a3 => $portData) {
                    foreach ($portData as $port => $errCount) {
                        $str .= " • $a3 / $port : <b>$errCount</b>\n";
                    }
                }
                DebugController::write($str, 'ZABBIX_A3_ERROR_STRING');
                $this->appendReply($user->uid, $str);
            }

            $this->appendReply($user->uid, "\n🏁 Поиск ошибок завершен!");
        }
    }
