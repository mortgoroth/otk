<?php

    namespace App\Traits\Telegram;

    trait SendsLongMessages {
        protected function splitAndSend (int $chatId, string $text, array $params, callable $sendMethod):void {
            // Telegram лимит 4096, но с учетом разметки 3000 — безопасный порог
            $parts = explode('<br>', wordwrap($text, 3000, '<br>'));
            $lastIndex = count($parts) - 1;

            foreach ($parts as $index => $part) {
                $currentParams = $params;
                $currentParams['text'] = $part;

                // Клавиатуру прикрепляем только к последней части сообщения
                if ($index !== $lastIndex) {
                    unset($currentParams['reply_markup']);
                }

                $sendMethod($currentParams);
            }
        }
    }
