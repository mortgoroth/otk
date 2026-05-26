<?php

    namespace App\Services\Telegram;

    use App\Traits\Telegram\SendsLongMessages;
    use Illuminate\Support\Facades\Http;

    class Transport {
        use SendsLongMessages;

        protected string $url;
        protected static array $currentKeyboard = [];

        public function __construct () {
            $conf = config('telegram');
            $this->url = "{$conf['api_url']}/bot{$conf['otk_service_bot']['token']}";
        }

        public static function setStaticKeyboard (array $keyboard):void {
            self::$currentKeyboard = $keyboard;
        }

        /**
         * Стандартная отправка с обычной клавиатурой (ReplyKeyboardMarkup)
         */
        public function send (int $chatId, string $text, array $keyboard = [], bool $removeKeyboard = false):int {
            $params = [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ];

            if ($removeKeyboard) {
                // $params['reply_markup'] = ['remove_keyboard' => true];
            } elseif (!empty($keyboard)) {
                $params['reply_markup'] = $this->formatKeyboard($keyboard);
            }
            // Если мы передали пустой массив [] — НИЧЕГО не добавляем.
            // Это и будет наше "чистое" сообщение для редактирования.
            elseif ($keyboard === [] && empty(self::$currentKeyboard)) {
                // ничего
            }

            return $this->executeAndGetId($text, $params);
        }

        /**
         * Отправка сообщения с Inline-кнопками (InlineKeyboardMarkup)
         */
        public function sendInline (int $chatId, string $text, array $buttons):int {
            $params = [
                'chat_id'      => $chatId,
                'text'         => $text,
                'parse_mode'   => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $buttons
                ]
            ];

            $response = Http::post("$this->url/sendMessage", $params);
            return $response->json('result.message_id', 0);
        }

        /**
         * Редактирование сообщения
         */
        public function update (int $chatId, int $messageId, string $text, array $inlineKeyboard = []):int {
            $params = [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ];
            Console::debug("UPDATETEXT: $text");

            // Добавляем Inline-кнопки, если они переданы (например, из SwList)
            if (!empty($inlineKeyboard)) {
                $params['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
            }
            // ВАЖНО: Мы НЕ добавляем сюда Reply-клавиатуру (статику),
            // чтобы не блокировать редактирование в Telegram.

            $response = Http::post("$this->url/editMessageText", $params);
            if ($response->failed()) {
                // Если HTML сломался, пробуем отправить без него, чтобы не висеть
                Console::error("EDIT FAIL [$messageId]: " . $response->body());
                // "План Б": пробуем без HTML, если ошибка в тегах
                $params['text'] = strip_tags($text);
                unset($params['parse_mode']);
                $retryResponse = Http::post("$this->url/editMessageText", $params);
                if ($retryResponse->failed()) {
                    return 0; // Совсем не получилось — BaseHandler пришлет новое сообщение
                }
            }
            return $messageId;
        }

        /**
         * Вспомогательный метод для запуска splitAndSend через трейт
         */
        private function executeAndGetId (string $text, array $params):int {
            if (mb_strlen($text) < 3000) { // Для коротких строк
                $res = Http::post("$this->url/sendMessage", $params);
                return $res->json('result.message_id', 0);
            }

            $id = 0;
            $this->splitAndSend($text, $params, function ($p) use (&$id) {
                $r = Http::post("$this->url/sendMessage", $p);
                $id = $r->json('result.message_id', 0);
            });
            return $id;
        }

        /**
         * Вспомогательный метод для единообразного форматирования ReplyKeyboardMarkup
         */
        private function formatKeyboard (array $keyboard):array {
            return [
                'keyboard'                => $keyboard,
                'resize_keyboard'         => true,
                'one_time_keyboard'       => false,
                'is_persistent'           => true,
                'input_field_placeholder' => '',
            ];
        }

        public function sendPhoto (int $chatId, string $path, string $caption, array $keyboard = []):int {
            // Если это путь к файлу, а не ссылка
            $file = fopen(public_path($path), 'r');

            $params = [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ];

            if (!empty($keyboard)) {
                $params['reply_markup'] = json_encode($this->formatKeyboard($keyboard));
            }

            $response = Http::attach('photo', $file)
                ->post("$this->url/sendPhoto", $params);

            return $response->json('result.message_id', 0);
        }

    }
