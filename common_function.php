<?php
/**
 ***********************************************************************************************
 * Common functions for the Admidio plugin KeyManager
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');

global $g_tbl_praefix;

if(!defined('PLUGIN_FOLDER'))
{
	define('PLUGIN_FOLDER', '/'.substr(__DIR__,strrpos(__DIR__,DIRECTORY_SEPARATOR)+1));
}
if(!defined('ORG_ID'))
{
	define('ORG_ID', (int) $gCurrentOrganization->getValue('org_id'));
}
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
    global $gDb;
	
    $sql    = 'SELECT rol_id
                 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_name   = \''.$role_name.'\'
                  AND rol_valid  = 1 
                  AND rol_cat_id = cat_id
                  AND ( cat_org_id = '.ORG_ID.'
                   OR cat_org_id IS NULL ) ';
                      
    $statement = $gDb->query($sql);
    $row = $statement->fetchObject();

   // fuer den seltenen Fall, dass waehrend des Betriebes die Sprache umgeschaltet wird:  $row->rol_id pruefen
    return (isset($row->rol_id) ?  $row->rol_id : 0);
}

/**
 * Funktion prueft, ob der Nutzer berechtigt ist das Plugin aufzurufen.
 * Zur Prüfung werden die Einstellungen von 'Modulrechte' und 'Sichtbar für'
 * verwendet, die im Modul Menü für dieses Plugin gesetzt wurden.
 * @param   string  $scriptName   Der Scriptname des Plugins
 * @return  bool    true, wenn der User berechtigt ist
 */
function isUserAuthorized($scriptName)
{
	global $gDb, $gCurrentUser, $gMessage, $gL10n;
	
	$userIsAuthorized = false;
	$menId = 0;
	
	$sql = 'SELECT men_id
              FROM '.TBL_MENU.'
             WHERE men_url = ? -- $scriptName ';
	
	$menuStatement = $gDb->queryPrepared($sql, array($scriptName));
	
	if ( $menuStatement->rowCount() === 0 || $menuStatement->rowCount() > 1)
	{
		$gMessage->show($gL10n->get('PLG_KEYMANAGER_MENU_URL_ERROR', $scriptName), $gL10n->get('SYS_ERROR'));
	}
	else
	{
		while ($row = $menuStatement->fetch())
		{
			$menId = (int) $row['men_id'];
		}
	}
	
	$sql = 'SELECT men_id, men_com_id, com_name_intern
              FROM '.TBL_MENU.'
         LEFT JOIN '.TBL_COMPONENTS.'
                ON com_id = men_com_id
             WHERE men_id = ? -- $menId
          ORDER BY men_men_id_parent DESC, men_order';
	
	$menuStatement = $gDb->queryPrepared($sql, array($menId));
	while ($row = $menuStatement->fetch())
	{
		if ((int) $row['men_com_id'] === 0 || Component::isVisible($row['com_name_intern']))
		{
			// Read current roles rights of the menu
			$displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
			$rolesDisplayRight = $displayMenu->getRolesIds();
			
			// check for right to show the menu
			if (count($rolesDisplayRight) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
			{
				$userIsAuthorized = true;
			}
		}
	}
	return $userIsAuthorized;
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
	global $gDb, $keyField;

	$mode = admStrToUpper($mode);
	$kmfSequence = (int) $keyField->getValue('kmf_sequence');

	// die Sequenz wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
	if ($mode === 'UP')
	{
		$sql = 'UPDATE '.TBL_KEYMANAGER_FIELDS.'
                   SET kmf_sequence = '.$kmfSequence.'
                 WHERE kmf_sequence = '.$kmfSequence.'-1
                   AND ( kmf_org_id = '.ORG_ID.'
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
                   AND ( kmf_org_id = '.ORG_ID.'
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
	global $gDb;

	$sql =  'SELECT max(kmf_sequence) as max_sequence
                   FROM '.TBL_KEYMANAGER_FIELDS.' 
                  WHERE ( kmf_org_id = '.ORG_ID.'
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

