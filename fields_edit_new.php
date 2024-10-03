<?php
/**
 ***********************************************************************************************
 * Create and edit key fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
 
/******************************************************************************
 * Parameters:
 *
 * kmf_id : key field id that should be edited
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getKmfId = admFuncVariableIsValid($_GET, 'kmf_id', 'int');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
if ($getKmfId > 0)
{
    $headline = $gL10n->get('PLG_KEYMANAGER_KEYFIELD_EDIT');
}
else
{
    $headline = $gL10n->get('PLG_KEYMANAGER_KEYFIELD_CREATE');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

$keyField = new TableAccess($gDb, TBL_KEYMANAGER_FIELDS, 'kmf');

if ($getKmfId > 0)
{
	$keyField->readDataById($getKmfId);

    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if ($keyField->getValue('kmf_org_id') > 0
    && (int) $keyField->getValue('kmf_org_id') !== (int) $gCurrentOrgId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (isset($_SESSION['fields_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $keyField->setArray($_SESSION['fields_request']);
    unset($_SESSION['fields_request']);
}

// create html page object
$page = new HtmlPage('plg-keymanager-fields-edit-new', $headline);

$page->addJavascript('
    function setValueList() {
        if ($("#kmf_type").val() === "DROPDOWN" || $("#kmf_type").val() === "RADIO_BUTTON") {
            $("#kmf_value_list_group").show("slow");
            $("#kmf_value_list").attr("required", "required");
        } else {
            $("#kmf_value_list").removeAttr("required");
            $("#kmf_value_list_group").hide();
        }
    }

    setValueList();
    $("#kmf_type").click(function() { setValueList(); });',
    true
);

// show form
$form = new HtmlForm('key_fields_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_function.php', array('kmf_id' => $getKmfId, 'mode' => 1)), $page);

if ($keyField->getValue('kmf_system') == 1)
{
    $form->addInput('kmf_name', $gL10n->get('SYS_NAME'), $keyField->getValue('kmf_name', 'database'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED));
}
else
{
    $form->addInput('kmf_name', $gL10n->get('SYS_NAME'), $keyField->getValue('kmf_name', 'database'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED));
}

// show internal field name for information
if ($getKmfId > 0)
{
    $form->addInput('kmf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $keyField->getValue('kmf_name_intern'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC'));
}

$keyFieldText = array(
	'CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
    'DATE'         => $gL10n->get('SYS_DATE'),
    'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'),
    'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
    'NUMBER'       => $gL10n->get('SYS_NUMBER'),
    'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
    'TEXT'         => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
    'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
);
asort($keyFieldText);

if ($keyField->getValue('kmf_system') == 1)
{
    //bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
    $form->addInput('kmf_type', $gL10n->get('ORG_DATATYPE'), $keyFieldText[$keyField->getValue('kmf_type')],
              array('maxLength' => 30, 'property' => HtmlForm::FIELD_DISABLED));
}
else
{
    // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
    $form->addSelectBox('kmf_type', $gL10n->get('ORG_DATATYPE'), $keyFieldText,
                  array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $keyField->getValue('kmf_type')));
}

$form->addMultilineTextInput(
    'kmf_value_list', 
    $gL10n->get('ORG_VALUE_LIST'), 
    (string) $keyField->getValue('kmf_value_list', 'database'),
    6,
    array('property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC')
);

if ($keyField->getValue('kmf_system') != 1)
{
	$form->addCheckbox('kmf_mandatory', $gL10n->get('SYS_REQUIRED_INPUT'), (bool) $keyField->getValue('kmf_mandatory'),
	    array('property' => HtmlForm::FIELD_DEFAULT,  'icon' => 'fa-asterisk'));
}

$form->addMultilineTextInput(
    'kmf_description', 
    $gL10n->get('SYS_DESCRIPTION'), 
    $keyField->getValue('kmf_description'), 
    3
);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => 'offset-sm-3'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $keyField->getValue('kmf_usr_id_create'), $keyField->getValue('kmf_timestamp_create'),
    (int) $keyField->getValue('kmf_usr_id_change'), $keyField->getValue('kmf_timestamp_change')          
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

