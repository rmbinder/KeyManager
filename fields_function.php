<?php
/**
 ***********************************************************************************************
 * Various functions for key fields
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * kmf_id   : key field id
 * mode     : 1 - create or edit key field
 *            2 - delete key field
 *            4 - change sequence of key field
 * sequence : new sequence für key field
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getKmfId    = admFuncVariableIsValid($_GET, 'kmf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array('UP', 'DOWN')));

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
{
    $gMessage->setForwardUrl($gHomepage, 3000);
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$keyField = new TableAccess($gDb, TBL_KEYMANAGER_FIELDS, 'kmf');

if ($getKmfId > 0)
{
	$keyField->readDataById($getKmfId);

    // check if key field belongs to actual organization
    if ($keyField->getValue('kmf_org_id') > 0
    && (int) $keyField->getValue('kmf_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getMode === 1)
{
     // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_POST;

    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if ($keyField->getValue('kmf_system') == 0 && $_POST['kmf_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
        // => EXIT
    }

    if ($keyField->getValue('kmf_system') == 0 && $_POST['kmf_type'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATATYPE')));
        // => EXIT
    }

    if (($_POST['kmf_type'] === 'DROPDOWN' || $_POST['kmf_type'] === 'RADIO_BUTTON')
    && $_POST['kmf_value_list'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_VALUE_LIST')));
        // => EXIT
    }

    if (isset($_POST['kmf_name']) && $keyField->getValue('kmf_name') !== $_POST['kmf_name'])
    {    
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_KEYMANAGER_FIELDS.'
                 WHERE kmf_name   = \''.$_POST['kmf_name'].'\'
                   AND kmf_id    <> '.$getKmfId ;
        $statement = $gDb->query($sql);
        
        if ((int) $statement->fetchColumn() > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
        }
    }

    // make html in description secure
    $_POST['kmf_description'] = admFuncVariableIsValid($_POST, 'kmf_description', 'html');

    // POST Variablen in das KeyField-Objekt schreiben
    foreach ($_POST as $key => $value)
    {
        if (strpos($key, 'kmf_') === 0)
        {
            $keyField->setValue($key, $value);
        }
    }

    $keyField->setValue('kmf_org_id', (int) $gCurrentOrganization->getValue('org_id'));
    
    if ($keyField->isNewRecord())
    {
    	$keyField->setValue('kmf_name_intern', getNewNameIntern($keyField->getValue('kmf_name', 'database'), 1));
    	$keyField->setValue('kmf_sequence', genNewSequence());
    }
    
    // Daten in Datenbank schreiben
    $returnCode = $keyField->save();

    if ($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['fields_request']);

    // zum Menue Schluesselfelder zurueck
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
elseif ($getMode === 2)
{
    // Feld loeschen
    if ($keyField->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif ($getMode === 4)
{
    // Feldreihenfolge aktualisieren
	moveSequence($getSequence);
    exit();
}

