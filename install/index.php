<?php

use Bitrix\Main\Localization\Loc;

class fastery_shipping extends CModule
{
    var $errors;
    var $MODULE_ID = 'fastery.shipping';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;

    /**
     * fastery_shipping constructor.
     * Устанавливаем все необхимые параметры модуля
     */
    function __construct()
    {
        Loc::loadMessages(__FILE__);

        $arModuleVersion = array();

        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->MODULE_NAME = Loc::getMessage('INSTALL_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('INSTALL_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage("PARTNER");
        $this->PARTNER_URI = Loc::getMessage("PARTNER_URI");
    }

    /**
     * Запись файлов модуля в каталоги
     * @param array $arParams
     * @return bool
     */
    function InstallFiles($arParams = array())
    {
        // Создадим папку для логов если она не существует
        if (!file_exists($_SERVER["DOCUMENT_ROOT"] . '/log')) {
            mkdir($_SERVER["DOCUMENT_ROOT"] . '/log', 0700);
        }

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/js/", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/js/" . $this->MODULE_ID, true, true);
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/css/", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/css/" . $this->MODULE_ID, true, true);
        return true;
    }

    /**
     * Удаление файлов модуля
     * @return bool
     */
    function UnInstallFiles()
    {
        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        DeleteDirFilesEx('/bitrix/css/' . $this->MODULE_ID);
        return true;
    }

    /**
     * Создание таблицы в базе данных для записи заказов
     * @return bool
     */
    function InstallDB()
    {
        global $DB;

        $dbRes = $DB->Query('CREATE TABLE IF NOT EXISTS `b_fastery` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `fastery_order_id` int(11) NOT NULL,
		  `order_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `request` text COLLATE utf8_unicode_ci NOT NULL,
		  `response` text COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;');

        return true;
    }

    /**
     * Удаление таблиц модуля
     * @return bool
     */
    function UnInstallDB()
    {
        // Не удаляем таблицу так как при переустановке не хотим чтобы данные в таблице
        // удалилиль или перезаписались.
        return true;
    }

    /**
     * Устанавливаем события модуля
     * и привязываем их у хукам системы
     * @return bool
     */
    function InstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();

        // Событие "OnEpilog" вызывается в конце визуальной части эпилога сайта.
        $eventManager->registerEventHandler('main', 'OnEpilog', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'IncludeScripts');

        // Вызывается при сохранении, если оплаченность заказа была изменена.
        $eventManager->registerEventHandler('sale', 'OnSaleOrderPaid', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'OrderPaid');

        // Вызывается при сохранении, если оплаченность заказа была изменена.
        $eventManager->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'OrderSaved');

        // Вызывается при сохранении, если статус заказа был изменен.
        $eventManager->registerEventHandler('sale', 'OnSaleStatusOrderChange', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'OrderStatusChange');

        // Вызывается при инициализации списка доступных методов доставкиы.
        $eventManager->registerEventHandler('sale', 'OnSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'Init');

        // Вызывается при сохранении, если был изменен флаг отгрузки.
        $eventManager->registerEventHandler('sale', 'OnShipmentDeducted', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'OrderDeducted');

        // Вызывается при сохранении, если сохраняемый заказ был отменен.
        $eventManager->registerEventHandler('sale', 'OnSaleOrderCanceled', $this->MODULE_ID, 'CDeliveryFasteryShipping', 'OrderCancel');

        return true;
    }

    /**
     * Отвязываем события модуля
     * @return bool
     */
    function UnInstallEvents()
    {
        CModule::IncludeModule('sale');
        return true;
    }

    /**
     * Установка модуля
     */
    function DoInstall()
    {
        //global $DB, $DBType, $APPLICATION;

        $this->errors = false;

        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallEvents();

        RegisterModule($this->MODULE_ID);

        $settings = Array(
            'CACHE_TIME' => '86400',
            'WEIGHT_UNIT' => '1',
            'LENGTH_UNIT' => '0.001'
        );

        foreach ($settings as $key => $value) {
            COption::SetOptionString($this->MODULE_ID, $key, $value, SITE_ID);
        }

        LocalRedirect('/bitrix/admin/settings.php?lang=ru&mid=fastery.shipping&mid_menu=1');
    }

    /**
     * Деинсталяция модуля
     */
    function DoUninstall()
    {
        //global $DB, $DBType, $APPLICATION;

        $this->errors = false;

        $this->UnInstallDB();
        $this->UnInstallFiles();
        $this->UnInstallEvents();

        UnRegisterModule($this->MODULE_ID);
    }
}