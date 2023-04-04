<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
Loader::includeModule("iblock");
Loader::includeModule('crm');

class CBPCrmDiscountChangeActivity extends CBPActivity
{
	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = array(
			'entityId' => '',
		);
	}

	protected function ReInitialize()
	{
		parent::ReInitialize();
	}

	public function Execute()
	{
		// идентификатор QUOTE требуется
		$some = "";
		$products = $this->getDealProducts($this->entityId);

		// для каждого такого товара, проверим еще раз если скидка превышает, то делаем обновление скидки до ALLOWED_DISCOUNT
		$arProductFieldsToBeChanged = [];
		foreach( $products as $key => $product ) {
			if(  ( floatval($product['DISCOUNT_RATE']) > floatval($product['ALLOWED_DISCOUNT_VALUE']) ) && ($product['ALLOWED_DISCOUNT_VALUE'] !== null) ) {
				// задаем значение скидки на товар равным допустимому, если пользователь ввел значение больше допустимого
				$newDiscount = ( floatval($product['DISCOUNT_RATE']) > floatval($product['ALLOWED_DISCOUNT_VALUE']) ) && ($product['ALLOWED_DISCOUNT_VALUE'] !== null) ? floatval($product['ALLOWED_DISCOUNT_VALUE']) : floatval($product['DISCOUNT_RATE']);

				// при измеении скидки изменятся Сумма скидки и Цена товара, пересчитаем на основе новой скидки на единицу товара
				$newDiscountSum = (floatval($product['PRICE_NETTO']) / 100 * $newDiscount);
				$newPrice = floatval($product['PRICE_NETTO']) - $newDiscountSum;

				$arProductFieldsToBeChanged[] = [
					'ID' => $product['ID'],
					'OWNER_ID' => $product['OWNER_ID'],
					'OWNER_TYPE' => $product['OWNER_TYPE'],
					'PRODUCT_ID' => $product['PRODUCT_ID'],
					'PRODUCT_NAME' => $product['PRODUCT_NAME'],
					'PRICE' => $newPrice,                           // меняем это поле
					'PRICE_ACCOUNT' => $newPrice,                   // меняем это поле
					'PRICE_EXCLUSIVE' => $newPrice,                 // меняем это поле
					'PRICE_NETTO' => $product['PRICE_NETTO'],
					'PRICE_BRUTTO' => $product['PRICE_BRUTTO'],
					'QUANTITY' => $product['QUANTITY'],
					'DISCOUNT_TYPE_ID' => $product['DISCOUNT_TYPE_ID'],
					'DISCOUNT_RATE' => $newDiscount,
					'DISCOUNT_SUM' => $newDiscountSum,
					'TAX_RATE' => $product['TAX_RATE'],
					'TAX_INCLUDED' => $product['TAX_INCLUDED'],
					'CUSTOMIZED' => $product['CUSTOMIZED'],
					'MEASURE_CODE' => $product['MEASURE_CODE'],
					'MEASURE_NAME' => $product['MEASURE_NAME'],
					'SORT' => $product['SORT'],
					'XML_ID' => $product['XML_ID'],
					'TYPE' => $product['TYPE'],
				];
			} else {
				$arProductFieldsToBeChanged[] = [
					'ID' => $product['ID'],
					'OWNER_ID' => $product['OWNER_ID'],
					'OWNER_TYPE' => $product['OWNER_TYPE'],
					'PRODUCT_ID' => $product['PRODUCT_ID'],
					'PRODUCT_NAME' => $product['PRODUCT_NAME'],
					'PRICE' => $product['PRICE'],
					'PRICE_ACCOUNT' => $product['PRICE_ACCOUNT'],
					'PRICE_EXCLUSIVE' => $product['PRICE_EXCLUSIVE'],
					'PRICE_NETTO' => $product['PRICE_NETTO'],
					'PRICE_BRUTTO' => $product['PRICE_BRUTTO'],
					'QUANTITY' => $product['QUANTITY'],
					'DISCOUNT_TYPE_ID' => $product['DISCOUNT_TYPE_ID'],
					'DISCOUNT_RATE' => $product['DISCOUNT_RATE'],
					'DISCOUNT_SUM' => $product['DISCOUNT_SUM'],
					'TAX_RATE' => $product['TAX_RATE'],
					'TAX_INCLUDED' => $product['TAX_INCLUDED'],
					'CUSTOMIZED' => $product['CUSTOMIZED'],
					'MEASURE_CODE' => $product['MEASURE_CODE'],
					'MEASURE_NAME' => $product['MEASURE_NAME'],
					'SORT' => $product['SORT'],
					'XML_ID' => $product['XML_ID'],
					'TYPE' => $product['TYPE'],
				];
			}

		}

		if( count($arProductFieldsToBeChanged)>0 ) {
			$result = CCrmProductRow::SaveRows("Q",7, $arProductFieldsToBeChanged);
		}


	}

	public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
	{
		return [];
	}


	// метод, который получит все товары конкретного QUOTE (ЗАКАЗ, ПРЕДЛОЖЕНИЕ)
	private function getDealProducts( $quoteId ) {
		$products = \Bitrix\Crm\ProductRowTable::getList([
			'select' => [
				'*',
				'OFFER_ID' => 'offers.ID',
				'OFFER_NAME'=>'offers.NAME',
				'CML2_LINK_' => 'offers.CML2_LINK',
				'GOOD_ID' => 'products.ID',
				'GOOD_NAME' => 'products.NAME',
				'ALLOWED_DISCOUNT_' => 'products.ALLOWED_DISCOUNT'
			],
			'filter' => ['OWNER_ID' => $quoteId],
			'runtime' => [
				'offers' => [
					'data_type' => \Bitrix\Iblock\Elements\ElementOffersTable::getEntity(),
					'reference' => [
						'this.PRODUCT_ID' => 'ref.ID'
					]
				],
				'products' => [
					'data_type' => \Bitrix\Iblock\Elements\ElementProductsTable::getEntity(),
					'reference' => [
						'this.CML2_LINK_IBLOCK_GENERIC_VALUE' => 'ref.ID'
					]
				]
			]
		])->fetchAll();

		return $products;
	}

	public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $currentValues = null, $formName = "")
	{
		if (!CModule::IncludeModule("crm"))
			return '';

		$runtime = CBPRuntime::GetRuntime();

		$arMap = array(
			'entityId' => 'entityId',
		);

		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

		if (!is_array($currentValues))
		{		
			foreach ($arMap as $k => $v)
			{
				$currentValues[$arMap[$k]] = isset($arCurrentActivity["Properties"][$k]) ? $arCurrentActivity["Properties"][$k] : '';
			}
		} 

		return $runtime->ExecuteResourceFile(
			__FILE__,
			"properties_dialog.php",
			array(
				"arCurrentValues" => $currentValues,
				"formName" => $formName,
			)
		);
	}

	public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $currentValues, &$arErrors)
	{
		$arErrors = array();

		$arMap = array(
			'entityId' => 'entityId',
		);

		$arProperties = [];
		foreach ($arMap as $key => $value)
		{
			$arProperties[$value] = $currentValues[$key];
		}

		$arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
		if (count($arErrors) > 0)
			return false;

		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
		$arCurrentActivity["Properties"] = $arProperties;

		return true;
	}
}
?>
