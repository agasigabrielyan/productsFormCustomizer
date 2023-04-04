<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/**
 * @var $arCurrentValues
 */
CJSCore::Init('jquery2');

use Devconsult\Entity\Field;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Main\IO\Path;
use Bitrix\Main\Page\Asset;
use Devconsult\Iblock\Helper;

//Asset::getInstance()->addJs(Path::normalize('/local/activities/custom/crmblockfieldactivity/script.js'));

$types = TypeTable::getList()->fetchAll();
$fields = (new Field(\CCrmOwnerType::Lead))->getList();
$structure = Helper::getIntranetStructure();
?>

<tr>
    <td align="right" width="40%"><?= GetMessage("ENTITY_ID") ?>:</td>
    <td width="60%">
        <?=\CBPDocument::ShowParameterField('string', 'entityId', $arCurrentValues['entityId'], array('size' => 30))?>
    </td>
</tr>
