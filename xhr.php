<?php
session_start();
include "includes/autoload.php";

// Инициализация и запуск системы
try {
    $antiBot = new \WAFSystem\WAFSystem();

    $skin = isset($_REQUEST["skin"]) ? $_REQUEST["skin"] : "";
    // Отображение капчи
    if (!empty($skin)) {
        switch ($skin) {
            case "default":
                require "skins/default.php";
        }
        exit;
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
    $Api->endJSON('block');
} catch (Exception $e) {
    error_log("AntiBot system failed: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}
