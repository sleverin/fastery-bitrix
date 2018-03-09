<?php
/**
 * Created by PhpStorm.
 * User: shdkhayrtdinov
 * Date: 16.06.17
 * Time: 16:23
 */
include('httpful.phar');
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class CDeliveryFasteryHelper
{
    public static function makePostRequest($uri, $params, $headers, $token)
    {

        $settings = CDeliveryFasteryShipping::GetModuleSettings();

        $domen = 'http://lk.fastery.ru';
        if($settings['TEST'] == 'Y') {
            $domen = 'http://demo.fastery.ru';
        }

        $data = array('access-token' => $token);

        $url = $domen . $uri . '?' . http_build_query($data);
        $json = json_encode($params);

        $response = \Httpful\Request::post($url)
            ->addHeaders($headers)
            ->body($json)
            ->send();

        return json_decode($response, true);
    }

    public static function makeGetRequest($uri, $params)
    {

        $settings = CDeliveryFasteryShipping::GetModuleSettings();

        $domen = 'http://lk.fastery.ru';
        if($settings['TEST'] == 'Y') {
            $domen = 'http://demo.fastery.ru';
        }

        $url = $domen . $uri . '?' . http_build_query($params);
        $hash = md5($url);

        $response = CDeliveryFasteryHelper::getCache($hash);

        if (!$response) {
            $response = \Httpful\Request::get($url)->send();
            CDeliveryFasteryHelper::setCache($hash, $response);
        }

        return json_decode($response, true);
    }

    public static function generateSelect($data, $name, $point, $isRequest)
    {
        $settings = CDeliveryFasteryShipping::GetModuleSettings();
        $pointId = null;
        $select = '';

        $minPrice = $data[key($data)]['cost'];
        $maxPrice = $minPrice;
        $minTerm = $data[key($data)]['min_term'];
        $maxTerm = $data[key($data)]['max_term'];

        foreach ($data as $key => $value) {
            if ($value['cost'] < $minPrice) $minPrice = $value['cost'];
            if ($value['cost'] > $maxPrice) $maxPrice = $value['cost'];
            if ($value['min_term'] < $minTerm) $minTerm = $value['min_term'];
            if ($value['max_term'] > $maxTerm) $maxTerm = $value['max_term'];
        }

        if (is_array($data) && count($data) > 0) {
            $select .= '<select class="fastery-select" id="fastery.' . $name . '" name="' . $name . '"';
            $select .= ' data-min_price="' . $minPrice . '"';
            $select .= ' data-max_price="' . $maxPrice . '"';
            $select .= ' data-min_term="' . $minTerm . '"';
            $select .= ' data-max_term="' . $maxTerm . '"';
            $select .= ' >';
            foreach ($data as $key => $item) {
                $select .= '<option rel="' . $key . '" data-point_id="' . $item['uid'] . '" value="' . $item['point_address'] . '"';
                foreach ($item as $k => $v) {
                    if (!is_array($v)) {
                        $select .= ' data-' . $k . '="' . $v . '" ';
                    }
                }

                if (isset($_GET['order_id'])) {
                    $db_props = CSaleOrderPropsValue::GetOrderProps($_GET['order_id']);
                    while ($arProp = $db_props->Fetch()) {
                        if ($arProp['CODE'] == 'POINT_ID') $pointId = $arProp['VALUE'];
                    }
                }

                if ($point['point_address'] == $item['point_address'] || ($pointId && $pointId == $item['uid'])) {
                    $select .= ' selected="selected"';
                    $select .= $isRequest ? ' id="selected-point"' : '';
                }
                $select .= '>';
                $select .= $item['point_address'];
                $select .= '</option>';
            }

            $select .= '</select>';
            $select .= '<br />';
            $select .= '<a class="choise_on_map" rel="' . $name . '">' . Loc::getMessage('CHOISE_ON_MAP') . '</a>';
            $select .= '<input type="hidden" id="' . $name . '-point_id" name="' . $name . '-point_id" value="' . ($pointId ? $pointId : '') . '" />';
        }

        return $select;
    }

    public static function log($message, $settings)
    {
        $log_path = '/log/';
        define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"] . $log_path . "fastery.log");
        if ($settings['USE_LOGGING']) {
            AddMessage2Log($message, "fastery.shipping");
        }
    }

    public static function setCache($hash, $value)
    {
        $settings = CDeliveryFasteryShipping::GetModuleSettings();

        $cache = new CPHPCache();
        $cacheTime = (int)$settings['CACHE_TIME'];
        $cacheId = $hash . CDeliveryFasteryShipping::$moduleId;
        $cachePath = '/' . CDeliveryFasteryShipping::$moduleId . '/';

        if ($cacheTime > 0) {
            $cache->StartDataCache($cacheTime, $cacheId, $cachePath);
            $cache->EndDataCache($value);
            return true;
        }

        return false;
    }

    public static function getCache($hash)
    {
        $settings = CDeliveryFasteryShipping::GetModuleSettings();

        $cache = new CPHPCache();
        $cacheTime = (int)$settings['CACHE_TIME'];
        $cacheId = $hash . CDeliveryFasteryShipping::$moduleId;
        $cachePath = '/' . CDeliveryFasteryShipping::$moduleId . '/';

        if ($cacheTime > 0 && $cache->InitCache($cacheTime, $cacheId, $cachePath)) {
            return $cache->GetVars();
        }

        return false;

    }

    public static function saveToDb($orderId, $request, $response)
    {
        global $DB;

        $order = json_decode($response, true);
        $fasteryOrderId = $order['id'];

        $dbRes = $DB->Query("INSERT INTO `b_fastery` (`order_id`,
                                                              `fastery_order_id`,
                                                              `request`,
                                                              `response`)
                                                    VALUES ('" . $orderId . "',
                                                            '" . $fasteryOrderId . "',
                                                            '" . str_replace("\\", "\\\\", $request) . "',
                                                            '" . str_replace("\\", "\\\\", str_replace("'", "", $response)) . "')");
    }

    public static function getIdDb($orderId)
    {
        global $DB;

        $results = $DB->Query("SELECT * FROM `b_fastery` WHERE `order_id`='" . $orderId . "'");
        $checkoutOrder = $results->Fetch();
        return $checkoutOrder['fastery_order_id'];
    }

}