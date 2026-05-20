<?php

    namespace App\Services\Telegram;

    use Symfony\Component\Console\Output\ConsoleOutput;

    class Console {
        private static ?ConsoleOutput $output = null;

        protected static function output ():ConsoleOutput {
            if (self::$output === null) {
                self::$output = new ConsoleOutput();
            }
            return self::$output;
        }

        /**
         * Проверка: нужно ли выводить лог в консоль
         */
        private static function shouldLog(): bool {
            $isDebugEnabled = config('services.telegram.debug', false);
            $allowedUids = config('services.telegram.debug_uids', []);
            $currentUid = request()->get('current_tg_uid');

            // 1. Если дебаг включен — логируем вообще всё и всех
            if ($isDebugEnabled) {
                return true;
            }

            // 2. Если дебаг выключен, проверяем: входит ли юзер в список избранных
            // Если список пуст или UID не совпал — молчим
            if (!empty($allowedUids) && in_array($currentUid, $allowedUids)) {
                return true;
            }

            return false;
        }

        public static function info (string $message):void {
            if (!self::shouldLog()) return;
            $time = date('Y-m-d H:i:s');
            self::output()->writeln("<info>[$time]</info> $message");
        }

        public static function error (string $message):void {
            // Ошибки пишем всегда, даже если дебаг выключен,
            $time = date('Y-m-d H:i:s');
            self::output()->writeln("<error>[$time]</error> $message");
        }

        public static function warn (string $message):void {
            if (!self::shouldLog()) return;
            $time = date('Y-m-d H:i:s');
            self::output()->writeln("<comment>[$time]</comment> $message");
        }
    }
