<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\Fuser,
    Bitrix\Sale\PaySystem;
use Bitrix\Main\Loader;

global $USER;

Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");

function CreatePasswordForAutologin()
{
    $defGroup = COption::GetOptionString("main", "new_user_registration_def_group", "");
    if($defGroup != "")
    {
        $arGroupID = explode(",", $defGroup);
        $arPolicy = CUser::GetGroupPolicy($arGroupID);
    }
    else
    {
        $arPolicy = CUser::GetGroupPolicy(array());
    }

    $passwordMinLength = intval($arPolicy["PASSWORD_LENGTH"]);
    if($passwordMinLength <= 0)
        $passwordMinLength = 6;
    $passwordChars = array(
        "abcdefghijklnmopqrstuvwxyz",
        "ABCDEFGHIJKLNMOPQRSTUVWXYZ",
        "0123456789",
    );
    if($arPolicy["PASSWORD_PUNCTUATION"] === "Y")
        $passwordChars[] = ",.<>/?;:'\"[]{}\|`~!@#\$%^&*()-_+=";

    return randString($passwordMinLength + 2, $passwordChars);
}

$request = Context::getCurrent()->getRequest();
$products = $request['elements'];
$type = $request['type'];
$comment = $request["comment"];
$phone = $request["phone"];
$name = $request["fio"];
$email = $request['email'];

$password = CreatePasswordForAutologin();

$arParams['GROUP_ID'] = explode(",", COption::GetOptionString("main", "new_user_registration_def_group", GROUP_ALL_USERS));
$arParams['PASSWORD'] = $password;
$arParams['CONFIRM_PASSWORD'] = $password;
$arParams['NAME'] = $name;
$arParams['EMAIL'] = $email;
$arParams['PERSONAL_PHONE'] = $phone;
$arParams['LOGIN'] = "buyer".time().GetRandomCode(5);

global $USER;

if(!$USER->isAuthorized()) {
    $user = new CUser;

    $userID = $user->Add($arParams);

    if (!empty($user->LAST_ERROR)) {
        $errorMessage = $user->LAST_ERROR;
        CEvent::SendImmediate("ERROR_MESSAGE", SITE_ID, array("MESSAGE" => $user->LAST_ERROR, "FILE" => __FILE__, "LINE" => __LINE__));
        echo json_encode([
            'result' => $user->LAST_ERROR,
        ]);
        die();
    } else {
        $arResult = CUser::SendPassword($arParams["LOGIN"], $arParams["EMAIL"], SITE_ID);
        $user->Authorize($userID);
    }
}

$siteId = Context::getCurrent()->getSite();
$currencyCode = CurrencyManager::getBaseCurrency();

$order = Order::create($siteId, $USER->isAuthorized() ? $USER->GetID() : $userID);
$order->setPersonTypeId(1);
$order->setField('CURRENCY', $currencyCode);


if($type === 'basket') {
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(\Bitrix\Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());
    $order->setBasket($basket);
} elseif($type === 'product') {
    $basket = Basket::create($siteId);
    foreach ($products as $k => $productId) {
        $item = $basket->createItem('catalog', $productId);
        $itemData = \CIBlockElement::GetByID($productId)->Fetch();
        $item->setFields(array(
            'QUANTITY' => 1,
            'CURRENCY' => $currencyCode,
            'LID' => $siteId,
            'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
            'CATALOG_XML_ID' => $itemData['IBLOCK_EXTERNAL_ID'],
            'PRODUCT_XML_ID' => $itemData['EXTERNAL_ID'],
        ));
    }
    $order->setBasket($basket);
}

$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem();
$service = Delivery\Services\Manager::getById(1);
$shipment->setFields(array(
    'DELIVERY_ID' => $service['ID'],
    'DELIVERY_NAME' => $service['NAME'],
));

$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem();
$paySystemService = PaySystem\Manager::getObjectById(1);
$payment->setFields(array(
    'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
    'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
));

// Устанавливаем свойства
$propertyCollection = $order->getPropertyCollection();

$ar = $propertyCollection->getArray();

$phoneProp = $propertyCollection->getPhone();
$phoneProp->setValue($phone);

$namePropValue = $propertyCollection->getItemByOrderPropertyId(1);
$namePropValue->setValue($name);


$emailProp = $propertyCollection->getUserEmail();
$emailProp->setValue($email);

$order->setField('USER_DESCRIPTION', 'Заказ в 1 клик - '.$comment);

/** @var \Bitrix\Sale\PropertyValue $propertyItem */
foreach ($propertyCollection as $propertyItem)
{
    if($propertyItem->getField("CODE") == "PHONE") {
        $result = $propertyItem->setField("VALUE", $phone);
    }

    if($propertyItem->getField("CODE") == "FIO") {
        $result = $propertyItem->setField("VALUE", $name);
    }
}

// Сохраняем
$order->doFinalAction(true);
$result = $order->save();
$orderId = $order->getId();


if($orderId > 0) {
    echo json_encode([
        'result' => 'ok',
        'order_id' => $orderId
    ]);
} else {
    echo json_encode([
        'result'    => 'Ошибка оформления заказа, повторите попытку позже. Если ошибка повторяется, для оформления заказа, пожалуйста, свяжитесь с менеджером по телефону',
    ]);
}