<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) 
        session_start();
}

$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

if (
    pathinfo($request_uri, PATHINFO_FILENAME) != 'xhr'
    && !isset($isInclude)
) { // нужно для совместимости с подключением через .htaccess
    $isInclude = true; // блокирует повторное подключение (совместимость с подключением через .htaccess)

    include "includes/autoload.php";

    // Инициализация и запуск системы
    try {
        $antiBot = new \WAFSystem\WAFSystem();
        if ($antiBot->enabled) {
            $antiBot->IFrameChecker->HeaderBlock(); // блокировка отображения в IFrame

            if (isset($_GET['awafblock'])) // переменная блокировки через JS
                $antiBot->Template->showBlockPage();

            $antiBot->run();
        }
    } catch (Exception $e) {
        error_log("AntiBot system failed: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }
}
