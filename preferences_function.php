<?php
/**
 ***********************************************************************************************
 * Verarbeiten der Einstellungen des Admidio-Plugins KeyManager
 * 
 * @copyright 2004-2018 The Admidio Team
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
if (!checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
{
	$gMessage->setForwardUrl($gHomepage, 3000);
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
            	              
        	case 'plugin_control':
            	unset($pPreferences->config['Pluginfreigabe']);
    			$pPreferences->config['Pluginfreigabe']['freigabe'] = $_POST['freigabe'];
    			$pPreferences->config['Pluginfreigabe']['freigabe_config'] = $_POST['freigabe_config'];
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
    $page = new HtmlPage($headline);
    
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
    
    // create module menu with back link
    $organizationNewMenu = new HtmlNavbar('menu_deinstallation', $headline, $page);
    $organizationNewMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    $page->addHtml($organizationNewMenu->show(false));
    
    $page->addHtml('<p class="lead">'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION_FORM_DESC').'</p>');

    // show form
    $form = new HtmlForm('deinstallation_form', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php?mode=3', $page);
    $radioButtonEntries = array('0' => $gL10n->get('PLG_KEYMANAGER_DEINST_ACTORGONLY'), '1' => $gL10n->get('PLG_KEYMANAGER_DEINST_ALLORG') );
    $form->addRadioButton('deinst_org_select',$gL10n->get('PLG_KEYMANAGER_ORG_CHOICE'), $radioButtonEntries);    
    $form->addSubmitButton('btn_deinstall', $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION'), array('icon' => THEME_URL .'/icons/delete.png', 'class' => 'col-sm-offset-3'));
    
    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
    break;
    
case 3:
    
	$gNavigation->addUrl(CURRENT_URL);
	$gMessage->setForwardUrl($gHomepage);		

	$resMes = $gL10n->get('PLG_KEYMANAGER_DEINST_STARTMESSAGE');
	$resMes .= $pPreferences->deleteKeyData($_POST['deinst_org_select']);
	$resMes .= $pPreferences->deleteConfigData($_POST['deinst_org_select']);
	$resMes .= $gL10n->get('PLG_KEYMANAGER_DEINST_ENDMESSAGE');
	
	$gMessage->show($resMes );
   	break;
}
