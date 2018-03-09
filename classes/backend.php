<?php
use Bitrix\Main\Localization\Loc;
if (CModule::IncludeModule('fastery.shipping')) {

    global $APPLICATION;
    global $DB;

    Loc::loadMessages(__FILE__);

    $asset = Bitrix\Main\Page\Asset::getInstance();
    $asset->addJs('http://api-maps.yandex.ru/2.1.34/?load=package.full&lang=ru-RU');
    $asset->addJs('/bitrix/js/fastery.shipping/nouislider.js');
    $asset->addJs('/bitrix/js/fastery.shipping/fastery.js');

    $GLOBALS['APPLICATION']->AddHeadString('<link href="/bitrix/css/fastery.shipping/nouislider.css";  type="text/css" rel="stylesheet" />', true);
    $GLOBALS['APPLICATION']->AddHeadString('<link href="/bitrix/css/fastery.shipping/fastery.css";  type="text/css" rel="stylesheet" />', true);

    $settings = CDeliveryFasteryShipping::GetModuleSettings();
    $deliveries = \Bitrix\Sale\Delivery\Services\Manager::getActiveList();

    $parentDelivery = array_filter($deliveries, function ($value) {
        return $value['CODE'] == 'FasteryModule';
    });

    $deliveryCode = array();
    foreach ($deliveries as $delivery) {
        if ((int)$delivery['PARENT_ID'] == (int)$parentDelivery[key($parentDelivery)]['ID']) {
            $deliveryType = explode(':', $delivery['CODE']);
            $deliveryCode[]= $delivery['ID'].':"'.$deliveryType[1].'"';
        }
    }

    // Собираем все свойства заказа для того чтобы определить LOCATION_ID
    $arOrder = CSaleOrder::GetByID($_GET['order_id']);
    $dbProps = CSaleOrderPropsValue::GetOrderProps($arOrder['ID']);
    while ($arProps = $dbProps->Fetch()) {
        if ($arProps["TYPE"] == "LOCATION") {
            $locationId = $arProps["VALUE"];
        }
    }

    // Рассчитаем сумму и сроки доставки
    $shipping = CDeliveryFasteryShipping::__GetLocationPrice($locationId, array(), $arOrder);

    // Выводим тут селекты .... нужно что то с этим делать!
    echo '<div style="display:none;">';
    foreach ($shipping as $type => $item) {
        if (isset($item['select'])) {
            echo $item['select'];
        }
    }

    // Дополнительное поле для опредления текущего типа доставки
    echo '<input type="text" name="checkoutType" />';
    echo '</div>';

    $items = Bitrix\Sale\Helpers\Admin\Blocks\OrderShipment::getDeliveryServiceList();
    $src_list = Bitrix\Sale\Helpers\Admin\Blocks\OrderShipment::getImgDeliveryServiceList($items);

    $GLOBALS['APPLICATION']->AddHeadString('<script type="text/javascript">
        BX.addCustomEvent("onAjaxSuccess", fasteryAfterFormReload);
        
        function fasteryAfterFormReload()
        {
            generateLink();
        }
        
        function generateLink()
        {
            var profile_id = document.getElementById("PROFILE_1").value;
		    var deliveries = {' . implode(',', $deliveryCode) . '};
//		    var shippingType = document.getElementsByName("checkoutType").item(0).value;
		    var shippingType = deliveries[profile_id];
            
            if(shippingType == "pvz" || shippingType == "terminal")
            {
                // Нужно удалить ссылку если такая уже есть
                if(document.getElementById("showMap")) {
                    var showMapParent = document.getElementById("showMap").parentNode;
                    showMapParent.removeChild(document.getElementById("showMap"));
                }
                
                ymaps.ready(function () {
                
                    // Создадим ссылку для карты
                    var showMap = document.createElement("a");
                    showMap.id = "showMap";
                    showMap.className = "choise_on_map";
                    showMap.setAttribute("rel", shippingType);
                    showMap.textContent = "' . Loc::getMessage('CHOISE_ON_MAP') . '";
                    
                    showMap.addEventListener("click", fst.createMap, false);
                    
                    // Сделаем задержку в 0.5сек чтобы ссылка не удалилась после перезагрузке блока
                    setTimeout(function() {
                        document.getElementById("PROFILE_1").parentNode.insertBefore(showMap, document.getElementById("PROFILE_1").nextSibling);
                    }, 200);
                    
                });
                
            }
        }
        
        function adminSelectChoose()
        {      
            var profile_id = document.getElementById("PROFILE_1").value;
            var deliveries = {' . implode(',', $deliveryCode) . '};
            document.getElementById("CUSTOM_PRICE_DELIVERY_1").value = "Y";
            document.getElementsByName("checkoutType").item(0).value = deliveries[profile_id];
            BX.ready(function () {
                BX.namespace("BX.Sale.Admin.OrderShipment");
                var e = new Object();
                e.index = 1;
                e.id = profile_id;
                e.src_list = "'.\CUtil::PhpToJSObject($src_list).'";
                e.isAjax = true;
                e.active = true;
                var OrderShipment = new BX.Sale.Admin.OrderShipment(e);
                OrderShipment.updateDeliveryInfo();
            });
        }
        
        document.addEventListener(\'DOMContentLoaded\', function()
        {    
            fst.init();
            generateLink();
            
            var profile_id = document.getElementById("PROFILE_1").value;
            var deliveries = {' . implode(',', $deliveryCode) . '};
            
            
			var selectsAdmin = document.getElementsByClassName(\'fastery-select\');
			for (var i = 0; i < selectsAdmin.length; i++) {
				selectsAdmin.item(i).addEventListener("change", adminSelectChoose, false);
			}
        });
        
    </script>');
}
