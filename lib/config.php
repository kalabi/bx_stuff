<?php

namespace Custom;

/**
 * Class Config
 * @package Custom
 */
class Config
{

    /**
     * @var
     */
    public static $settings;
    /**
     * @var
     */
    public static $env;

    /**
     * @return mixed
     */
    public static function getEnv()
    {
        return self::$env;
    }

    /**
     * @param $settings
     */
    public static function set($settings)
    {
        self::$settings = $settings;
    }

    /**
     * @param $param
     *
     * @return mixed
     */
    public static function get($param)
    {
        return self::$settings[$param];
    }

    /**
     * @param $env
     */
    public static function setEnv($env)
    {
        self::$env = $env;
    }
}