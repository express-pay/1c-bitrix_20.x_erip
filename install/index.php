<?php

IncludeModuleLangFile(__FILE__);

class triincom_expresspayerip extends CModule
{
    const MODULE_ID = 'triincom.expresspayerip';
    public $MODULE_ID = 'triincom.expresspayerip';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public $strError = '';
    public $errors = [];

    public function __construct()
    {
        $arModuleVersion = array();
        include(dirname(__FILE__) . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = GetMessage("EXPRESSPAY_ERIP_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("EXPRESSPAY_ERIP_MODULE_DESC");
        $this->PARTNER_NAME = GetMessage("EXPRESSPAY_ERIP_MODULE_PARTNER");
        $this->PARTNER_URI = "https://express-pay.by/";
    }

    public function InstallFiles($arParams = array())
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $this->MODULE_ID . '/install',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/handlers/paysystem/',
            true,
            true
        );
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx(BX_ROOT . '/modules/sale/handlers/paysystem/expresspay_erip');
        return true;
    }

    public function DoInstall()
    {
        global $DB, $APPLICATION;

        if (!\CModule::IncludeModule('sale')) {
            $this->errors[] = GetMessage('ERROR_SALE_MODULE_NOT_INSTALLED');
        }

        if (count($this->errors) > 0) {
            $APPLICATION->ThrowException(implode(', ', $this->errors));
            return false;
        }

        $this->InstallFiles();

        RegisterModule(self::MODULE_ID);
    }

    public function DoUninstall()
    {
        \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
        UnRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
    }
}
