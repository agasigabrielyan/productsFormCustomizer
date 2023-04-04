<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
CModule::IncludeModule("sale");
CModule::includeModule('crm');
\Bitrix\Main\Loader::includeModule('bizproc');
\Bitrix\Main\Loader::includeModule('crm');

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$requestAction = $request->getPost("action");

switch ($requestAction) {
    case "checkeditability":
        $products = getCatalogProducts();     // получим товары
        echo json_encode( $products );
        break;
    case "runbusinessprocess":
        $businessProccessRan = runBusinessProccessSomeDiscountsExceeds();       // запустим бизнес процесс
        echo json_encode( $businessProccessRan );
        break;
}








// метод получает товары каталога
function getCatalogProducts() {
    $goods = \Bitrix\Iblock\Elements\ElementProductsTable::getList([
        'select' => [ 'ID','NAME','EDITING_PRICE_' => 'EDITING_PRICE', 'ALLOWED_DISCOUNT_' => 'ALLOWED_DISCOUNT' ],
    ])->fetchAll();

    $goodsWithIdAsKey = [];
    foreach ($goods as $good) {
        $goodsWithIdAsKey[$good['ID']] = $good;
    }
    return $goodsWithIdAsKey;
}


// метод запускает бизнес-процесс согласования заказа(предложение), так как какая то скидка привысила допустимое значение для товара
function runBusinessProccessSomeDiscountsExceeds() {
    $businessProccessRan = false;
    $orderId = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getPost('orderId');

    $arErrorsTmp = [];
    $bzId = 485;

    if( CModule::IncludeModule('bizproc') && CModule::IncludeModule("crm") ) {
	try {
	    $wfid = \CBPDocument::StartWorkflow(
	     $bzId,
	     array('crm','Bitrix\Crm\Integration\BizProc\Document\Quote','QUOTE_' . $orderId),
	     array(),
	     $arErrorsTmp
	    );
	} catch(Exception $e) {
	    throw new Exception ('Почему то не создался бизнес процесс ' . $e);
	}
    }
    
    file_put_contents(
	__DIR__ . "/error.txt",
	print_r($arErrorsTmp, true)
    );

    if( $wfId>0 ) {
        $businessProccessRan = true;
    }

    return $businessProccessRan;
}

