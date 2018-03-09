<?php
use Bitrix\Main\Localization\Loc;

// Инициализируем файл с локализацией
Loc::loadMessages(__FILE__);

$module_id = 'fastery.shipping';

CModule::Includemodule('iblock');
CModule::Includemodule('sale');
CModule::Includemodule('catalog');
CModule::IncludeModule($module_id);

$RIGHT = $APPLICATION->GetGroupRight($module_id);

// Табы для настроек модуля
$aTabs = array(
    array(
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('MAIN_TAB_SET'),
        'ICON' => 'main_user_edit',
        'TITLE' => Loc::getMessage('OPTIONS_MAIN_TAB_TITLE')
    ),
    array(
        'DIV' => 'edit2',
        'TAB' => Loc::getMessage('OPTIONS_BINDING_FIELDS'),
        'ICON' => 'main_user_edit',
        'TITLE' => Loc::getMessage('OPTIONS_BINDING_TAB_TITLE')
    ),
);

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$arOrder = array('SORT' => 'ASC', 'NAME' => 'ASC');

// Получим статусы заказа для привязки их к модулю
$OrderStatuses = array();
$db_StatusOrder = CSaleStatus::GetList($arOrder);
while ($arStatus = $db_StatusOrder->GetNext()) {
    $OrderStatuses[$arStatus['ID']] = $arStatus['NAME'];
}

// Получим свойства заказа для привязки их к модулю
$arPersonTypeProps = array();
$db_props = CSaleOrderProps::GetList(
    array('SORT' => 'ASC'),
    array(
        'PERSON_TYPE_ID' => $arPersonTypeIDs,
        'TYPE' => array(
            'TEXT',
            'TEXTAREA',
            'STRING'
        )
    ),
    false,
    false,
    array(
        'ID',
        'NAME',
        'PERSON_TYPE_ID'
    )
);

while ($arProps = $db_props->GetNext()) {
    $arPersonTypeProps[$arProps['PERSON_TYPE_ID']]['PROPS'][] = $arProps;
}

// Получим типы покупателей
$db_ptype = CSalePersonType::GetList($arOrder, Array('ACTIVE' => 'Y'));
while ($ptype = $db_ptype->Fetch()) {
    $arPersonTypeIDs[] = $ptype['ID'];
    $arPersonTypeProps[$ptype['ID']] = array(
        'NAME' => $ptype['NAME'],
        'PROPS' => array(),
        'LIDS' => $ptype['LIDS']
    );
}

// Получим свойства заказа для каждого типа покупателя
$db_props = CSaleOrderProps::GetList(
    array('SORT' => 'ASC'),
    array(
        'PERSON_TYPE_ID' => $arPersonTypeIDs,
        'TYPE' => array(
            'TEXT',
            'TEXTAREA',
            'STRING',
            'LOCATION'
        )
    ),
    false,
    false,
    array('ID', 'NAME', 'PERSON_TYPE_ID')
);

while ($arProps = $db_props->GetNext()) {
    $arPersonTypeProps[$arProps['PERSON_TYPE_ID']]['PROPS'][] = $arProps;
}


$arPropsForOrder = array(
    'FIO',
    'PHONE',
    'EMAIL',
    'CITY',
    'ADDRESS',
    'STREET',
    'HOUSE',
    'FLAT',
    'HOUSING',
    'POSTCODE',
);

$arFields = array(
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

if (isset($_POST)) {
    if (isset($_POST['SAVE_SETTINGS'])) {
        foreach ($arFields as $code => $value) {
            if ($value == 'ARRAY')
                $val = json_encode($_POST[$code]);
            else $val = $_POST[$code];
            COption::SetOptionString($module_id, $code, $val, SITE_ID);
        }
    }
}

$arSettings = array();
foreach ($arFields as $code => $type) {
    $value = COption::GetOptionString($module_id, $code, '', SITE_ID);

    if ($type == 'ARRAY')
        $value = json_decode($value, true);
    $arSettings[$code] = $value;
}

// не забудем разделить подготовку данных и вывод
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

?>
<?php CJSCore::Init(array('jquery')); ?>

<h1><?= GetMessage('MODULE_TITLE'); ?></h1>

<form method='post'
      action='<?php echo $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($module_id) ?>&amp;lang=<?= LANGUAGE_ID ?>'>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width='40%' class='adm-detail-content-cell-l'
            style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_TEST') ?>:
        </td>
        <td width='60%'>
            <input type='checkbox' name='TEST'
                   value='Y' <?php if ($arSettings['TEST'] == 'Y') echo ' checked'; ?> />
        </td>
    </tr>
    <tr>
        <td width='40%' class='adm-detail-valign-top'><?= Loc::getMessage('PROPERTY_TOKEN'); ?>:</td>
        <td width='60%'>
            <input type='text' value='<?= $arSettings['TOKEN'] ?>' name='TOKEN'/>
        </td>
    </tr>

    <tr>
        <td width='40%' class='adm-detail-content-cell-l'
            style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_SHOP_ID') ?>:
        </td>
        <td width='60%'>
            <input type='text' name='SHOP_ID' value='<?= $arSettings['SHOP_ID']; ?>'/>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">
            <hr/>
        </td>
    </tr>
    <tr>
        <td width='40%' class='adm-detail-content-cell-l'
            style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_USE_LOGGING') ?>:
        </td>
        <td width='60%'>
            <input type='checkbox' name='USE_LOGGING'
                   value='Y' <?php if ($arSettings['USE_LOGGING'] == 'Y') echo ' checked'; ?>>
        </td>
    </tr>
    <tr>
        <td width='40%' class='adm-detail-content-cell-l'
            style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_CACHE_TIME') ?>:
        </td>
        <td width='60%'>
            <input type='text' name='CACHE_TIME' value='<?= $arSettings['CACHE_TIME']; ?>'/>
        </td>
    </tr>
    <tr>
        <td width='40%' class='adm-detail-content-cell-l'
            style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_DEDUCTED_ORDER') ?>:
        </td>
        <td width='60%'>
            <input type='checkbox' name='DEDUCTED_ORDER'
                   value='Y' <?php if ($arSettings['DEDUCTED_ORDER'] == 'Y') echo ' checked'; ?> />
        </td>
    </tr>

    <?php $tabControl->BeginNextTab(); ?>

    <?php foreach ($arPersonTypeProps as $idPerson => $arPerson): ?>
        <tr class='heading'>
            <td colspan='2'><?= $arPerson['NAME'] ?></td>
        </tr>
        <?php foreach ($arPropsForOrder as $codeProp): ?>

            <tr>
                <td width='40%' class='adm-detail-content-cell-l'
                    style='white-space:nowrap'><?= Loc::getMessage('PROPERTY_' . $codeProp) ?>:
                </td>
                <td width='60%'>
                    <select name='PROPERTIES[<?= $idPerson ?>][<?= $codeProp ?>]'>
                        <option value='empty'><?= Loc::getMessage('EMPTY_VALUE') ?></option>
                        <?php foreach ($arPerson['PROPS'] as $arProp): ?>
                            <option value='<?= $arProp['ID'] ?>' <?php if ($arProp['ID'] == $arSettings['PROPERTIES'][$idPerson][$codeProp]): ?> selected<?php endif ?>><?= $arProp['NAME'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?= bitrix_sessid_post(); ?>

    <?php $tabControl->Buttons(); ?>
    <input type='submit' name='SAVE_SETTINGS' class='adm-btn-save' value='<?= Loc::getMessage('BUTTON_SAVE'); ?>'
           title='<?= Loc::getMessage('BUTTON_SAVE'); ?>'/>
    <input type='button' onclick='window.document.location = ' ?lang=<?= LANGUAGE_ID ?>''
           value='<?= Loc::getMessage('BUTTON_CANCEL'); ?>' title='<?= Loc::getMessage('BUTTON_CANCEL'); ?>'/>
    <?php $tabControl->End(); ?>
</form>
<?php
if (!empty($arNotes)) {
    echo BeginNote();
    foreach ($arNotes as $i => $str) {
        ?><span class='required'><sup><?php echo $i + 1 ?></sup></span><?php echo $str ?><br><?
    }
    echo EndNote();
}
?>
