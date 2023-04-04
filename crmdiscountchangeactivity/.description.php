<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = array(
	"NAME" => GetMessage("ORDER_DISCOUNT_CHANGE_ACTIVITY_NAME"),	// BPDAF_DESCR_NAME2   ORDER
	"DESCRIPTION" => GetMessage("ORDER_DISCOUNT_CHANGE_ACTIVITY_DESCRIPTION"),
	"TYPE" => "activity",
	"CLASS" => "CrmDiscountChangeActivity",
	"JSCLASS" => "BizProcActivity",
	"CATEGORY" => array(
		'ID' => 'document',
		"OWN_ID" => 'custom',
		"OWN_NAME" => 'Custom',
	)
);
?>
