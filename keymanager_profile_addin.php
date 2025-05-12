<?php
/**
 ***********************************************************************************************
 * keymanager_profile_addin.php
 *
 * Shows issued keys in a member´s profile
 * 
 * Usage:
 * 
 * Add the following line to profile.php ( before $page->show(); ):
 * require_once(ADMIDIO_PATH . FOLDER_PLUGINS .'/keymanager/keymanager_profile_addin.php');
 *
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;

$getUserUuid   = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

require_once(__DIR__ . '/../../adm_program/system/common.php');                    
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

$pPreferences = new ConfigTablePKM();                  
$pPreferences->read();

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeysByUser($gCurrentOrgId, $user->getValue('usr_id'));

//eine Anzeige nur, wenn dieses Mitglied auch einen Schlüssel besitzt
if (sizeof($keys->keys) === 0)
{
    return;
}

$page->addHtml('<div class="card admidio-field-group" id="keymanager_box">
				<div class="card-header">'.$gL10n->get('PLG_KEYMANAGER_KEYMANAGER'));
$page->addHtml('<a class="admidio-icon-link float-right" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keymanager.php', array(
                        'export_and_filter' => true,
                        'show_all'          => true,
                        'same_side'         => true,
                        'filter_receiver'   => $user->getValue('usr_id'))). '">
                    <i class="fas fa-key" data-toggle="tooltip" title="'.$gL10n->get('PLG_KEYMANAGER_KEYMANAGER').'"></i>
    	        </a>');
$page->addHtml('</div><div id="keymanager_box_body" class="card-body">');

foreach ($keys->keys as $key)
{
    $keys->readKeyData($key['kmk_id'], $gCurrentOrgId);
    
	$page->addHtml('<li class= "list-group-item">');
	$page->addHtml('<div style="text-align: left;float:left;">');

	$content = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php', array('key_id' => $key['kmk_id'])).'">'.$keys->getValue('KEYNAME').'</a>';
	
	$contentAdd = $keys->getValue($pPreferences->config['Optionen']['profile_addin']);
	if (!empty($contentAdd))
	{
	    if (strlen($contentAdd) > 50)
	    {
	        $contentAdd = substr($contentAdd, 0, 50).'...';
	    }
	    $content .= ' - '.$contentAdd;
	}
	
	if ($key['kmk_former'])
	{
	    $content = '<s>'.$content.'</s>';
	}
	$page->addHtml($content);

	$page->addHtml('</div><div style="text-align: right;float:right;">');
	
	if (!empty($keys->getValue('RECEIVED_ON')))
	{
	    $content = $gL10n->get('PKM_RECEIVED_ON').' '.date('d.m.Y',strtotime($keys->getValue('RECEIVED_ON')));
	    if ($key['kmk_former'])
	    {
	        $content = '<s>'.$content.'</s>';
	    }
	    $page->addHtml($content.' ');
	}
	
	if ($pPreferences->isPffInst())
	{
	    $page->addHtml('<a class="admidio-icon-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_export_to_pff.php', array('key_id' => $key['kmk_id'])). '">
    	                       <i class="fas fa-print" data-toggle="tooltip" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_PRINT').'"></i>
    	                </a>');
	}
	if (isUserAuthorizedForPreferences())
	{
	    $page->addHtml('<a class="admidio-icon-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_delete.php', array('key_id' => $key['kmk_id'], 'key_former' => $key['kmk_former'])). '">
    	                       <i class="fas fa-minus-circle" data-toggle="tooltip" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_DELETE').'"></i>
    	                </a>');
	}
	
	$page->addHtml('</div>');//Float right
	$page->addHtml('<div style="clear:both"></div></li>');
}

$page->addHtml('</ul></div></div>');
//Move content to correct position by jquery
$page->addHtml('<script>$("#keymanager_box").insertBefore("#profile_roles_box");</script>');


