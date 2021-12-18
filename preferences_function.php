<?php
/**
 ***********************************************************************************************
 * Verarbeiten der Einstellungen des Admidio-Plugins KeyManager
 * 
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
 
 /******************************************************************************
 * Parameters:
 *
 * mode:  1 - Save preferences
 *        2 - show dialog for deinstallation
 *        3 - deinstallation
 * form     - The name of the form preferences that were submitted.
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('defaultValue' => 1));
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
if ($getMode === 1)
{
    $gMessage->showHtmlTextOnly(true);
}

switch ($getMode)
{
case 1:
	try
	{
		switch ($getForm)
    	{
       		case 'interface_pff':
 	        	$pPreferences->config['Optionen']['interface_pff'] = $_POST['interface_pff'];	
            	break;  

            case 'access_preferences':
                unset($pPreferences->config['access']);
                $pPreferences->config['access']['preferences'] = isset($_POST['access_preferences']) ? array_unique(array_merge($_POST['access_preferences'], $pPreferences->config_default['access']['preferences'])) : $pPreferences->config_default['access']['preferences'];
                break;            

        	default:
           		$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    	}
	}
	catch(AdmException $e)
	{
		$e->showText();
	}    
    
	$pPreferences->save();

	echo 'success';
	break;

case 2:
	
	$headline = $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION');
	 
	    // create html page object
    $page = new HtmlPage('plg-keymanager-deinstallation', $headline);
    
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
    
    $page->addHtml('<p class="lead">'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION_FORM_DESC').'</p>');

    // show form
    $form = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('mode' => 3)), $page);
    $radioButtonEntries = array('0' => $gL10n->get('PLG_KEYMANAGER_DEINST_ACTORGONLY'), '1' => $gL10n->get('PLG_KEYMANAGER_DEINST_ALLORG') );
    $form->addRadioButton('deinst_org_select',$gL10n->get('PLG_KEYMANAGER_ORG_CHOICE'), $radioButtonEntries);    
    $form->addSubmitButton('btn_deinstall', $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'offset-sm-3'));
    
    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
    break;
    
case 3:
    
	$gNavigation->clear();
	$gMessage->setForwardUrl($gHomepage);		

	$resMes = $gL10n->get('PLG_KEYMANAGER_DEINST_STARTMESSAGE');
	$resMes .= $pPreferences->deleteKeyData($_POST['deinst_org_select']);
	$resMes .= $pPreferences->deleteConfigData($_POST['deinst_org_select']);
	$resMes .= $gL10n->get('PLG_KEYMANAGER_DEINST_ENDMESSAGE');
	
	$gMessage->show($resMes );
   	break;
}
