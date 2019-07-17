<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use \Bitrix\Sale;

/**
 * Class CustomOneClickComponent
 */
class CustomOneClickComponent extends CBitrixComponent
{

    /**
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function getElementsById()
    {

        if (count($this->arResult['ELEMENTS']) === 0) {
            return [];
        }

        \Bitrix\Main\Loader::includeModule('iblock');

        $items = [];
        $params = [
            'select'    => [
                'ID',
                'NAME',
                'CODE',
                'IBLOCK_ID',
                'IBLOCK_SECTION_ID',
                'DETAIL_PAGE_URL',
                'PREVIEW_PICTURE'
            ],
            'filter'    => [
                'IBLOCK_ID' => CATALOG_IBLOCK_ID,
                'ACTIVE'    => 'Y',
                'ID'        => $this->arResult['ELEMENTS'],
            ],
            'order'     => ['id' => 'asc'],
            'nTopCount' => false
        ];

        $res = \CIBlockElement::GetList($params['order'], $params['filter'], false, $params['nTopCount'], $params['select']);
        while ($ob = $res->GetNext()) {
            $items[$ob['ID']] = [
                'NAME'    => $ob['NAME'],
                'PICTURE' => \CFile::GetPath($ob['PREVIEW_PICTURE']),
                'LINK'    => $ob['DETAIL_PAGE_URL'],
            ];
        }

        return $items;
    }

    /**
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\NotImplementedException
     */
    private function getElementsFromBasket()
    {
        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());
        $items = [];
        foreach ($basket as $basketItem) {
            $items[] = $basketItem->getProductId();
        }

        return $items;
    }

    /**
     * @return mixed|void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\NotImplementedException
     */
    public function executeComponent()
    {
        if ($this->arParams['TYPE'] === 'basket') {
            $this->arResult['ELEMENTS'] = $this->getElementsFromBasket();
        } elseif ($this->arParams['TYPE'] === 'product') {
            $this->arResult['ELEMENTS'] = $this->arParams['ELEMENTS'];
        }

        $this->arResult['ITEMS'] = $this->getElementsById();

        $this->includeComponentTemplate();
    }
}

