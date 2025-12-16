<?php
/**
 ***********************************************************************************************
 * KeyManager
 *
 * Version 3.0.0
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
use Admidio\Infrastructure\Exception;
use Plugins\KeyManager\classes\Config\ConfigTable;

// Fehlermeldungen anzeigen
//error_reporting(E_ALL);

try {
    require_once (__DIR__ . '/../../system/common.php');
    require_once (__DIR__ . '/system/common_function.php');

    // only authorized user are allowed to start this module
    if (! isUserAuthorized()) {
        throw new Exception('SYS_NO_RIGHTS');
    }
    
      $gNavigation->addStartUrl(CURRENT_URL);
      

    $pPreferences = new ConfigTable();
    if ($pPreferences->checkForUpdate()) {
        $pPreferences->init();
    } 
    $pPreferences->read();

    if ($pPreferences->config['install']['access_role_id'] == 0 || $pPreferences->config['install']['menu_item_id'] == 0) {
        
        $urlInst = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/install.php';
        $gMessage->show($gL10n->get('PLG_KEYMANAGER_INSTALL_UPDATE_REQUIRED', array(
            '<a href="' . $urlInst . '">' . $urlInst . '</a>'
        )));
    }
    
    admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/keymanager.php');
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}

