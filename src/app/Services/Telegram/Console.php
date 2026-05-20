<?php

    namespace App\Services\Telegram;

    use Symfony\Component\Console\Output\ConsoleOutput;

    class Console {
        private static ?ConsoleOutput $output = null;

        /**
         * Инициализация вывода (Singleton)
         */
        protected static function output ():ConsoleOutput {
            if (self::$output === null) {
                self::$output = new ConsoleOutput();
            }
            return self::$output;
        }

        /**
         * Зеленый текст (успех/инфо)
         */
        public static function info (string $message):void {
            $time = date('Y-m-d H:i:s');
            self::output()
                ->writeln("<info>[$time]</info> $message");
        }

        /**
         * Красный текст (ошибки)
         */
        public static function error (string $message):void {
            $time = date('Y-m-d H:i:s');
            self::output()
                ->writeln("<error>[$time]</error> $message");
        }

        /**
         * Желтый текст (предупреждения)
         */
        public static function warn (string $message):void {
            $time = date('Y-m-d H:i:s');
            self::output()
                ->writeln("<comment>[$time]</comment> $message");
        }

        /**
         * Обычный текст (дебаг)
         */
        public static function line (string $message):void {
            $time = date('Y-m-d H:i:s');
            self::output()
                ->writeln("[$time] $message");
        }
    }
