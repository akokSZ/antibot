<?php

namespace WAFSystem;

class Metrika
{
    private static $_instances = null;
    private $ID = ""; // Код Яндекс Метрики

    public function __construct(WAFSystem $wafsystem)
    {
        if (is_null(self::$_instances))
            self::$_instances = $this;

        $this->ID = $wafsystem->Config->init('main', 'metrika_site', $this->ID, 'Код Яндекс Метрики для tags.js');
    }

    public static function getInstance(WAFSystem $wafsystem)
    {
        if (is_null(self::$_instances))
            self::$_instances = new self($wafsystem);

        return self::$_instances;
    }

    public function get($key)
    {
        return $this->{$key};
    }
}
