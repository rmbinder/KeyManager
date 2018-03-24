<?php
/**
 ***********************************************************************************************
 * Delete a key field
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *        
 * mode       : 1 - Menu and preview of the key field who should be deleted
 *              2 - Delete a key field
 * kmf_id     : ID of the key field who should be deleted
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$getMode  = admFuncVariableIsValid($_GET, 'mode',   'numeric', array('defaultValue' => 1));
$getKmfId = admFuncVariableIsValid($_GET, 'kmf_id', 'int');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

$keyField = new TableAccess($gDb, TBL_KEYMANAGER_FIELDS, 'kmf', $getKmfId );

switch ($getMode)
{
    case 1:
    		
    	$headline = $gL10n->get('PLG_KEYMANAGER_KEYFIELD_DELETE');
    		
    	// create html page object
    	$page = new HtmlPage($headline);
    	
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
    	
    	// add current url to navigation stack
    	$gNavigation->addUrl(CURRENT_URL, $headline);
    		
    	// create module menu with back link
    	$organizationNewMenu = new HtmlNavbar('menu_keyfield_delete', $headline, $page);
    	$organizationNewMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    	$page->addHtml($organizationNewMenu->show(false));
    		
    	$page->addHtml('<p class="lead">'.$gL10n->get('PLG_KEYMANAGER_KEYFIELD_DELETE_DESC').'</p>');
    		
    	// show form
    	$form = new HtmlForm('keyfield_delete_form', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_delete.php?kmf_id='.$getKmfId.'&mode=2', $page);
    	
    	$form->addInput('kmf_name', $gL10n->get('SYS_NAME'), $keyField->getValue('kmf_name', 'database'),
    			array('maxLength' => 100, 'property' => FIELD_DISABLED));
    	
    	// show internal field name for information
    	$form->addInput('kmf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $keyField->getValue('kmf_name_intern'),
    			array('maxLength' => 100, 'property' => FIELD_DISABLED));
    	
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
    
    	$form->addInput('kmf_type', $gL10n->get('ORG_DATATYPE'), $keyFieldText[$keyField->getValue('kmf_type')],
    				array('maxLength' => 30, 'property' => FIELD_DISABLED));
    	
    	$form->addMultilineTextInput('kmf_value_list', $gL10n->get('ORG_VALUE_LIST'),
    			$keyField->getValue('kmf_value_list', 'database'), 6,
    			array('property' => FIELD_DISABLED));
    	
    	$form->addMultilineTextInput('kmf_description', $gL10n->get('SYS_DESCRIPTION'),
    			$keyField->getValue('kmf_description'), 3,
    			array( 'property' => FIELD_DISABLED));
    	
    	$form->addSubmitButton('btn_delete', $gL10n->get('SYS_DELETE'), array('icon' => THEME_URL .'/icons/delete.png', 'class' => 'col-sm-offset-3'));
    		
    	// add form to html page and show page
    	$page->addHtml($form->show(false));
    	$page->show();
    	break;
    		
    case 2:
    	
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_LOG.'
        		      WHERE kml_kmf_id = '.$getKmfId.' ';
    	$gDb->query($sql);
    	
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_DATA.'
        		      WHERE kmd_kmf_id = '.$getKmfId.' ';
    	$gDb->query($sql);
    	
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_FIELDS.'
        		 WHERE kmf_id = '.$getKmfId.'
    			   AND ( kmf_org_id = '.$gCurrentOrganization->getValue('org_id').'
                    OR kmf_org_id IS NULL ) ';
    	$gDb->query($sql);
    	
    	// go back to key view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    	$gMessage->show($gL10n->get('PLG_KEYMANAGER_KEYFIELD_DELETED'));

    	break;
    	// => EXIT
}
    		
    		
    		
    		