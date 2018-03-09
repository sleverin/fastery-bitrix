<?php
use Bitrix\Main\Localization\Loc;

if (!class_exists('CDeliveryFasteryShipping')) {
    Loc::loadMessages(__FILE__);
    CModule::IncludeModule('fastery.shipping');
    CModule::IncludeModule('sale');
    CModule::IncludeModule('catalog');

    class CDeliveryFasteryShipping
    {
        public static $calculationUrl = '/api/delivery/calculate';
        public static $orderUrl = '/api/order/create';
        public static $updateUrl = '/api/order/update/';
        public static $moduleId = 'fastery.shipping';

        /**
         * @return array
         */
        public function Init()
        {
            return array(
                'SID' => 'FasteryModule',
                'NAME' => Loc::getMessage('NAME'),
                'DESCRIPTION' => Loc::getMessage('DESCRIPTION'),
                'DESCRIPTION_INNER' => '',
                'BASE_CURRENCY' => 'RUB',
                'HANDLER' => '/bitrix/modules/fastery.shipping/classes/module.php',
                'DBGETSETTINGS' => array(__CLASS__, 'GetSettings'),
                'DBSETSETTINGS' => array(__CLASS__, 'SetSettings'),
                'COMPABILITY' => array(__CLASS__, 'Compability'),
                'CALCULATOR' => array(__CLASS__, 'Calculate'),
                'PROFILES' => array(
                    'courier' => array(
                        'TITLE' => Loc::getMessage('COURIER'),
                        'DESCRIPTION' => Loc::getMessage('COURIER_DESCIRPTION'),
                        'RESTRICTIONS_WEIGHT' => array(0),
                        'LOGOTIP' => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/fastery.shipping/asset/fastery-courier.jpg'),
                        'RESTRICTIONS_SUM' => array(0)
                    ),
                    'fastest' => array(
                        'TITLE' => Loc::getMessage('FASTEST'),
                        'DESCRIPTION' => Loc::getMessage('FASTEST_DESCIRPTION'),
                        'RESTRICTIONS_WEIGHT' => array(0),
                        'LOGOTIP' => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/fastery.shipping/asset/fastery-courier.jpg'),
                        'RESTRICTIONS_SUM' => array(0)
                    ),
                    'pvz' => array(
                        'TITLE' => Loc::getMessage('PVZ'),
                        'DESCRIPTION' => Loc::getMessage('PVZ_DESCRIPTION'),
                        'RESTRICTIONS_WEIGHT' => array(0),
                        'LOGOTIP' => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/fastery.shipping/asset/fastery-pvz.jpg'),
                        'RESTRICTIONS_SUM' => array(0)
                    ),
                    'terminal' => array(
                        'TITLE' => Loc::getMessage('TERMINAL'),
                        'DESCRIPTION' => Loc::getMessage('TERMINAL_DESCRIPTION'),
                        'RESTRICTIONS_WEIGHT' => array(0),
                        'LOGOTIP' => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/fastery.shipping/asset/fastery-pvz.jpg'),
                        'RESTRICTIONS_SUM' => array(0)
                    ),
                    'mail' => array(
                        'TITLE' => Loc::getMessage('MAIL'),
                        'DESCRIPTION' => Loc::getMessage('MAIL_DESCRIPTION'),
                        'RESTRICTIONS_WEIGHT' => array(0),
                        'LOGOTIP' => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/fastery.shipping/asset/fastery-terminal.jpg'),
                        'RESTRICTIONS_SUM' => array(0)
                    )
                ),
                'ISNEEDEXTRAINFO' => 'Y'
            );
        }

        /**
         * @param $arSettings
         * @return string
         */
        public function SetSettings($arSettings)
        {
            return serialize($arSettings);
        }

        /**
         * @param $strSettings
         * @return mixed
         */
        public static function GetSettings($strSettings)
        {
            return unserialize($strSettings);
        }

        /**
         * @return array
         */
        public static function GetModuleSettings()
        {
            $propertyFormat = array(
                'TOKEN' => 'TEXT',
                'SHOP_ID' => 'TEXT',
                'USE_LOGGING' => 'TEXT',
                'USE_CALC_LOGGING' => 'TEXT',
                'CACHE_TIME' => 'TEXT',
                'SHOW_SELECT' => 'TEXT',
                'PICKUP_TYPE' => 'TEXT',
                'PROPERTIES' => 'ARRAY',
                'TEST' => 'TEXT',
                'DEDUCTED_ORDER' => 'TEXT'
            );

            $settings = array();

            foreach ($propertyFormat as $code => $type) {
                $value = COption::GetOptionString(CDeliveryFasteryShipping::$moduleId, $code, '', SITE_ID);
                if ($type == 'ARRAY') {
                    $value = json_decode($value, true);
                }
                $settings[$code] = $value;
            }
            return $settings;
        }

        /**
         * @param $LOCATION_ID
         * @return string
         */
        public function GetCity($LOCATION_ID)
        {
            $city = CSaleLocation::GetByID($LOCATION_ID);
            return $city['CITY_NAME'];
        }

        /**
         * @param $LOCATION_ID
         * @param $arConfig
         * @param $arOrder
         * @return mixed
         */
        public static function __GetLocationPrice($LOCATION_ID, $arConfig, $arOrder)
        {
            global $DB;

            $settings = CDeliveryFasteryShipping::GetModuleSettings();

            $weight = 0;
            $cost = 0;
            $assessed_cost = 0;
            foreach ($arOrder['ITEMS'] as $product) {
                $cost += $product['PRICE'] * $product['QUANTITY'];
                $assessed_cost += $product['PRICE'] * $product['QUANTITY'];
                $weight += ($product['WEIGHT'] ? $product['WEIGHT'] : 1) * $product['QUANTITY'];
            }

            $params = array();
            $params['city'] = CDeliveryFasteryShipping::GetCity($LOCATION_ID);
            $params['cost'] = $cost;
            $params['assessed_cost'] = $assessed_cost;
            $params['weight'] = $weight;
            $params['shop_id'] = $settings['SHOP_ID'];
            $params['access-token'] = $settings['TOKEN'];

            $response = CDeliveryFasteryHelper::makeGetRequest(self::$calculationUrl, $params);

            // Преобразуем в нужный нам формат данных с группировкой по методу доставки
            foreach ($response['items'] as $key => $item) {
                if ($item['type'] == 'point') {
                    // Сделаем отображение терминалов и пвз на одной карте
                    if($item['point_type'] == 'pvz' || $item['point_type'] == 'terminal') {
                        $item['point_type'] = 'pvz';
                    }
                    $carriers[$item['point_type']][$key] = $item;
                } else {
                    $carriers[$item['type']][$key] = $item;
                }
            }

            CDeliveryFasteryShipping::IncludeScripts();
            $return = array();

            if (isset($carriers['courier'])) {
                $return['courier'] = CDeliveryFasteryShipping::getCourier($carriers['courier'], 'courier');
                $return['fastest'] = CDeliveryFasteryShipping::getFastest($carriers['courier'], 'fastest', $return['courier']);
            }

            if (isset($carriers['mail'])) {
                $return['mail'] = CDeliveryFasteryShipping::getCourier($carriers['mail'], 'mail');
            }

            if (isset($carriers['pvz'])) {
                $return['pvz'] = CDeliveryFasteryShipping::getPoints($carriers['pvz'], 'pvz');
            }

            if (isset($carriers['terminal'])) {
                $return['terminal'] = CDeliveryFasteryShipping::getPoints($carriers['terminal'], 'terminal');
            }

            if (isset($carriers['mail'])) {
                $return['mail'] = CDeliveryFasteryShipping::getPoints($carriers['mail'], 'mail');
            }

            foreach ($return as $key => $carrier) {
                if (empty($carrier)) {
                    unset($return[$key]);
                }
            }

            if (count($return)) {
                return $return;
            }

            return false;
        }

        /**
         * @return array
         */
        public function GetDeliveries()
        {
            return array();
        }

        /**
         * @param $profile
         * @param $arConfig
         * @param $arOrder
         * @param $STEP
         * @param bool $TEMP
         * @return array
         */
        public function Calculate($profile, $arConfig, $arOrder, $STEP, $TEMP = false)
        {
            $arResult = CDeliveryFasteryShipping::__GetLocationPrice($arOrder["LOCATION_TO"], $arConfig, $arOrder);

            if ($arResult[$profile]['from'] == 0 || $arResult[$profile]['from'] == $arResult[$profile]['to']) {
                $term = Loc::getMessage('FROM_TRANSIT') . $arResult[$profile]['to'] . Loc::getMessage('DAYS');
            } else {
                $term = Loc::getMessage('FROM_TRANSIT') . $arResult[$profile]['from'] . Loc::getMessage('TO_TRANSIT') . $arResult[$profile]['to'] . Loc::getMessage('DAYS');
            }

            if ($profile == 'pvz' || $profile == 'terminal') {
                $term .= '<br />' . $arResult[$profile]['select'];
            }

            $return = array(
                'RESULT' => 'OK',
                'VALUE' => $arResult[$profile]['cost'],
                'TRANSIT' => $term,
            );

            return $return;
        }

        /**
         * @param $arOrder
         * @param $arConfig
         * @return array
         */
        public function Compability($arOrder, $arConfig)
        {
            $deliveries = CDeliveryFasteryShipping::__GetLocationPrice($arOrder["LOCATION_TO"], $arConfig, $arOrder);
            $deliveryNames = ($deliveries) ? array_keys($deliveries) : false;
            return $deliveryNames;
        }

        /**
         * @param $data
         * @param $title
         * @return array
         */
        private function getCourier($data, $title)
        {
            if (is_array($data) && count($data) > 0) {

                $courier = current($data);

                foreach ($data as $item) {
                    if ($item['cost'] < $courier['cost']) {
                        $courier = $item;
                    }
                }

                return array(
                    'cost' => $courier['cost'],
                    'from' => $courier['min_term'],
                    'to' => $courier['max_term'],
                    'data' => $courier
                );
            }

            return array();
        }

        /**
         * @param $data
         * @param $title
         * @param $cheapest
         * @return array
         */
        private function getFastest($data, $title, $cheapest)
        {
            if (is_array($data) && count($data) > 0) {

                $courier = current($data);

                foreach ($data as $item) {
                    if ($item['min_term'] < $courier['min_term']) {
                        $courier = $item;
                    }
                }

                if (json_encode($courier) == json_encode($cheapest['data']) || $courier['min_term'] >= $cheapest['data']['min_term']) {
                    return array();
                }

                return array(
                    'cost' => $courier['cost'],
                    'from' => $courier['min_term'],
                    'to' => $courier['max_term'],
                    'data' => $courier
                );
            }

            return array();
        }

        /**
         * @param $data
         * @param $title
         * @return array
         */
        private function getPoints($data, $title)
        {

            $point = current($data);
            $pointList = array();
            $pointAddress = array();

            $getKeyAddress = function($array, $address) {
                foreach($array as $k => $v)
                {
                    if($v['point_address'] == $address) {
                        return array($k => $v['point_address']);
                    }
                }
            };


            foreach ($data as $key => $item) {
                if (in_array($item['point_address'], $pointAddress)) {
                    $kAdress = $getKeyAddress($pointList, $item['point_address']);
                    if($item['cost'] > $pointList[key($kAdress)]['cost'])
                        continue;

                    unset($pointList[key($kAdress)]);
                }

                $pointList[$key] = $item;
                $pointAddress[] = $item['point_address'];

                if(isset($_REQUEST['order'][$title.'-point_id'])) {
                    if($_REQUEST['order'][$title.'-point_id'] == $item['uid']) {
                        $point = $item;
                        $isRequest = true;
                    }
                } elseif($item['cost'] < $point['cost']) {
                    $point = $item;
                    $isRequest = false;
                }
            }

            return array(
                'cost' => $point['cost'],
                'from' => $point['min_term'],
                'to' => $point['max_term'],
                'data' => $pointList,
                'select' => CDeliveryFasteryHelper::generateSelect($pointList, $title, $point, $isRequest)
            );
        }

        /**
         * @param $place
         * @param $placeId
         */
        public function GetPlaceLocation($place, $placeId)
        {

        }

        /**
         * Подготавливаем массив заказа для отправки в платформу
         *
         * @param $arOrder
         * @param $isNew
         * @return array|bool
         */
        private function getOrderArray($arOrder, $isNew)
        {

            CModule::IncludeModule('catalog');
            CModule::IncludeModule('sale');
            CModule::IncludeModule('iblock');
            CModule::IncludeModule('fastery.shipping');

            $settings = CDeliveryFasteryShipping::GetModuleSettings();
            $delivery = array();
            $deliveryProducts = array();

            $deliveryIds = explode(':', trim($arOrder['DELIVERY_ID']));
            $deliveryType = $deliveryIds[1];

            if($deliveryIds[0] == 'FasteryModule') {
                $dbBasketItems = CSaleBasket::GetList(array("NAME" => "ASC", "ID" => "ASC"),
                    array("ORDER_ID" => $arOrder['ID']),
                    false,
                    false,
                    false);

                // Подсчитаем вес, сумму за товары и оценочную стоимость
                $weight = 0;
                $cost = 0;
                $assessed_cost = 0;
                while ($item = $dbBasketItems->Fetch()) {
                    $deliveryProducts[] = array(
                        'name' => $item['NAME'],
                        'barcode' => $item['PRODUCT_ID'],
                        'quantity' => (int) $item['QUANTITY'],
                        'price' => $item['PRICE'],
                        'weight' => $item['WEIGHT'] ? $item['WEIGHT'] : 1
                    );

                    $weight += ($item['WEIGHT'] ? $item['WEIGHT'] : 1) * $item['QUANTITY'];
                    $cost += $item['PRICE'] * $item['QUANTITY'];
                    $assessed_cost += $item['BASE_PRICE'] * $item['QUANTITY'];
                }

                // Свойства заказа
                foreach ($settings['PROPERTIES'][$arOrder['PERSON_TYPE_ID']] as $code => $propId) {
                    $arPropKeys[$propId] = $code;
                }

                $db_props = CSaleOrderPropsValue::GetOrderProps($arOrder['ID']);
                $fasteryProperties = array();
                $systemProperty = 0;
                while ($arProp = $db_props->Fetch()) {
                    if ($arProp['CODE'] == 'POINT_ID') {
                        $systemProperty = $arProp['ID'];
                    }

                    if (isset($arPropKeys[$arProp['ORDER_PROPS_ID']]) && $arPropKeys[$arProp['ORDER_PROPS_ID']] != "") {
                        if ($arProp['IS_LOCATION'] == 'Y') {
                            $fasteryProperties[$arPropKeys[$arProp['ORDER_PROPS_ID']]] = CDeliveryFasteryShipping::GetCity($arProp['VALUE']);
                        } else {
                            $fasteryProperties[$arPropKeys[$arProp['ORDER_PROPS_ID']]] = $arProp['VALUE'];
                        }
                    }
                }

                $params = array();
                $params['city'] = $fasteryProperties['CITY'];
                $params['cost'] = $cost;
                $params['assessed_cost'] = $assessed_cost;
                $params['weight'] = $weight;
                $params['shop_id'] = $settings['SHOP_ID'];
                $params['access-token'] = $settings['TOKEN'];

                $response = CDeliveryFasteryHelper::makeGetRequest(self::$calculationUrl, $params);

                // Преобразуем в нужный нам формат данных с группировкой по методу доставки
                foreach ($response['items'] as $key => $item) {
                    if ($item['type'] == 'point') {
                        // Сделаем отображение терминалов и пвз на одной карте
                        if($item['point_type'] == 'pvz' || $item['point_type'] == 'terminal') {
                            $item['point_type'] = 'pvz';
                        }
                        $carriers[$item['point_type']][$key] = $item;
                    } else {
                        $carriers[$item['type']][$key] = $item;
                    }
                }

                $point_id = 0;

                if ($deliveryType == 'pvz' || $deliveryType == 'terminal') {
                    foreach ($carriers[$deliveryType] as $carrier) {
                        if ($carrier['uid'] == $_REQUEST[$deliveryType . '-point_id']) {
                            $point_id = $carrier['uid'];
                            $delivery = array(
                                'cost' => $carrier['cost'],
                                'uid' => $carrier['uid']
                            );

                        }
                    }
                } elseif ($deliveryType == 'courier' || $deliveryType == 'fastest' ||  $deliveryType == 'mail') {

                    if($fasteryProperties['HOUSE'] == '') $fasteryProperties['HOUSE'] = '-';

                    $carrier = CDeliveryFasteryShipping::getCourier($carriers['courier'], $deliveryType);
                    if($deliveryType == 'fastest') {
                        $carrier = CDeliveryFasteryShipping::getFastest($carriers['courier'], $deliveryType, $carrier);
                    }
                    if($deliveryType == 'mail') {
                        $carrier = CDeliveryFasteryShipping::getCourier($carriers['mail'], $deliveryType, $carrier);
                    }

                    if($deliveryType != 'mail') {
                        $delivery = array(
                            'type' => 'courier',
                            'cost' => $carrier['data']['cost'],
                            'uid' => $carrier['data']['uid']
                        );
                    } else {
                        $delivery = array(
                            'type' => 'mail',
                            'cost' => $carrier['data']['cost'],
                            'uid' => $carrier['data']['uid']
                        );
                    }

                }

                // Выберим свойства заказа для того чтобы найти системное свойство POINT_ID
                $db_props = CSaleOrderPropsValue::GetList(array(), array('CODE' => 'POINT_ID'));
                if ($prop = $db_props->Fetch()) {
                    $orderPropId = $prop['ORDER_PROPS_ID'];
                    //$systemProperty = $prop['ID'];
                }

                // Если заказ только создался то системных полей у него еще нет, и необходимо создать поле
                if($isNew) {
                    CSaleOrderPropsValue::Add(array(
                        'ORDER_ID' => $arOrder['ID'],
                        'CODE' => 'POINT_ID',
                        'ORDER_PROPS_ID' => $orderPropId,
                        'NAME' => 'Point id',
                        'VALUE' => $point_id
                    ));
                } elseif (isset($arOrder['DELIVERY_DOC_NUM']) && $arOrder['DELIVERY_DOC_NUM'] == '') {
                    CSaleOrderPropsValue::Add(array(
                        'ORDER_ID' => $arOrder['ID'],
                        'CODE' => 'POINT_ID',
                        'ORDER_PROPS_ID' => $orderPropId,
                        'NAME' => 'Point id',
                        'VALUE' => $point_id
                    ));
                } else {
                    if($systemProperty && $point_id) {
                        CSaleOrderPropsValue::Update($systemProperty, array(
                            'VALUE' => $point_id
                        ));
                    }
                }

                $order = array(
                    'shop_id' => $settings['SHOP_ID'],
                    'shop_order_number' => (string)$arOrder['ACCOUNT_NUMBER'],
                    'phone' => $fasteryProperties['PHONE'],
                    'fio' => $fasteryProperties['FIO'],
                    'products' => $deliveryProducts,
                    'address' => array(
                        'city' => $fasteryProperties['CITY'],
                        'street' => ($fasteryProperties['STREET']) ? $fasteryProperties['STREET'] : $fasteryProperties['ADDRESS'],
                        'house' => ($fasteryProperties['HOUSE']) ? $fasteryProperties['HOUSE'] : null,
                        'housing' => ($fasteryProperties['HOUSING']) ? $fasteryProperties['HOUSING'] : null,
                        'flat' => ($fasteryProperties['FLAT']) ? $fasteryProperties['FLAT'] : null,
                        'postcode' => ($fasteryProperties['POSTCODE']) ? $fasteryProperties['POSTCODE'] : null
                    ),
                    'delivery' => $delivery
                );
                return $order;
            }

            return false;
        }

        /**
         * Событие сохранения заказа
         *
         * @param \Bitrix\Main\Event $event
         */
        public function OrderSaved(\Bitrix\Main\Event $event)
        {

            CModule::IncludeModule('catalog');
            CModule::IncludeModule('sale');
            CModule::IncludeModule('iblock');
            CModule::IncludeModule('fastery.shipping');

            $settings = CDeliveryFasteryShipping::GetModuleSettings();
            $delivery = array();
            $deliveryProducts = array();

            $payment = $event->getParameter("ENTITY");
            $isNew = $event->getParameter("IS_NEW");
            $fields = $payment->getFields();
            $values = $fields->getValues();

            $orderId = $values['ID'];
            //$orderId = $values['ACCOUNT_NUMBER'];
            $arOrder = CSaleOrder::GetByID($orderId);

            $order = CDeliveryFasteryShipping::getOrderArray($arOrder, $isNew);

            $response = array();
            if($settings['DEDUCTED_ORDER'] != 'Y' && $order) {
                if ($isNew) {
                    $response = CDeliveryFasteryHelper::makePostRequest(CDeliveryFasteryShipping::$orderUrl, $order, array('Content-Type' => 'application/json'), $settings['TOKEN']);
                    // Если все успешно и заказ передан в платформу то запишем ID в поле Номер документа отгрузки
                    if(isset($response['id'])) {
                        CSaleOrder::Update($arOrder['ID'], array(
                            'DELIVERY_DOC_NUM' => $response['id']
                        ));
                    }
                } else {
                    $fasteryOrderId = CDeliveryFasteryHelper::getIdDb($orderId);
                    $response = CDeliveryFasteryHelper::makePostRequest(CDeliveryFasteryShipping::$updateUrl . $arOrder['DELIVERY_DOC_NUM'], $order, array('Content-Type' => 'application/json'), $settings['TOKEN']);
                }
            }

            CDeliveryFasteryHelper::log(json_encode($order), $settings);
            CDeliveryFasteryHelper::log(json_encode($response), $settings);
            CDeliveryFasteryHelper::saveToDb($orderId, json_encode($order), json_encode($response));

        }

        /**
         * Добавление скриптов на страницу оформления заказа или страницу редактирвоания заказа
         */
        public function IncludeScripts()
        {
            if ($_SERVER['SCRIPT_NAME'] == '/bitrix/admin/sale_order_shipment_edit.php') {
                include_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/fastery.shipping/classes/backend.php');
            } else {
                include_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/fastery.shipping/classes/frontend.php');
            }
        }

        /**
         * Событие изменения статуса оплаты
         *
         * @param \Bitrix\Main\Event $event
         */
        public function OrderPaid(\Bitrix\Main\Event $event)
        {
            $settings = CDeliveryFasteryShipping::GetModuleSettings();

            CModule::IncludeModule("catalog");
            CModule::IncludeModule("sale");
            CModule::IncludeModule("iblock");
            CModule::IncludeModule("checkoutru.checkout");

            $payment = $event->getParameter("ENTITY");
            $fields = $payment->getFields();
            $values = $fields->getValues();

            $order = array(
                'shop_id' => $settings['SHOP_ID'],
                'payment_method' => ($values['PAYED'] == 'Y') ? 'noPay' : 'fullPay'
            );

            if(isset($values['DELIVERY_DOC_NUM']) && $values['DELIVERY_DOC_NUM'] != ''){
                $response = CDeliveryFasteryHelper::makePostRequest(CDeliveryFasteryShipping::$updateUrl . $values['DELIVERY_DOC_NUM'], $order, array('Content-Type' => 'application/json'), $settings['TOKEN']);
                CDeliveryFasteryHelper::log(json_encode($order), $settings);
                CDeliveryFasteryHelper::log(json_encode($response), $settings);
            }
        }

        /**
         * Событие изменения статуса заказа
         *
         * @param \Bitrix\Main\Event $event
         */
        public function OrderStatusChange(\Bitrix\Main\Event $event)
        {
            // TODO реализация метода выгрузки заказа если установлен статус выгрузки
        }

        /**
         * Событие изменения статуса отгрузки
         * @param \Bitrix\Main\Event $event
         */
        public function OrderDeducted(\Bitrix\Main\Event $event)
        {
            $settings = CDeliveryFasteryShipping::GetModuleSettings();

            CModule::IncludeModule("catalog");
            CModule::IncludeModule("sale");
            CModule::IncludeModule("iblock");
            CModule::IncludeModule("checkoutru.checkout");

            $payment = $event->getParameter("ENTITY");
            $fields = $payment->getFields();
            $values = $fields->getValues();

            if($values['DEDUCTED'] == 'Y' && $settings['DEDUCTED_ORDER'] == 'Y') {
                $orderId = $values['ORDER_ID'];
                $arOrder = CSaleOrder::GetByID($orderId);

                $order = CDeliveryFasteryShipping::getOrderArray($arOrder, false);

                $response = array();
                if($values['DELIVERY_DOC_NUM'] == '' && $order){
                    $response = CDeliveryFasteryHelper::makePostRequest(CDeliveryFasteryShipping::$orderUrl, $order, array('Content-Type' => 'application/json'), $settings['TOKEN']);
                    // Если все успешно и заказ передан в платформу то запишем ID в поле Номер документа отгрузки
                    if(isset($response['id'])) {
                        CSaleOrder::Update($arOrder['ID'], array(
                            'DELIVERY_DOC_NUM' => $response['id']
                        ));
                    }
                } else {
                    $fasteryOrderId = CDeliveryFasteryHelper::getIdDb($orderId);
                    $response = CDeliveryFasteryHelper::makePostRequest(CDeliveryFasteryShipping::$updateUrl . $fasteryOrderId, $order, array('Content-Type' => 'application/json'), $settings['TOKEN']);
                }

                CDeliveryFasteryHelper::saveToDb($orderId, json_encode($order), json_encode($response));
                CDeliveryFasteryHelper::log(json_encode($order), $settings);
                CDeliveryFasteryHelper::log(json_encode($response), $settings);
            }

        }

        /**
         * Событие отмены заказа
         *
         * @param \Bitrix\Main\Event $event
         */
        public function OrderCancel(\Bitrix\Main\Event $event)
        {
            // TODO реализация метода отмены заказа - будет реализовано после появление соответствующего метода API
        }
    }
}
