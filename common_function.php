<?php
/**
 ***********************************************************************************************
 * Common functions for the Admidio plugin KeyManager
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');

global $g_tbl_praefix;

$plugin_folder = '/'.substr(__DIR__,strrpos(__DIR__,DIRECTORY_SEPARATOR)+1);

if (!defined('TBL_KEYMANAGER_FIELDS'))
{
	define('TBL_KEYMANAGER_FIELDS',  $g_tbl_praefix . '_keymanager_fields');
}
if (!defined('TBL_KEYMANAGER_DATA'))
{
	define('TBL_KEYMANAGER_DATA',    $g_tbl_praefix . '_keymanager_data');
}
if (!defined('TBL_KEYMANAGER_KEYS'))
{
	define('TBL_KEYMANAGER_KEYS',    $g_tbl_praefix . '_keymanager_keys');
}
if (!defined('TBL_KEYMANAGER_LOG'))
{
	define('TBL_KEYMANAGER_LOG',     $g_tbl_praefix . '_keymanager_log');
}

/**
 * Funktion liest die Role-ID einer Rolle aus
 * @param   string  $role_name Name der zu pruefenden Rolle
 * @return  int     rol_id  Rol_id der Rolle; 0, wenn nicht gefunden
 */
function getRole_IDPKM($role_name)
{
    global $gDb, $gCurrentOrganization;
	
    $sql    = 'SELECT rol_id
                 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_name   = \''.$role_name.'\'
                  AND rol_valid  = 1 
                  AND rol_cat_id = cat_id
                  AND ( cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                   OR cat_org_id IS NULL ) ';
                      
    $statement = $gDb->query($sql);
    $row = $statement->fetchObject();

   // fuer den seltenen Fall, dass waehrend des Betriebes die Sprache umgeschaltet wird:  $row->rol_id pruefen
    return (isset($row->rol_id) ?  $row->rol_id : 0);
}


/**
 * Funktion prueft, ob der Nutzer, aufgrund seiner Rollenzugehoerigkeit berechtigt ist das Plugin aufzurufen
 * @param   array  $array   Array mit Rollen-IDs:   entweder $pPreferences->config['Pluginfreigabe']['freigabe']
 *                                                  oder $pPreferences->config['Pluginfreigabe']['freigabe_config']
 * @return  bool   $showPlugin
 */
function checkShowPluginPKM($array)
{
	global $gCurrentUser;
	
    $showPlugin = false;

    foreach ($array as $i)
    {
        if ($gCurrentUser->isMemberOfRole($i))
        {
            $showPlugin = true;
        } 
    } 
    return $showPlugin;
}


/**
 * Funktion ueberprueft den uebergebenen Namen, ob er gemaess den Namenskonventionen fuer
 * Profilfelder und Kategorien zum Uebersetzen durch eine Sprachdatei geeignet ist
 * und gibt den uebersetzten Namen zurueck
 *
 * @param   string  $field_name
 * @return  string  translated $field_name
 */
function convlanguagePKM($field_name)
{
	global $gL10n;

	return (((substr($field_name,3,1)) == '_') ? $gL10n->get($field_name) : $field_name);
}


 /**
 * Funktion erzeugt einen neuen name_intern
 *
 * @param   string  $name
 * @param   int     $index
 * @return  string  $newNameIntern new name_intern
 */
function getNewNameIntern($name, $index)
{
	global $gDb;
	
	$name = umlautePff($name);
	$newNameIntern = strtoupper(str_replace(' ', '_', $name));

	if ($index > 1)
	{
		$newNameIntern = $newNameIntern . '_' . $index;
	}

	$sql = 'SELECT kmf_id
              FROM '.TBL_KEYMANAGER_FIELDS.'
             WHERE kmf_name_intern = \''.$newNameIntern.'\' ';
	$userFieldsStatement = $gDb->query($sql);

	if ($userFieldsStatement->rowCount() > 0)
	{
		++$index;
		$newNameIntern = getNewNameIntern($name, $index);
	}

	return $newNameIntern;
}


/**
 * das Feld wird um eine Position in der Reihenfolge verschoben
 * @param string $mode
 */
function moveSequence($mode)
{
	global $gDb, $gCurrentOrganization, $keyField;

	$mode = admStrToUpper($mode);
	$kmfSequence = (int) $keyField->getValue('kmf_sequence');

	// die Sequenz wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
	if ($mode === 'UP')
	{
		$sql = 'UPDATE '.TBL_KEYMANAGER_FIELDS.'
                   SET kmf_sequence = '.$kmfSequence.'
                 WHERE kmf_sequence = '.$kmfSequence.'-1
                   AND ( kmf_org_id = '.$gCurrentOrganization->getValue('org_id').'
                    OR kmf_org_id IS NULL ) ';
		$gDb->query($sql );
		--$kmfSequence;
	}
	// die Sequenz wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
	elseif ($mode === 'DOWN')
	{
		$sql = 'UPDATE '.TBL_KEYMANAGER_FIELDS.'
                   SET kmf_sequence = '.$kmfSequence.'
                 WHERE kmf_sequence = '.$kmfSequence.'+1
                   AND ( kmf_org_id = '.$gCurrentOrganization->getValue('org_id').'
                    OR kmf_org_id IS NULL ) ';
		$gDb->query($sql );
		++$kmfSequence;
	}
	
	$keyField->setValue('kmf_sequence', $kmfSequence);
	$keyField->save();
}


/**
 * Funktion liest die hoechste Sequenzzahl aus der db,
 * inkrementiert und gibt den neuen Wert zurueck
 *
 * @return  int  $row['max_sequence'] + 1
 */
function genNewSequence()
{
	global $gDb, $gCurrentOrganization;

	$sql =  'SELECT max(kmf_sequence) as max_sequence
                   FROM '.TBL_KEYMANAGER_FIELDS.' 
                  WHERE ( kmf_org_id = '.$gCurrentOrganization->getValue('org_id').'
                     OR kmf_org_id IS NULL ) ';
	$statement = $gDb->query($sql);
	$row = $statement->fetch();

	return $row['max_sequence'] + 1;
}


/**
 * Ersetzt Umlaute
 * (wird benoetigt um einen neuen name_intern zu erzeugen)
 * @param   string  $tmptext
 * @return  string  $tmptext
 */
function umlautePff($tmptext)
{
	// Autor: guenter47
	// angepasst aufgrund eines Fehlers bei der Umsetzung von ß (rmb)

	$tmptext = htmlentities($tmptext);
	$tmptext = str_replace('&uuml;', 'ue', $tmptext);
	$tmptext = str_replace('&auml;', 'ae', $tmptext);
	$tmptext = str_replace('&ouml;', 'oe', $tmptext);
	$tmptext = str_replace('&szlig;', 'ss', $tmptext);
	$tmptext = str_replace('&Uuml;', 'Ue', $tmptext);
	$tmptext = str_replace('&Auml;', 'Ae', $tmptext);
	$tmptext = str_replace('&Ouml;', 'Oe', $tmptext);
	$tmptext = str_replace('.', '', $tmptext);
	$tmptext = str_replace(',', '', $tmptext);
	$tmptext = str_replace('/', '', $tmptext);

	return $tmptext;
}

