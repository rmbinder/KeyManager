<?php
/**
 ***********************************************************************************************
 * keymanager_profile_addin.php
 *
 * Shows issued keys in a member´s profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;
use Plugins\KeyManager\classes\Config\ConfigTable;
use Plugins\KeyManager\classes\Service\Keys;

$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array(
    'defaultValue' => $gCurrentUser->getValue('usr_uuid')
));

require_once (__DIR__ . '/../../../system/common.php');
require_once (__DIR__ . '/common_function.php');

$pPreferences = new ConfigTable();
$pPreferences->read();

$keymanagerTemplateData = array();

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeysByUser($gCurrentOrgId, $user->getValue('usr_id'));

// eine Anzeige nur, wenn dieses Mitglied auch einen Schlüssel besitzt
if (sizeof($keys->keys) === 0) {
    return;
}

foreach ($keys->keys as $key) {
    $keys->readKeyData($key['kmk_id'], $gCurrentOrgId);

    $templateRow = array();
    $templateRow['id'] = $key['kmk_id'];
    $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/keys_edit_new.php', array(
        'key_id' => $key['kmk_id']
    ));
    $templateRow['name'] = $keys->getValue('KEYNAME');

    $contentAdd = $keys->getValue($pPreferences->config['Optionen']['profile_addin']);
    if (! empty($contentAdd)) {
        if (strlen($contentAdd) > 50) {
            $contentAdd = substr($contentAdd, 0, 50) . '...';
        }
        $templateRow['name'] .= ' - ' . $contentAdd;
    }

    if ($key['kmk_former']) {
        $templateRow['name'] = '<s>' . $templateRow['name'] . '</s>';
    }

    $templateRow['received_on'] = '';
    if (! empty($keys->getValue('RECEIVED_ON'))) {
        $received_on = $gL10n->get('PKM_RECEIVED_ON') . ' ' . date('d.m.Y', strtotime($keys->getValue('RECEIVED_ON')));
        if ($key['kmk_former']) {
            $received_on = '<s>' . $received_on . '</s>';
        }
        $templateRow['received_on'] = $received_on;
    }

    if (isUserAuthorizedForPreferences()) {
        $templateRow['actions'][] = array(
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER . '/system/keys_delete.php', array(
                'key_id' => $key['kmk_id'],
                'key_former' => $key['kmk_former']
            )),
            'icon' => 'bi bi-trash',
            'tooltip' => $gL10n->get('PLG_KEYMANAGER_KEY_DELETE')
        );
    }
    $keymanagerTemplateData[] = $templateRow;
}
$page->assignSmartyVariable('keymanagerTemplateData', $keymanagerTemplateData);
$page->assignSmartyVariable('urlKeyManagerFiles', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER . '/system/keymanager.php', array(
    'export_and_filter' => true,
    'show_all' => true,
    'same_side' => true,
    'filter_receiver' => $user->getValue('usr_id')
)));
$page->assignSmartyVariable('showKeyManagerOnProfile', $gCurrentUser->isAdministratorUsers());


