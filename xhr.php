<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) 
        session_start();
}

include "includes/autoload.php";

// Не кешировать
header('Pragma: no-cache');
header('Expires: Thu, 18 Aug 1994 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Инициализация и запуск системы
try {
    $antiBot = new \WAFSystem\WAFSystem();

    $skin = isset($_REQUEST["skin"]) ? $_REQUEST["skin"] : "";
    $skin = preg_replace('/[^a-zA-Z0-9_-]/', '', $skin); // Безопасность

    if (!empty($skin)) {
        $skinsDir = 'skins/';
        $skinFile = $skinsDir . $skin . '.php';

        // Проверка существования файла
        if (file_exists($skinFile) && is_readable($skinFile)) {
            require $skinFile;
            exit;
        } else {
            // Если файл не найден, подключаем файл по умолчанию
            $defaultFile = $skinsDir . 'default.php';
            if (file_exists($defaultFile)) {
                require $defaultFile;
            } else {
                // Если default.php тоже нет, показываем ошибку
                header('HTTP/1.1 404 Not Found');
                echo 'Скин не найден';
            }
            exit;
        }
    }

    header('Content-type: application/json; charset=utf-8');
    $Api = \WAFSystem\Api::getInstance($antiBot);

    // Блокировка плохих запросов
    if (!$Api->isPost()) {
        $antiBot->Logger->log("Not a POST request");
        $antiBot->BlackListIP->add($antiBot->Profile->IP, 'Not a POST request');
        $Api->endJSON('block');
    }

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
            $antiBot->Logger->log("Successfully passed the captcha");
            $antiBot->Marker->set();
            $Api->endJSON('allow');
        }
    }
    $Api->endJSON('refresh');
} catch (Exception $e) {
    error_log("AntiBot system failed: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}
