<?php
/**
 ***********************************************************************************************
 * Show history of key field changes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * key_id           : If set only show the key field history of that key
 * filter_date_from : is set to actual date,  if no date information is delivered
 * filter_date_to   : is set to 31.12.9999, if no date information is delivered
 *
 *****************************************************************************/

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;
use Plugins\KeyManager\classes\Service\Keys;

require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/common_function.php');

// calculate default date from which the key fields history should be shown
$filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
$filterDateFrom->modify('-'.$gSettingsManager->getInt('contacts_field_history_days').' day');

$getKeyId    = admFuncVariableIsValid($_GET, 'key_id',           'int');
$getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
$getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to',   'date', array('defaultValue' => DATE_NOW));

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);

$user = new User($gDb, $gProfileFields);

$headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($keys->getValue('KEYNAME')));

// if profile log is activated then the key field history will be shown otherwise show error
if (!$gSettingsManager->getBool('changelog_module_enabled'))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// add page to navigation history
$gNavigation->addUrl(CURRENT_URL, $headline);

// filter_date_from and filter_date_to can have different formats
// now we try to get a default format for intern use and html output
$objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
if ($objDateFrom === false)
{
    // check if date has system format
    $objDateFrom = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
    if($objDateFrom === false)
    {
        $objDateFrom = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
    }
}

$objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
if ($objDateTo === false)
{
    // check if date has system format
    $objDateTo = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
    if($objDateTo === false)
    {
        $objDateTo = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
    }
}

// DateTo should be greater than DateFrom
if ($objDateFrom > $objDateTo)
{
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gSettingsManager->getString('system_date'));
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gSettingsManager->getString('system_date'));

// create select statement with all necessary data
$sql = 'SELECT kml_kmk_id, kml_kmf_id,  kml_usr_id_create, kml_timestamp_create, kml_value_old, kml_value_new
          FROM '.TBL_KEYMANAGER_LOG.'
         WHERE kml_timestamp_create BETWEEN ? AND ? 
           AND kml_kmk_id = ?
      ORDER BY kml_timestamp_create DESC';
      
$fieldHistoryStatement = $gDb->queryPrepared($sql, array($dateFromIntern.' 00:00:00', $dateToIntern.' 23:59:59', $getKeyId));

if ($fieldHistoryStatement->rowCount() === 0)
{
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();
    $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
    $gMessage->show($gL10n->get('SYS_NO_ENTRIES'));
    // => EXIT
}

// create html page object
$page = new HtmlPage('plg-keymanager-keys-history', $headline);

// create filter menu with input elements for Startdate and Enddate                                                 //todo !!!!!!!!!!!!!!!!!!!!!!ohne Umweg �ber $form
$FilterNavbar = new HtmlNavbar('menu_profile_field_history_filter', '', null, 'filter');
$form = new HtmlForm('navbar_filter_form', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/system/keys_history.php', $page, array('type' => 'navbar', 'setFocus' => false));
$form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('key_id', '', $getKeyId, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
$FilterNavbar->addForm($form->show(false));
$page->addHtml($FilterNavbar->show());

//$table = new HtmlTable('profile_field_history_table', $page, true, true);
// Workaround für Admidio 5 (ohne Verwendung der Presenter-Klassen; $datatable darf nicht true sein)
$table = new HtmlTable('profile_field_history_table', $page, true, false);

$columnHeading = array();

$table->setDatatablesOrderColumns(array(array(5, 'desc')));

$columnHeading[] = $gL10n->get('SYS_FIELD');
$columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
$columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
$columnHeading[] = $gL10n->get('SYS_EDITED_BY');
$columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

$table->addRowHeadingByArray($columnHeading);

while ($row = $fieldHistoryStatement->fetch())
{
    $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['kml_timestamp_create']);
    $columnValues    = array();
	$columnValues[]  = convlanguagePKM($keys->getPropertyById((int) $row['kml_kmf_id'], 'kmf_name'));

    $kmlValueNew = $keys->getHtmlValue($keys->getPropertyById((int) $row['kml_kmf_id'], 'kmf_name_intern'), $row['kml_value_new']);
    if ($kmlValueNew !== '')
    {
    	if ($keys->getPropertyById((int) $row['kml_kmf_id'], 'kmf_name_intern') === 'RECEIVER')
    	{
    		$user->readDataById((int) $kmlValueNew);
    		$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
    	}
    	else 
    	{
    		$columnValues[] = $kmlValueNew;
    	}
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }
    
    $kmlValueOld = $keys->getHtmlValue($keys->getPropertyById((int) $row['kml_kmf_id'], 'kmf_name_intern'), $row['kml_value_old']);
    if ($kmlValueOld !== '')
   	{
   	 	if ($keys->getPropertyById((int) $row['kml_kmf_id'], 'kmf_name_intern') === 'RECEIVER')
   	 	{
   	 		$user->readDataById((int) $kmlValueOld);
   	 		$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
   	 	}
   	 	else
   	 	{
   	 		$columnValues[] = $kmlValueOld;
   	 	}
    }
    else
    {
        $columnValues[] = '&nbsp;';
    }
   
    $user->readDataById($row['kml_usr_id_create']);
    $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
    $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show());
$page->show();
