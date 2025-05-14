<?php
/**
 ***********************************************************************************************
 * Delete a key or make a key to the former
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode       : 1 - Menu and preview of the key who should be deleted or made to the former
 *              2 - Delete a key
 *              3 - Make the key to the former
 * key_id     : ID of the key who should be deleted or made to the former
 * key_former : 0 - Key is activ
 * 			    1 - Key is already made to the former
 *
 *****************************************************************************/

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getMode      = admFuncVariableIsValid($_GET, 'mode',      'numeric', array('defaultValue' => 1));
$getKeyId     = admFuncVariableIsValid($_GET, 'key_id',    'int');
$getKeyFormer = admFuncVariableIsValid($_GET, 'key_former', 'bool');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);
$user = new User($gDb, $gProfileFields);

switch ($getMode)
{
    case 1:
    		
    	$headline = $gL10n->get('PLG_KEYMANAGER_KEY_DELETE');
    		
    	// add current url to navigation stack
    	$gNavigation->addUrl(CURRENT_URL, $headline);
    		
    	// create html page object
    	$page = new HtmlPage('plg-keymanager-keys-delete', $headline);
    	
    	$page->addHtml('<p class="lead">'.$gL10n->get('PLG_KEYMANAGER_KEY_DELETE_DESC').'</p>');
    		
    	// show form
    	$form = new HtmlForm('key_delete_form', '', $page);
    	
    	foreach ($keys->mKeyFields as $keyField)
    	{
    		$kmfNameIntern = $keyField->getValue('kmf_name_intern');
    	
    		$content = $keys->getValue($kmfNameIntern, 'database');
    		if ($kmfNameIntern === 'RECEIVER' && strlen($content) > 0)
    		{
    			$user->readDataById($content);
    			$content = $user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME');
    		}
    		elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'DATE')
    		{
    			$content = $keys->getHtmlValue($kmfNameIntern, $content);
    		}
    		elseif ( $keys->getProperty($kmfNameIntern, 'kmf_type') === 'DROPDOWN'
    				|| $keys->getProperty($kmfNameIntern, 'kmf_type') === 'RADIO_BUTTON')
    		{
    			$arrListValues = $keys->getProperty($kmfNameIntern, 'kmf_value_list', 'text');
    			$content = '';
    			if ($content > 0)
    			{
    			    $content = $arrListValues[$content];
    			}
    		}
    		
    		$form->addInput(
    			'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'),
    			convlanguagePKM($keys->getProperty($kmfNameIntern, 'kmf_name')),
    			$content,
    			array('property' => HtmlForm::FIELD_DISABLED)
    		);
    	 }	
    	
    	$form->addButton('btn_delete', $gL10n->get('SYS_DELETE'), array('icon' => 'bi bi-trash', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_delete.php', array('key_id' => $getKeyId, 'mode' => 2)), 'class' => 'btn-primary offset-sm-3'));
    	if (!$getKeyFormer)
    	{
    		$form->addButton('btn_former', $gL10n->get('PLG_KEYMANAGER_FORMER'), array('icon' => 'bi bi-person-fill-dash', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_delete.php', array('key_id' => $getKeyId, 'mode' => 3)), 'class' => 'btn-primary offset-sm-3'));
    		$form->addCustomContent('', '<br />'.$gL10n->get('PLG_KEYMANAGER_KEY_MAKE_TO_FORMER'));
    	}
    	
    	// add form to html page and show page
    	$page->addHtml($form->show(false));
    	$page->show();
    	break;
    		
    case 2:
    		
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_LOG.'
        	          WHERE kml_kmk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    	
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_DATA.'
        		      WHERE kmd_kmk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    
    	$sql = 'DELETE FROM '.TBL_KEYMANAGER_KEYS.'
        		      WHERE kmk_id = ? -- $getKeyId
    			        AND ( kmk_org_id = ? -- $gCurrentOrgId
                         OR kmk_org_id IS NULL ) ';
    	$gDb->queryPrepared($sql, array($getKeyId, $gCurrentOrgId));
    	
    	// go back to key view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
		$gMessage->show($gL10n->get('PLG_KEYMANAGER_KEY_DELETED'));

    	break;
    	// => EXIT
    	
    case 3:
    	
    	$sql = 'UPDATE '.TBL_KEYMANAGER_KEYS.'
                   SET kmk_former = 1
                 WHERE kmk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    		
    	// go back to key view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
    	$gMessage->show($gL10n->get('PLG_KEYMANAGER_KEY_MADE_TO_FORMER'));
    	break;
    	// => EXIT
}
    		
    		
    		
    		