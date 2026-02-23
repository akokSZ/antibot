<?php

namespace WAFSystem;

class CSRF
{
    private static $_instances = null;
    private $csrf_token_key = 'csrf_tokens'; // название массива токенов
    private $expireTime = 600; // время жизни токена
    private $tokenPattern = '/^[a-f0-9]{64}$/'; // Шаблон валидации токена (64 hex-символа)
    private $SESSION;

    private function __construct(WAFSystem $wafsystem)
    {
        $this->SESSION = $wafsystem->SESSION;

        if ($this->SESSION->isStarted()) {
            throw new \RuntimeException('Session is not active');
        }

        if (!isset($this->SESSION[$this->csrf_token_key])) {
            $this->SESSION[$this->csrf_token_key] = [];
        }
    }

    public static function getInstance(WAFSystem $wafsystem)
    {
        if (is_null(self::$_instances))
            self::$_instances = new self($wafsystem);

        return self::$_instances;
    }

    public function createCSRF()
    {
        $this->cleanExpiredTokens(); // Очищаем устаревшие токены
        $token = $this->genKey();
        $this->SESSION[$this->csrf_token_key][$token] = [
            'expire' => time() + $this->expireTime,
            'session_id' => session_id(),
            'created_at' => time()
        ];

        return $token;
    }

    public function validCSRF($csrf_token, $requestMethod = 'POST')
    {
        $this->checkSessionAndCookies();

        if (strtoupper($requestMethod) === 'GET') {
            throw new \Exception('CSRF-tokens should not be used in GET requests');
        }

        // Проверка формата (64 hex-символа для 32 байт)
        if (!preg_match($this->tokenPattern, $csrf_token)) {
            throw new \Exception('Error: Invalid CSRF-token format');
        }

        if (!isset($this->SESSION[$this->csrf_token_key][$csrf_token])) {
            throw new \Exception('Error: CSRF-token not found. Possible reasons: cookies disabled, session expired, or token already used');
        }

        $tokenData = $this->SESSION[$this->csrf_token_key][$csrf_token];

        if ($tokenData['expire'] < time()) {
            unset($this->SESSION[$this->csrf_token_key][$csrf_token]);
            throw new \Exception('Error: CSRF-token expired');
        }

        if ($tokenData['session_id'] !== session_id()) {
            unset($this->SESSION[$this->csrf_token_key][$csrf_token]);
            throw new \Exception('Error: Session mismatch. Possible session hijacking attempt');
        }

        // Удаляем токен после успешной проверки
        unset($this->SESSION[$this->csrf_token_key][$csrf_token]);
        return true;
    }

    /**
     * Удаляет CSRF-токен
     * Возвращает true в случае успеха
     */
    public function removeCSRF($csrf_token)
    {
        if (!preg_match($this->tokenPattern, $csrf_token)) {
            throw new \InvalidArgumentException('Invalid token format');
        }

        unset($this->SESSION[$this->csrf_token_key][$csrf_token]);
        return true;
    }

    /**
     * Возвращает все активные CSRF-токены (только для отладки)
     */
    public function debugGetTokens()
    {
        $this->cleanExpiredTokens();
        return $this->SESSION[$this->csrf_token_key];
    }

    # альтернатива random_bytes() для PHP < 7.0.0
    private function random_bytes_php5($length)
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
    private function genKey()
    {
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $byte =  random_bytes(32);
        } else {
            $byte =  $this->random_bytes_php5(32);
        }

        return bin2hex($byte);
    }

    /**
     * Очищает устаревшие токены
     */
    private function cleanExpiredTokens()
    {
        foreach ($this->SESSION[$this->csrf_token_key] as $token => $data) {
            if ($data['expire'] < time()) {
                unset($this->SESSION[$this->csrf_token_key][$token]);
            }
        }
    }

    /**
     * Проверяет активность сессии и поддержку кук
     * @throws \RuntimeException
     */
    private function checkSessionAndCookies()
    {
        // Проверка активности сессии
        if (!$this->SESSION->isStarted()) {
            throw new \RuntimeException('Session is not active');
        }

        // Проверка поддержки кук
        $cookieName = $this->SESSION->session_name();
        if (empty($_COOKIE[$cookieName])) {
            throw new \RuntimeException('Cookies are disabled, or session cookie not set');
        }
    }
}
