<?php

namespace Custom;


class YandexReviews
{
    const API_URL = 'https://api.content.market.yandex.ru/v2/';
    const TOKEN = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXX';
    const CACHE_FILE = '/bitrix/cache/yandex_reviews.json';
    const SHOP_ID = 00000;
    const CACHE_LIFETIME = 3600;

    private static function sendRequest($url)
    {
        $url = self::API_URL.$url;

        $headers = array(
            "Host: api.content.market.yandex.ru",
            "Accept: */*",
            "Authorization: ".self::TOKEN
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($data, true);

        return $data;
    }

    private static function getCache()
    {
        if (\Bitrix\Main\IO\File::isFileExists(\Bitrix\Main\Application::getDocumentRoot().self::CACHE_FILE)) {
            $reviews = json_decode(\Bitrix\Main\IO\File::getFileContents(\Bitrix\Main\Application::getDocumentRoot().self::CACHE_FILE), true);

            return $reviews;
        }

        return false;
    }

    public static function getReviews()
    {

        $lastUpdate = \COption::GetOptionString("custom", "last_yandex_reviews_update", time());

        if (time() - $lastUpdate <= self::CACHE_LIFETIME) {

            \CEventLog::Add(
                array(
                    "SEVERITY"      => "INFO",
                    "AUDIT_TYPE_ID" => "YANDEX_REVIEWS_LOG",
                    "MODULE_ID"     => "custom",
                    "DESCRIPTION"   => "GET YANDEX REVIEWS FROM CACHE",
                )
            );

            return self::getCache();
        }


        $request = self::sendRequest('/shops/'.self::SHOP_ID.'/opinions/?count=30&how=DESC');

        if ($request['status'] !== 'OK') {

            \CEventLog::Add(
                array(
                    "SEVERITY"      => "ERROR",
                    "AUDIT_TYPE_ID" => "YANDEX_REVIEWS_LOG",
                    "MODULE_ID"     => "custom",
                    "DESCRIPTION"   => "GET YANDEX REVIEWS ERROR: ".json_encode($request),
                )
            );

            return self::getCache();
        }

        $reviews = $request['opinions'];

        if(!$reviews || !is_array($reviews)) {
            return false;
        }

        \Bitrix\Main\IO\File::putFileContents(\Bitrix\Main\Application::getDocumentRoot().self::CACHE_FILE, json_encode($reviews));
        \COption::SetOptionString("custom", "last_yandex_reviews_update", time());
        \CEventLog::Add(
            array(
                "SEVERITY"      => "INFO",
                "AUDIT_TYPE_ID" => "YANDEX_REVIEWS_LOG",
                "MODULE_ID"     => "custom",
                "DESCRIPTION"   => "GET YANDEX REVIEWS FROM API",
            )
        );

        return $reviews;
    }
}