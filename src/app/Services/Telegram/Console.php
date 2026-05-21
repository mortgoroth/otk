<?php

    namespace App\Services\Telegram;

    use Illuminate\Container\EntryNotFoundException;
    use Illuminate\Contracts\Container\CircularDependencyException;
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\NotFoundExceptionInterface;
    use Symfony\Component\Console\Formatter\OutputFormatterStyle;
    use Symfony\Component\Console\Output\ConsoleOutput;

    class Console {
        private static ?ConsoleOutput $output = null;

        protected static function output ():ConsoleOutput {
            if (self::$output === null) {
                self::$output = new ConsoleOutput();
                // Регистрируем оранжевый стиль (цвет 208 в 256-цветовой палитре)
                $orange = new OutputFormatterStyle('#ff8700');
                self::$output->getFormatter()->setStyle('debug', $orange);
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
            if ($isDebugEnabled) return true;
            // 2. Если дебаг выключен, проверяем: входит ли юзер в список избранных
            // Если список пуст или UID не совпал — молчим
            if (!empty($allowedUids) && in_array($currentUid, $allowedUids)) return true;

            return app()->runningInConsole() && !request()->has('current_tg_uid');
        }

        /**
         * @throws CircularDependencyException
         * @throws EntryNotFoundException
         * @throws NotFoundExceptionInterface
         * @throws ContainerExceptionInterface
         */
        private static function write(string $message, string $tag, bool $force = false): void {
            if (!$force && !self::shouldLog()) return;

            $time = date('Y-m-d H:i:s');
            $uid = request()->get('current_tg_uid');
            $prefix = $uid ? " [UID: $uid]" : "";

            self::output()->writeln("<$tag>[$time]</$tag>$prefix $message");
        }

        public static function info(string $message): void {
            self::write($message, 'info'); // Зеленый
        }

        public static function warn(string $message): void {
            self::write($message, 'comment'); // Желтый
        }

        /**
         * Оранжевый таймстамп для глубокого дебага
         */
        public static function debug(string $message): void {
            self::write($message, 'debug'); // Оранжевый
        }

        public static function error(string $message): void {
            self::write($message, 'error', true); // Белый на красном (пишем всегда)
        }

    }
