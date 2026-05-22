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
                $params['reply_markup'] = [
                    'remove_keyboard' => true
                ];
            } elseif (!empty($keyboard)) {
                $params['reply_markup'] = $this->formatKeyboard($keyboard);
            } elseif (!empty(self::$currentKeyboard)) {
                $params['reply_markup'] = $this->formatKeyboard(self::$currentKeyboard);
            }

            return $this->executeAndGetId($chatId, $text, $params);
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

            if (!empty($inlineKeyboard)) {
                $params['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
            }

            $response = Http::post("$this->url/editMessageText", $params);
            return $response->json('result.message_id', $messageId);
        }

        /**
         * Вспомогательный метод для запуска splitAndSend через трейт
         */
        private function executeAndGetId (int $chatId, string $text, array $params):int {
            $messageId = 0;
            $this->splitAndSend($chatId, $text, $params, function ($finalParams) use (&$messageId) {
                $response = Http::post("$this->url/sendMessage", $finalParams);
                $messageId = $response->json('result.message_id', 0);
                if ($response->failed()) {
                    Console::error("TELEGRAM FAIL: " . $response->body());
                }
            });
            return $messageId;
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

    }
