<?php
/**
 ***********************************************************************************************
 * Common functions for the Admidio plugin KeyManager
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Components\Entity\Component;
use Admidio\Roles\Entity\RolesRights;

require_once (__DIR__ . '/../../../system/common.php');

global $g_tbl_praefix;

if (! defined('PLUGIN_FOLDER')) {
    define('PLUGIN_FOLDER', '/' . substr(dirname(__DIR__), strrpos(dirname(__DIR__), DIRECTORY_SEPARATOR) + 1));
}
if (! defined('TBL_KEYMANAGER_FIELDS')) {
    define('TBL_KEYMANAGER_FIELDS', $g_tbl_praefix . '_keymanager_fields');
}
if (! defined('TBL_KEYMANAGER_DATA')) {
    define('TBL_KEYMANAGER_DATA', $g_tbl_praefix . '_keymanager_data');
}
if (! defined('TBL_KEYMANAGER_KEYS')) {
    define('TBL_KEYMANAGER_KEYS', $g_tbl_praefix . '_keymanager_keys');
}
if (! defined('TBL_KEYMANAGER_LOG')) {
    define('TBL_KEYMANAGER_LOG', $g_tbl_praefix . '_keymanager_log');
}

spl_autoload_register('myAutoloader');

/**
 * Mein Autoloader
 * Script aus dem Netz
 * https://www.marcosimbuerger.ch/tech-blog/php-autoloader.html
 * @param   string  $className   Die übergebene Klasse
 * @return  string  Der überprüfte Klassenname
 */
function myAutoloader($className) {
    // Projekt spezifischer Namespace-Prefix.
    $prefix = 'Plugins\\';
    
    // Base-Directory für den Namespace-Prefix.
    $baseDir = __DIR__ . '/../../';
    
    // Check, ob die Klasse den Namespace-Prefix verwendet.
    $len = strlen($prefix);
    
    if (strncmp($prefix, $className, $len) !== 0) {
        // Wenn der Namespace-Prefix nicht verwendet wird, wird abgebrochen.
        return;
    }
    // Den relativen Klassennamen ermitteln.
    $relativeClassName = substr($className, $len);
    
    // Den Namespace-Präfix mit dem Base-Directory ergänzen,
    // Namespace-Trennzeichen durch Verzeichnis-Trennzeichen im relativen Klassennamen ersetzen,
    // .php anhängen.
    $file = $baseDir . str_replace('\\', '/', $relativeClassName) . '.php';
    // Pfad zur Klassen-Datei zurückgeben.
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * Funktion prueft, ob der Nutzer berechtigt ist das Plugin aufzurufen.
 * Zur Prüfung werden die Einstellungen von 'Modulrechte' und 'Sichtbar für'
 * verwendet, die im Modul Menü für dieses Plugin gesetzt wurden.
 *
 * @param string $scriptName
 *            Der Scriptname des Plugins
 * @return bool true, wenn der User berechtigt ist
 */
function isUserAuthorized($scriptName)
{
    global $gDb, $gMessage, $gLogger, $gL10n, $gCurrentUser;

    $userIsAuthorized = false;
    $menId = 0;

    $sql = 'SELECT men_id
              FROM ' . TBL_MENU . '
             WHERE men_url = ? -- $scriptName ';

    $menuStatement = $gDb->queryPrepared($sql, array(
        $scriptName
    ));

    if ($menuStatement->rowCount() === 0 || $menuStatement->rowCount() > 1) {
        $gMessage->show($gL10n->get('PLG_KEYMANAGER_MENU_URL_ERROR', array(
            $scriptName
        )), $gL10n->get('SYS_ERROR'));
    } else {
        while ($row = $menuStatement->fetch()) {
            $menId = (int) $row['men_id'];
        }
    }

    // Read current roles rights of the menu
    $displayMenu = new RolesRights($gDb, 'menu_view', $menId);

    // check for right to show the menu
    if (count($displayMenu->getRolesIds()) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships())) {
        $userIsAuthorized = true;
    }

    return $userIsAuthorized;
}

/**
 * Funktion prueft, ob der Nutzer berechtigt ist, das Modul Preferences aufzurufen.
 *
 * @param
 *            none
 * @return bool true, wenn der User berechtigt ist
 */
function isUserAuthorizedForPreferences()
{
    global $pPreferences, $gCurrentUser;

    $userIsAuthorized = false;

    if ($gCurrentUser->isAdministrator()) // Mitglieder der Rolle Administrator dürfen "Preferences" immer aufrufen
    {
        $userIsAuthorized = true;
    } else {
        foreach ($pPreferences->config['access']['preferences'] as $roleId) {
            if ($gCurrentUser->isMemberOfRole((int) $roleId)) {
                $userIsAuthorized = true;
                continue;
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
 * @param string $field_name
 * @return string translated $field_name
 */
function convlanguagePKM($field_name)
{
    return (((substr($field_name, 3, 1)) == '_') ? $GLOBALS['gL10n']->get($field_name) : $field_name);
}

/**
 * Funktion erzeugt einen neuen name_intern
 *
 * @param string $name
 * @param int $index
 * @return string $newNameIntern new name_intern
 */
function getNewNameIntern($name, $index)
{
    $name = umlautePKM($name);
    $newNameIntern = strtoupper(str_replace(' ', '_', $name));

    if ($index > 1) {
        $newNameIntern = $newNameIntern . '_' . $index;
    }

    $sql = 'SELECT kmf_id
              FROM ' . TBL_KEYMANAGER_FIELDS . '
             WHERE kmf_name_intern = ? ';
    $userFieldsStatement = $GLOBALS['gDb']->queryPrepared($sql, array(
        $newNameIntern
    ));

    if ($userFieldsStatement->rowCount() > 0) {
        ++ $index;
        $newNameIntern = getNewNameIntern($name, $index);
    }

    return $newNameIntern;
}

/**
 * Funktion liest die hoechste Sequenzzahl aus der db,
 * inkrementiert und gibt den neuen Wert zurueck
 *
 * @return int $row['max_sequence'] + 1
 */
function genNewSequence()
{
    $sql = 'SELECT max(kmf_sequence) as max_sequence
                   FROM ' . TBL_KEYMANAGER_FIELDS . ' 
                  WHERE ( kmf_org_id = ?
                     OR kmf_org_id IS NULL ) ';
    $statement = $GLOBALS['gDb']->queryPrepared($sql, array(
        $GLOBALS['gCurrentOrgId']
    ));
    $row = $statement->fetch();

    return $row['max_sequence'] + 1;
}

/**
 * Ersetzt Umlaute
 * (wird benoetigt um einen neuen name_intern zu erzeugen)
 *
 * @param string $tmptext
 * @return string $tmptext
 */
function umlautePKM($tmptext)
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

/**
 *
 * @param string $group
 * @param string $id
 * @param string $title
 * @param string $icon
 * @param string $body
 * @return string
 */
function getPreferencePanel($group, $id, $title, $icon, $body)
{
    $html = '
        <div class="card" id="panel_' . $id . '">
            <div class="card-header">
                <a type="button" data-bs-toggle="collapse" data-bs-target="#collapse_' . $id . '">
                    <i class="' . $icon . ' bi-fw"></i>' . $title . '
                </a>
            </div>
            <div id="collapse_' . $id . '" class="collapse" aria-labelledby="headingOne" data-bs-parent="#accordion_preferences">
                <div class="card-body">
                    ' . $body . '
                </div>
            </div>
        </div>
    ';
    return $html;
}
