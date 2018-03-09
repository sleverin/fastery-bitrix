<?php
if (CModule::IncludeModule('fastery.shipping')) {
    global $APPLICATION;
    global $DB;
    $settings = CDeliveryFasteryShipping::GetModuleSettings();

    $address_prop_keys = 'address_prop_keys = {';
    $i = 0;
    foreach ($settings['PROPERTIES'] as $k => $val) {
        $i++;
        if ($val['ADDRESS'] == 'empty') {
            $idProp = 0;
        } else {
            $idProp = $val['ADDRESS'];
        }
        $address_prop_keys .= $k . ':' . $idProp;
        if (count($settings['PROPERTIES']) != $i) {
            $address_prop_keys .= ',';
        }
    }
    $address_prop_keys .= '}';

    $GLOBALS['APPLICATION']->AddHeadString('<script type="text/javascript">' . $address_prop_keys . ';</script>');

    $asset = Bitrix\Main\Page\Asset::getInstance();
    $asset->addJs('http://api-maps.yandex.ru/2.1.34/?load=package.full&lang=ru-RU');
    $asset->addJs('/bitrix/js/fastery.shipping/nouislider.js');
    if (LANG_CHARSET != 'UTF-8' && LANG_CHARSET != 'utf-8') {
        $asset->addJs('/bitrix/js/fastery.shipping/fastery.js');
    } else {
        $asset->addJs('/bitrix/js/fastery.shipping/fastery-utf.js');
    }
    $asset->addCss('/bitrix/css/fastery.shipping/nouislider.css');
    $asset->addCss('/bitrix/css/fastery.shipping/fastery.css');

    $GLOBALS['APPLICATION']->AddHeadString('<script type="text/javascript">
        BX.addCustomEvent("onAjaxSuccess", fasteryAfterFormReload);
        function fasteryAfterFormReload()
        {
            
            var links = document.getElementsByClassName(\'choise_on_map\');
            for (var i = 0; i < links.length; i++) {
                links.item(i).addEventListener("click", fst.createMap, false);
            }
            
            var selects = document.getElementsByClassName(\'fastery-select\');
            for (var i = 0; i < selects.length; i++) {
                selects.item(i).addEventListener("change", fst.selectChoose, false);
            }
            
            if(document.getElementsByClassName("fastery-select").length > 0) {
               
               // При перезагрузке необходимо заполнить скрытое поле          
               if(document.getElementById("selected-point")) {
                   var pointType = document.getElementsByClassName("fastery-select").item(0).name;
                   var input = document.getElementById(pointType + "-point_id");
                   input.value = document.getElementById("selected-point").getAttribute("data-point_id");
                   
               }
                
               // Необходимо пройтись по id свойства товара и заполнить их
               Object.keys(address_prop_keys).map(function(objectKey, index) {
                    var value = address_prop_keys[objectKey];
                    if(document.getElementsByName("ORDER_PROP_" + value).length > 0) {
                      document.getElementsByName("ORDER_PROP_" + value).item(0).value = document.getElementsByClassName("fastery-select").item(0).value;
                    }
               }); 
            } 
        }
        
        
    </script>');

} ?>
