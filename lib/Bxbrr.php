<?php

namespace Custom;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Sale\Location\Admin\LocationHelper;
use Bitrix\Sale\Location\LocationTable;
use COption;
use Bitrix\Sale;

/**
 * Class Bxbrr
 * @package Custom
 */
class Bxbrr
{

    /**
     *
     */
    const API_URL = 'http://api.boxberry.de/json.php';

    /**
     * @var string
     */
    private $token = 'XXXXXXXXXXXXXXXXXX';

    /**
     * @var array
     */
    public $errors = [];

    /**
     * @var array
     */
    public $log = [];

    /**
     * время жизни кеша запросов
     * @var array
     */
    private $cacheLifetime = [
        'ListCitiesFull'    => 60 * 2,
        'ListCities'        => 60 * 2,
        'ListZips'          => 60 * 60 * 24,
        'ZipCheck'          => 60 * 60 * 24,
        'DeliveryCosts'     => 0,
        'PointsByPostCode'  => 0,
        'PointsDescription' => 60 * 60 * 24,
        'ListPointsShort'   => 60 * 60 * 24,
        'ListPoints'        => 60 * 60 * 24,
    ];
    /**
     * @var
     */
    public $region_bitrix_name;
    /**
     * @var
     */
    public $city_bitrix_name;
    /**
     * @var
     */
    public $city_widget_name;

    /**
     *  минимальный вес
     */
    const MIN_WEIGHT = 1;

    /**
     * путь к логам
     */
    const LOG_PATH = '/bitrix/log/';

    /**
     *  путь к кешу
     */
    const CACHE_PATH = '/bitrix/cache/bxbrr/';

    /**
     *  id ПВЗ отправки
     */
    const START_PVZ_ID  = 75008;

    /**
     * @param $method
     * @param $args
     *
     * @return bool|mixed
     */
    private function sendRequest($method, $args)
    {

        $this->log[] = __METHOD__;
        $this->log[] = $method;

        if ($this->cacheLifetime[$method] !== 0) {

            $cache = $this->checkCache($method.implode('-', $args));

            if ($cache && is_array($cache)) {
                return $cache;
            }
        }

        if ($curl = curl_init()) {

            $url = self::API_URL.'?token='.$this->token.'&method='.$method.'&'.http_build_query($args);

            $this->log[] = $url;

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);

            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

            $errno = curl_errno($curl);
            $error = curl_error($curl);

            if ($errno > 0) {
                $this->errors[$errno] = $error;
            }


            $out = curl_exec($curl);
            curl_close($curl);

            if ($this->cacheLifetime[$method] !== 0) {
                $this->setCache($method.implode('-', $args), $out);
            }

            $this->log[] = $out;

            $this->writeLog();

            return json_decode($out, true);

        }

        return false;
    }

    /**
     * @param $method
     *
     * @return bool|mixed
     */
    private function checkCache($method)
    {
        $this->log[] = __METHOD__;
        $lastUpdate = \COption::GetOptionString("custom", "bxbrr_".$method, time());

        $this->log[] = date('d.m.Y H:i:s', $lastUpdate);
        $this->log[] = date('d.m.Y H:i:s', time());

        if (time() - (int)$lastUpdate < $this->cacheLifetime[$method]) {

            if (\Bitrix\Main\IO\File::isFileExists(\Bitrix\Main\Application::getDocumentRoot().self::CACHE_PATH.$method.'.json')) {
                $this->log[] = 'cache exist and not expired';

                return json_decode(\Bitrix\Main\IO\File::getFileContents(\Bitrix\Main\Application::getDocumentRoot().self::CACHE_PATH.$method.'.json'), true);
            } else {
                $this->log[] = 'cache doesnt exist';

                return false;
            }
        }

        $this->log[] = 'cache doesnt exist or expired';

        return false;
    }

    /**
     * @param $method
     * @param $data
     */
    private function setCache($method, $data)
    {
        $this->log[] = __METHOD__;
        $fn = $method.'.json';

        \Bitrix\Main\IO\File::putFileContents(\Bitrix\Main\Application::getDocumentRoot().$this->cachePath.$fn, ($data));
        \COption::SetOptionString("custom", "bxbrr_".$method, time());
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    private function upString($name)
    {
        return str_replace(GetMessage('yo'), GetMessage('ye'), (LANG_CHARSET == 'windows-1251' ? mb_strtoupper($name, 'CP1251') : mb_strtoupper($name)));
    }

    /**
     * @param $location
     *
     * @return string
     */
    public function GetBitrixRegionNames($location)
    {

        \Bitrix\Main\Loader::includeModule('sale');

        $city_bitrix_name = false;
        $region_bitrix_name = false;
        if (!empty($location)) {
            $parameters = array();
            $parameters['filter']['=CODE'] = $location;
            $parameters['filter']['NAME.LANGUAGE_ID'] = "ru";
            $parameters['limit'] = 1;
            $parameters['select'] = array('*', 'LNAME' => 'NAME.NAME');

            $arVal = LocationTable::getList($parameters)->fetch();
            $fullCityName = LocationHelper::getLocationPathDisplay($location);

            if ($arVal && strlen($arVal['LNAME']) > 0) {
                $val = $arVal['LNAME'];
                $this->city_bitrix_name = $this->upString($val);
                $this->region_bitrix_name = $this->upString($fullCityName);
                $city_widget_name = explode(",", $region_bitrix_name);
                $city_widget_name = array_reverse($city_widget_name);
                $this->city_widget_name = $city_widget_name[0].' '.(strpos(
                        $city_widget_name[1],
                        GetMessage("BOXBERRY_REGION")
                    ) !== false ? $city_widget_name[1] : '');

                return $this->city_widget_name;
            }

        }
    }

    /**
     * @param      $city_bitrix_name
     * @param bool $region_bitrix_name
     *
     * @return bool|mixed
     */
    public function GetCityCode($city_bitrix_name, $region_bitrix_name = false)
    {
        $boxberry_list = $this->sendRequest('ListCities', []);
        $i = 0;

        foreach ($boxberry_list as $boxberry_cities) {
            $city_name = self::upString($boxberry_cities['Name']);
            $region_name = self::upString($boxberry_cities['Region']);
            $boxberry_city[$i]['Name'] = $city_name;
            $boxberry_city[$i]['Region'] = $region_name;
            $boxberry_city[$i]['Code'] = $boxberry_cities['Code'];
            $i++;

            if (strpos($this->region_bitrix_name, $region_name) !== false) {
                if (strpos($this->city_bitrix_name, $city_name) !== false) {
                    return $boxberry_cities["Code"];
                }
            }
        }
        foreach ($boxberry_city as $cities) {
            if (strpos($this->city_bitrix_name, $cities['Name']) !== false) {
                return $cities["Code"];
            }
        }

        return false;
    }

    /**
     * @param $arOrder
     * @param $arConfig
     *
     * @return mixed
     */
    private function getFullDimensions($arOrder, $arConfig)
    {
        $weight_default = 10;
        if (count($arOrder["ITEMS"]) == 1 && $arOrder["ITEMS"][0]["QUANTITY"] == 1) {
            $multiplier = 10;
            $full_package["width"] = $arOrder["ITEMS"][0]["DIMENSIONS"]["WIDTH"] / $multiplier;
            $full_package["height"] = $arOrder["ITEMS"][0]["DIMENSIONS"]["HEIGHT"] / $multiplier;
            $full_package["length"] = $arOrder["ITEMS"][0]["DIMENSIONS"]["LENGTH"] / $multiplier;
            $full_package["weight"] = ($arOrder["ITEMS"][0]['WEIGHT'] == '0.00' || (float)$arOrder["ITEMS"][0]['WEIGHT'] < (float)self::MIN_WEIGHT ? $weight_default : $arOrder["ITEMS"][0]['WEIGHT']);
        } else {
            $full_package["width"] = 0;
            $full_package["height"] = 0;
            $full_package["length"] = 0;
            $full_package["weight"] = 0;

            foreach ($arOrder["ITEMS"] as $item) {
                $full_package["weight"] += $item["QUANTITY"] * ($item['WEIGHT'] == '0.00' || $item['WEIGHT'] < (float)self::MIN_WEIGHT ? $weight_default : $item['WEIGHT']);
            }
        }

        return $full_package;
    }

    /**
     * @param Sale\Order $order
     *
     * @return array
     */
    public function getOrderData(\Bitrix\Sale\Order $order)
    {
        if (!Loader::includeModule('sale')) {
            $this->errors[] = 'No sale module';
        };

        if (!Loader::includeModule('catalog')) {
            $this->errors[] = 'No catalog module';
        };

        $server = \Bitrix\Main\Application::getInstance()->getContext()->getServer();

        $data = [
            'ordersum'    => $order->getPrice(),
            'sucrh'       => 1,
            'targetstart' => self::START_PVZ_ID,
            'paysum'      => 0,
            'version'     => '2.2',
            'cms'         => 'bitrix',
            'url'         => $server->getHttpHost()
        ];

        $arOrder = [];
        $items = [];
        $ids = [];

        $basket = $order->getBasket();

        foreach ($basket as $basketItem) {

            $ar_res = \CCatalogProduct::GetByID($basketItem->getProductId());
            $ids[] = $basketItem->getProductId();

            $items[] = [
                'WEIGHT'     => $basketItem->getWeight(),
                'DIMENSIONS' => [
                    'HEIGHT' => $ar_res['HEIGHT'],
                    'WIDTH'  => $ar_res['WIDTH'],
                    'LENGTH' => $ar_res['LENGTH'],
                ],
                'QUANTITY'   => $basketItem->getQuantity(),
            ];
        }

        $arOrder['ITEMS'] = $items;

        $package = $this->getFullDimensions($arOrder, []);

        $data = array_merge($data, $package);

        $this->log[] = $ids;
        $this->log[] = $arOrder['ITEMS'];
        $this->log[] = $data;

        return $data;
    }

    /**
     * @return bool|mixed
     */
    public function getListCitiesFull()
    {
        $this->log[] = __METHOD__;

        return $this->sendRequest('ListCitiesFull', []);
    }

    /**
     * @param $cityCode
     *
     * @return bool|mixed
     */
    public function getListPointsShort($cityCode)
    {
        $this->log[] = __METHOD__;

        return $this->sendRequest('ListPointsShort', ['CityCode' => $cityCode, 'prepaid' => 1]);
    }

    /**
     * @param $cityCode
     *
     * @return bool|mixed
     */
    public function getListPoints($cityCode)
    {
        $this->log[] = __METHOD__;

        return $this->sendRequest('ListPoints', ['CityCode' => $cityCode, 'prepaid' => 1]);
    }

    /**
     * @return bool|mixed
     */
    public function getListZips()
    {
        $this->log[] = __METHOD__;

        return $this->sendRequest('ListZips', []);
    }

    /**
     * @param $data
     *
     * @return bool|mixed
     */
    public function getDeliveryCosts($data)
    {
        $this->log[] = __METHOD__;

        return $this->sendRequest('DeliveryCosts', $data);
    }

    /**
     *
     */
    public function writeLog()
    {
        $f = fopen(\Bitrix\Main\Application::getDocumentRoot().self::LOG_PATH."/log_".date('d.m.Y').".log", "ab+");
        fwrite($f, '----- '.date('d.m.Y H:i:s').' -------'.PHP_EOL);
        foreach ($this->log as $k => $item) {
            if (is_null($item) || !$item) {
                continue;
            }

            if (is_array($item)) {

                if (count($item) > 10) {
                    continue;
                }

                fwrite($f, $k.' = '.json_encode($item).PHP_EOL);
            } else {
                fwrite($f, $k.' = '.$item.PHP_EOL);
            }
        }
        fwrite($f, '-------------------'.PHP_EOL);
        fclose($f);
    }
}