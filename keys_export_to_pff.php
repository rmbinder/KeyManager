<?php
/**
 ***********************************************************************************************
 * Prepare print data for plugin FormFiller 
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * key_id     : ID of the key who should be printed
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getKeyId = admFuncVariableIsValid($_GET, 'key_id',  'int');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

if (substr_count($gNavigation->getUrl(), 'keys_export_to_pff') === 1)
{
	admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . $plugin_folder. '/keymanager.php');
	// => EXIT
}
    
$keys = new Keys($gDb, $gCurrentOrganization->getValue('org_id'));
$keys->readKeyData($getKeyId, $gCurrentOrganization->getValue('org_id'));
    		
$headline = $gL10n->get('PLG_KEYMANAGER_PREPARE_DATA_FOR_PRINT');
    		
// create html page object
$page = new HtmlPage($headline);
    		
// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);
    		
$page->addJavascript('
	$(document).ready(function() {
    	$("#export_to_pff_form").submit();
    });',
    true
);
    	
// show form
$form = new HtmlForm('export_to_pff_form', ADMIDIO_URL . FOLDER_PLUGINS .'/formfiller/createpdf.php', $page, array('type' => 'filter'));
    	
foreach($keys->mKeyFields as $keyField)
{    		
	$kmfNameIntern = $keyField->getValue('kmf_name_intern');
    	
	$content = $keys->getValue($kmfNameIntern, 'database');
    	
    if ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'DATE')
    {
    	$content = $keys->getHtmlValue($kmfNameIntern, $content);
    }
    elseif ( $keys->getProperty($kmfNameIntern, 'kmf_type') === 'DROPDOWN'
    	  || $keys->getProperty($kmfNameIntern, 'kmf_type') === 'RADIO_BUTTON')
    {
    	$arrListValues = $keys->getProperty($kmfNameIntern, 'kmf_value_list', 'text');
    	$content = $arrListValues[$content];
    }
    elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'CHECKBOX')
    {
    	if ($content == 1)
    	{
    		$content = $gL10n->get('SYS_YES');
    	}
    	else
    	{
    		$content = $gL10n->get('SYS_NO');
    	}
    }
    		
    $form->addInput(
    	'kmf-'. $kmfNameIntern,
    	convlanguagePKM($keys->getProperty($kmfNameIntern, 'kmf_name')),
    	$content,
    	array(  'property' => FIELD_HIDDEN)
    );
}
$form->addInput('form_id', '', $pPreferences->config['Optionen']['interface_pff'], array(  'property' => FIELD_HIDDEN));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
    		
    		