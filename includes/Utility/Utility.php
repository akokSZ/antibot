<?php

namespace Utility;

// Класс генерирует случайные значения
class GenerateRandomName
{
    private static $_instances = null;

    public static function genKey($length)
    {
        if (is_null(self::$_instances)) {
            self::$_instances = new self();
        }
        return self::$_instances->_genKey($length);
    }

    // Генератор случайных имён
    public static function genFuncName($min = 6, $max = 16)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';
        $name = '';
        if (is_null(self::$_instances)) {
            self::$_instances = new self();
        }

        $length = self::$_instances->_random_int($min, $max);
        for ($i = 0; $i < $length; $i++) {
            $name .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // Имена функций не могут начинаться с цифры, но могут с буквы или _
        if (is_numeric($name[0])) {
            $name = '_' . $name;
        }
        return $name;
    }

    private function _random_int($min, $max)
    {
        if (function_exists('random_int')) {
            return random_int($min, $max);
        }
        return rand($min, $max);
    }

    # альтернатива random_bytes() для PHP < 7.0.0
    private function _random_bytes_php5($length)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($strong === true) {
                return $bytes;
            }
            trigger_error('OpenSSL produced non-strong bytes', E_USER_WARNING);
        }

        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        trigger_error('Used fallback random generator (not cryptographically secure)', E_USER_WARNING);

        return $bytes;
    }
    # генерирует случайный код
    private function _genKey($length = 32)
    {
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $byte =  random_bytes($length);
        } else {
            $byte =  $this->_random_bytes_php5($length);
        }

        return substr(bin2hex($byte), 0, $length);
    }
}
