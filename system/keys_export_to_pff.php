<?php
/**
 ***********************************************************************************************
 * Prepare print data for plugin FormFiller 
 *
 * @copyright The Admidio Team
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

use Admidio\Infrastructure\Utils\SecurityUtils;
use Plugins\KeyManager\classes\Config\ConfigTable;
use Plugins\KeyManager\classes\Service\Keys;

require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getKeyId = admFuncVariableIsValid($_GET, 'key_id',  'int');

$pkmArray = array();

$pPreferences = new ConfigTable();
$pPreferences->read();
$pPreferences->readPff();

if (substr_count($gNavigation->getUrl(), 'keys_export_to_pff') === 1)
{
	admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER. '/system/keymanager.php');
	// => EXIT
}

$headline = $gL10n->get('PLG_KEYMANAGER_KEY_PRINT');
$gNavigation->addUrl(CURRENT_URL, $headline);

if (!array_key_exists($pPreferences->config['Optionen']['interface_pff'], $pPreferences->configpff['Formular']['desc']))
{
    $gMessage->show($gL10n->get('PLG_KEYMANAGER_PFF_CONFIG_NOT_FOUND'));
}
else 
{
    $pkmArray['form_id'] = $pPreferences->config['Optionen']['interface_pff'];
}

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);
    	
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
    
    $pkmArray['kmf-'. $kmfNameIntern] = $content;
}

admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.$pPreferences->pffDir().'/createpdf.php', $pkmArray));
    		
    		