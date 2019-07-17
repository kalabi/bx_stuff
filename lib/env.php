<?php

namespace Custom;

use \Bitrix\Main\Application;

/**
 * Class Env
 * определение окружения и загрузка его настроек
 * @package Custom
 */
class Env
{

    /**
     * список  хостов
     * @var array
     */
    public $arHosts = [];

    /**
     * текущий хост
     * @var string
     */
    public $currentHost = '';

    /**
     * окружение
     * @var string
     */
    public $env = 'dev';

    /**
     * настройки
     * @var array
     */
    public $settings = [];

    /**
     * Env constructor.
     */
    public function __construct()
    {

        $context = Application::getInstance()->getContext();
        $server = $context->getServer();
        $this->currentHost = $server->getServerName();

    }

    /**
     * @return array
     */
    public function getArHosts()
    {
        return $this->arHosts;
    }

    /**
     * @param array $arHosts
     */
    public function setArHosts($arHosts)
    {
        $this->arHosts = $arHosts;
    }

    /**
     *  загрузка настроек окружения
     */
    public function load()
    {

        if (is_array($this->arHosts) && count($this->arHosts) > 0) {
            $this->env = $this->arHosts[$this->currentHost];
        }
        else {
            $this->arHosts = [
                $this->currentHost => 'dev'
            ];
            $this->env = 'dev';
        }

        Config::setEnv($this->env);

        if (file_exists(__DIR__.'/env/'.$this->env.'.php')) {
            $this->settings = include_once __DIR__.'/env/'.$this->env.'.php';
        }

        $this->loadSettings();
    }

    /**
     *  загрузка констант и кастомных настроек в Config
     */
    private function loadSettings()
    {
        if (is_array($this->settings) && count($this->settings) > 0) {

            // объявление констант
            if (is_array($this->settings['constants']) && count($this->settings['constants']) > 0) {
                foreach ($this->settings['constants'] as $constant => $value) {
                    define($constant, $value);
                }
            }

            // загрузка кастомных параметров
            if (is_array($this->settings['params']) && count($this->settings['params']) > 0) {
                Config::set($this->settings['params']);
            }
        }
    }

}