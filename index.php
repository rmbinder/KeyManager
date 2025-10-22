<?php
/**
 ***********************************************************************************************
 * KeyManager
 *
 * Version 3.0.0 Beta 1
 *
 * KeyManager is an Admidio plugin for managing building and room keys.
 * 
 * Note: KeyManager uses the external class XLSXWriter (https://github.com/mk-j/PHP_XLSXWriter)
 * 
 * Author: rmb
 *
 * Compatible with Admidio version 5
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Exception;
use Admidio\Users\Entity\User;

// Fehlermeldungen anzeigen
error_reporting(E_ALL);

try {
    require_once (__DIR__ . '/../../system/common.php');
    require_once (__DIR__ . '/system/common_function.php');
    require_once (__DIR__ . '/classes/keys.php');
    require_once (__DIR__ . '/classes/configtable.php');

    // $scriptName ist der Name wie er im Menue eingetragen werden muss, also ohne evtl. vorgelagerte Ordner wie z.B. /playground/adm_plugins/keymanager...
    $scriptName = substr($_SERVER['SCRIPT_NAME'], strpos($_SERVER['SCRIPT_NAME'], FOLDER_PLUGINS));

    // only authorized user are allowed to start this module
    if (! isUserAuthorized($scriptName)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $pPreferences = new ConfigTablePKM();
    if ($pPreferences->checkForUpdate()) {
        $pPreferences->init();
    } else {
        $pPreferences->read();
    }

    $gNavigation->addStartUrl(CURRENT_URL);
    admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/keymanager.php');
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}

