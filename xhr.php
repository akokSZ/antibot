<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent())
        session_start();
}

include "includes/autoload.php";

function verifyTurnstileToken($secretKey, $token, $remoteIp = '')
{
    if ($secretKey === '' || $token === '') {
        return false;
    }

    $payload = [
        'secret' => $secretKey,
        'response' => $token
    ];

    if (!empty($remoteIp)) {
        $payload['remoteip'] = $remoteIp;
    }

    $postData = http_build_query($payload);
    $responseBody = false;

    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        $responseBody = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 8
            ]
        ]);
        $responseBody = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    }

    if ($responseBody === false) {
        return false;
    }

    $decoded = json_decode($responseBody, true);
    return is_array($decoded) && isset($decoded['success']) && $decoded['success'] === true;
}

// Не кешировать
header('Pragma: no-cache');
header('Expires: Thu, 18 Aug 1994 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Инициализация и запуск системы
try {
    $antiBot = \WAFSystem\WAFSystem::getInstance();

    $skin = isset($_REQUEST["skin"]) ? $_REQUEST["skin"] : "";
    $skin = preg_replace('/[^a-zA-Z0-9_-]/', '', $skin); // Безопасность

    if (!empty($skin)) {
        // Заменяем CSFR
        $csfr = \WAFSystem\CSRF::getInstance($antiBot);
        try {
            if (!isset($_REQUEST["csrf"]))
                throw new \Exception('Error: _REQUEST[csrf] is not set');

            $isCSRF = $csfr->validCSRF($_REQUEST["csrf"]);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $antiBot->Logger->log($message, ["_REQUEST" => $_REQUEST]);
            $antiBot->GrayList->add($antiBot->Profile->IP, $message);
            $antiBot->Template->showBlockPage();
        }

        $_REQUEST["csrf"] = $csfr->createCSRF(); // выдем новый, т.к. страный удален

        $skinsDir = 'skins/';
        $skinFile = $skinsDir . $skin . '.php';

        // Проверка существования файла
        if (file_exists($skinFile) && is_readable($skinFile)) {
            require $skinFile;
            exit;
        } else {
            // Если файл не найден, подключаем файл по умолчанию
            $defaultFile = $skinsDir . 'checkbox.php';
            if (file_exists($defaultFile)) {
                require $defaultFile;
            } else {
                // Если checkbox.php тоже нет, показываем ошибку
                header('HTTP/1.1 404 Not Found');
                echo 'Скин не найден';
            }
            exit;
        }
    }

    $Api = \WAFSystem\Api::getInstance($antiBot);
    $data = $Api->getData();

    // Проверка браузера
    if (isset($data['func'])) {
        if ($data['func'] == "checks") {
            $antiBot->Logger->rotateIfNeeded(); // ротация логов, пока пользователь ожидает проверку
            new \WAFSystem\SysUpdate($antiBot->Config, $antiBot->Logger); // Обновляем систему пока проходит проверка на роботность
            $antiBot->isAllowed2($Api);
        }

        // Запрос на установку метки
        else if ($data['func'] == $antiBot->Marker->getNameMarker() && $Api->isHiddenValue()) {
            $captchaType = $antiBot->Config->get('main', 'captcha_type', 'checkbox');
            if ($captchaType === 'turnstile') {
                $turnstileToken = '';
                if (isset($data['turnstile_token'])) {
                    $turnstileToken = trim((string)$data['turnstile_token']);
                } elseif (isset($data['turnstileToken'])) {
                    $turnstileToken = trim((string)$data['turnstileToken']);
                } elseif (isset($data['cf-turnstile-response'])) {
                    $turnstileToken = trim((string)$data['cf-turnstile-response']);
                }

                $turnstileSecretKey = trim((string)$antiBot->Config->init('turnstile', 'secret_key', '', 'Secret key Cloudflare Turnstile (server-side verification)'));
                if (!verifyTurnstileToken($turnstileSecretKey, $turnstileToken, $antiBot->Profile->IP)) {
                    $antiBot->Logger->log("Turnstile server-side verification failed");
                    $Api->endJSON('fail', ['message' => 'turnstile_verification_failed']);
                }
            }

            $antiBot->Logger->log("Successfully passed the captcha");
            $antiBot->Marker->set();
            $Api->endJSON('allow');
        }
    }
    $Api->endJSON('fail', ["message" => "`" . $data['func'] . "` not found"]);
} catch (Exception $e) {
    error_log("AWAF system failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}
