<?php

namespace WAFSystem;

class FPSChecker
{
    public $action = 'SKIP';
    public $enabled = false;

    private $Logger;
    private $modulName = 'fps_checker';
    private $fps = 30;

    public function __construct(Config $config, Logger $logger)
    {
        $this->Logger = $logger;
        $this->enabled = $config->init($this->modulName, 'enabled', $this->enabled, 'проверка FPS девайсов');
        $this->action = $config->init($this->modulName, 'action', $this->action, 'CAPTCHA - капча, BLOCK - заблокировать, SKIP - ничего не делать');
        $this->fps = $config->init($this->modulName, 'fps', $this->fps, 'минимальная частота кадров');
    }

    public function Checking($fps)
    {
            if ($fps < $this->fps) {
                $this->Logger->log("FPS {$fps} is less than {$this->fps}");
                return true;
            }

        return false;
    }
}
