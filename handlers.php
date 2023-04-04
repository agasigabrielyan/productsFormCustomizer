<?php
use Bitrix\Main\DI;
use Devconsult\Crm\Smart\ContainerOverride;
use Bitrix\Main\Engine\CurrentUser;
use Devconsult\Entity\{CrmFieldBlock, Highload};
use Devconsult\Crm\Helper;
use Bitrix\Main\Page\Asset;

\InformUnity\Handlers\Main::init();

if (\Bitrix\Main\Loader::includeModule('crm'))
{
	//подменяем преопределенный контейнер
	DI\ServiceLocator::getInstance()->addInstance('crm.service.container', new ContainerOverride());
}

\Bitrix\Main\EventManager::getInstance()->addEventHandler('crm', 'OnBeforeCrmDealUpdate', function (&$arFields) {

    //AddMessage2Log($arFields);

	$currentUserId = CurrentUser::get()->getId();
	$responsibleField = DEAL_RESPONSIBLE_FIELD;

    $oldData = \Bitrix\Crm\DealTable::getList([
		'select' => ['*','UF_*'],
		'filter' => [
			'ID' => $arFields['ID']
		]
	])->fetch();

    $hl = new Highload('StageLock');
    $arItem = $hl->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
        'UF_CATEGORY_ID' => $oldData['CATEGORY_ID']
    ]);

    $stageLock = current($arItem);

    $hlResponsible = new Highload('AcceptedResponsible');
    $arItems = $hlResponsible->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal
    ]);

	$responsible = [];

	foreach($arItems as $item)
	{
		if($item['UF_RESPONSIBLE'])
		{
			$responsible = array_merge($responsible, \Bitrix\Main\Web\Json::decode($item['UF_RESPONSIBLE']));
		}
	}

	if (!in_array($currentUserId, $responsible) && !empty($stageLock['UF_STAGE_ID']) && !empty($arFields['STAGE_ID'])) {

		$status = $oldData['CATEGORY_ID'] === '0' ? 'DEAL_STAGE' : 'DEAL_STAGE_'.$oldData['CATEGORY_ID'];

		if (Helper::isLockStage($status, $arFields['STAGE_ID'], $oldData['STAGE_ID'], $stageLock['UF_STAGE_ID']))
        {
            $arFields['RESULT_MESSAGE'] = 'изменение запрещено';
            return false;
        }
    }

	$blockFields = (new CrmFieldBlock())->getList([
		'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
	]);

    $checkFields = array_column($blockFields, 'UF_FIELD');

    $hl = new Highload('EntityItemBlock');
    $arItem = $hl->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
        'UF_ENTITY_ID' => $arFields['ID'],
        'UF_STAGE_ID' => $oldData['STAGE_ID']
    ]);
    if (count($arItem) > 0)
    {
        $arItem = current($arItem);
        $checkFields = array_merge($checkFields, \Bitrix\Main\Web\Json::decode($arItem['UF_BLOCKED_FIELDS']));
    }

    /*if ($oldData['ASSIGNED_BY_ID'] == $currentUserId || in_array($currentUserId, $oldData[$responsibleField]))
	{
		return $arFields;
	}*/


    if (in_array($currentUserId, $responsible))
    {
        return $arFields;
    }

	foreach ($arFields as $fieldName => $fieldValue)
    {
    	if (in_array($fieldName, $checkFields)) 
    	{
    		if ($oldData[$fieldName] != $fieldValue) 
    		{
    			$arFields['RESULT_MESSAGE'] = 'изменение поля  ' . $fieldName . ' запрещено';
    			return false;
    		}
    	}
    }

	if($arFields['UF_REMARKETING_COUNT'] && $oldData['UF_REMARKETING_COUNT'] != $arFields['UF_REMARKETING_COUNT'])
	{
		$arFields['RESULT_MESSAGE'] = 'изменение поля "Ремаркетинг: кол-во" запрещено';
		return false;
	}

    return $arFields;

});


\Bitrix\Main\EventManager::getInstance()->addEventHandler('crm', 'OnBeforeCrmLeadUpdate', function (&$arFields) {

	$currentUserId = CurrentUser::get()->getId();
	$responsibleField = LEAD_RESPONSIBLE_FIELD;

    $oldData = \Bitrix\Crm\LeadTable::getList([
		'select' => ['*','UF_*'],
		'filter' => [
			'ID' => $arFields['ID']
		]
	])->fetch();

    $hl = new Highload('StageLock');
    $arItem = $hl->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Lead
    ]);

    $stageLock = current($arItem);

    $hlResponsible = new Highload('AcceptedResponsible');
    $arItems = $hlResponsible->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
    ]);

	$responsible = [];

	foreach($arItems as $item)
	{
		if($item['UF_RESPONSIBLE'])
		{
			$responsible = array_merge($responsible, \Bitrix\Main\Web\Json::decode($item['UF_RESPONSIBLE']));
		}
	}

    if (!in_array($currentUserId, $responsible) && !empty($stageLock['UF_STAGE_ID']) && !empty($arFields['STATUS_ID'])) {

        if (Helper::isLockStage('STATUS', $arFields['STATUS_ID'], $oldData['STATUS_ID'], $stageLock['UF_STAGE_ID'])) 
        {
            $arFields['RESULT_MESSAGE'] = 'изменение запрещено';
            return false;
        }
    }

	$blockFields = (new CrmFieldBlock())->getList([
		'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
		'UF_STAGE_ID' => $oldData['STATUS_ID']
	]);
    $checkFields = array_column($blockFields, 'UF_FIELD');

    $hl = new Highload('EntityItemBlock');
    $arItem = $hl->getList([
        'UF_ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
        'UF_ENTITY_ID' => $arFields['ID'],
        'UF_STAGE_ID' => $oldData['STATUS_ID']
    ]);
    if (count($arItem) > 0)
    {
        $arItem = current($arItem);
        $checkFields = array_merge($checkFields, \Bitrix\Main\Web\Json::decode($arItem['UF_BLOCKED_FIELDS']));
    }

    /*if ($oldData['ASSIGNED_BY_ID'] == $currentUserId || in_array($currentUserId, $oldData[$responsibleField]))
	{
		return $arFields;
	}*/
    if (in_array($currentUserId, $responsible))
    {
        return $arFields;
    }

	foreach ($arFields as $fieldName => $fieldValue)
    {
    	if (in_array($fieldName, $checkFields)) 
    	{
    		if ($oldData[$fieldName] != $fieldValue) 
    		{
    			$arFields['RESULT_MESSAGE'] = 'изменение поля  ' . $fieldName . ' запрещено';
    			return false;
    		}
    	}
    }

    return $arFields;

});


$asset = Asset::getInstance();

$files = glob($_SERVER['DOCUMENT_ROOT'] . '/local/assets/js/main/*.js');

foreach ($files as $file)
{
	$asset->addJs(str_replace('home/bitrix/www/', '', $file));
}




// Регистрация приложения для кастомизация полей товаров на детальной странице предложения
AddEventHandler("main", "OnBeforeProlog", "MyOnBeforePrologHandlerFormCustomizer", 50);
function MyOnBeforePrologHandlerFormCustomizer()
{
    global $APPLICATION;
    // подключаем приложение, при условии, если находимся на детальной странице создания Заказа и пользователь является менеджером магазина

    global $USER;
    if( in_array('15',$USER->GetUserGroupArray()) ) {
        if( preg_match("~\/crm\/type\/7\/details\/[0-9]+\/~", $APPLICATION->GetCurPage(false))  ) {
            // зарегистрируем наше приложение и проинициализируем его
            CJSCore::RegisterExt(
                "products.form.customizer",
                array(
                    "js" => "/local/assets/js/productsFormCustomizer/productsFormCustomizer.js",
                    "css" => "/local/assets/js/productsFormCustomizer/productsFormCustomizer.css",
                    "rel" => ['popup', 'ajax', 'fx', 'ls', 'date', 'json', 'window','jquery'],
                )
            );
            CJSCore::Init(['products.form.customizer']);

            $asset = \Bitrix\Main\Page\Asset::getInstance();
            $asset->addString('<script>BX.ready(function() { BX.ProductsFormCustomizer.initCustomization() });</script>');
        }        
    }
}


