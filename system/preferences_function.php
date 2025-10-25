<?php
/**
 ***********************************************************************************************
 * Verarbeiten der Einstellungen des Admidio-Plugins KeyManager
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * ****************************************************************************
 * Parameters:
 *
 * mode: 1 - Save preferences
 *       2 - show dialog for deinstallation
 *       3 - deinstallation
 * form - The name of the form preferences that were submitted.
 *
 * ***************************************************************************
 */
use Admidio\Infrastructure\Utils\SecurityUtils;
use Plugins\KeyManager\classes\Config\ConfigTable;

require_once (__DIR__ . '/../../../system/common.php');
require_once (__DIR__ . '/common_function.php');

$pPreferences = new ConfigTable();
$pPreferences->read();

// only authorized user are allowed to start this module
if (! isUserAuthorizedForPreferences()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
if ($getMode === 1) {
    $gMessage->showHtmlTextOnly(true);
}

try {
    switch ($getForm) {
        case 'interface_pff':
            $pPreferences->config['Optionen']['interface_pff'] = $_POST['interface_pff'];
            break;

        case 'profile_addin':
            $pPreferences->config['Optionen']['profile_addin'] = $_POST['profile_addin'];
            break;

        case 'export':
            $pPreferences->config['Optionen']['file_name'] = $_POST['file_name'];
            $pPreferences->config['Optionen']['add_date'] = isset($_POST['add_date']) ? 1 : 0;
            break;

        case 'access_preferences':
            if (isset($_POST['access_preferences'])) {
                $pPreferences->config['access']['preferences'] = array_filter($_POST['access_preferences']);
            } else {
                $pPreferences->config['access']['preferences'] = array();
            }
            break;

        default:
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
} catch (AdmException $e) {
    $e->showText();
}

$pPreferences->save();

echo 'success';
break;


