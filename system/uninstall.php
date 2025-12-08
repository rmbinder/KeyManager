<?php
/**
 ***********************************************************************************************
 * Uninstallation of the Admidio plugin KeyManager
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : security_check - security check
 *            uninst - uninstallation procedure
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Exception;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Plugins\KeyManager\classes\Config\ConfigTable;

try {
    require_once (__DIR__ . '/../../../system/common.php');
    require_once (__DIR__ . '/common_function.php');

    // only authorized user are allowed to start this module
    if (! isUserAuthorizedForPreferences()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $pPreferences = new ConfigTable();
    $pPreferences->read();

    $result = $gL10n->get('PLG_KEYMANAGER_UNINST_STARTMESSAGE');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'security_check',
        'validValues' => array(
            'security_check',
            'uninst'
        )
    ));

    switch ($getMode) {
        case 'security_check':
            // Sicherheitsabfrage, ob wirklich alles gelöscht werden soll

            global $gL10n;

            $title = $gL10n->get('PLG_KEYMANAGER_UNINSTALLATION');
            $headline = $gL10n->get('PLG_KEYMANAGER_UNINSTALLATION');

            $gNavigation->addUrl(CURRENT_URL, $headline);

            $page = PagePresenter::withHtmlIDAndHeadline('plg-keymanager-uninstall-html');
            $page->setTitle($title);
            $page->setHeadline($headline);

            $form = new FormPresenter('keymanager_uninstall_form', '../templates/uninstall.plugin.keymanager.tpl', '', $page, array(
                'type' => 'default',
                'method' => 'post',
                'setFocus' => false
            ));

            $form->addButton('btn_exit', $gL10n->get('SYS_YES'), array(
                'icon' => 'bi-check-square',
                'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/uninstall.php', array(
                    'mode' => 'uninst'
                )),
                'class' => 'btn-primary'
            ));

            $form->addButton('btn_continue', $gL10n->get('SYS_BACK'), array(
                'icon' => 'bi-backspace',
                'link' => $gNavigation->getPreviousUrl(),
                'class' => 'btn-primary'
            ));

            $form->addToHtmlPage(false);

            $page->show();

            break;

        case 'uninst':
            // Sicherheitsabfrage wurde bestätigt, es darf alles gelöscht werden

            // Zugriffsrolle und Menüpunkt löschen
            if ($pPreferences->config['install']['access_role_id'] == 0 || $pPreferences->config['install']['menu_item_id'] == 0) {
                // nur zur Sicherheit; dass 'access_role_id' und/oder 'menu_item_id' Null ist, dürfte eigentlich nicht vorkommen
                $result .= $gL10n->get('PLG_KEYMANAGER_UNINST_NO_INST_IDS_FOUND');
            } else {
                $result_role = true;

                $role = new Role($gDb, (int) $pPreferences->config['install']['access_role_id']);

                $result_role = $role->delete();
                $result .= ($result_role ? $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE_ERROR'));

                $result_menu = false;

                // Alle in der Konfigurationstabelle gespeicherten Zugriffsrollen (org-übergreifend) einlesen
                $access_roles_prefs = $pPreferences->getAllAccessRoles();

                // der Menüpunkt wird nur entfernt, wenn die bei der Installation erzeugte Zugriffsrolle
                // die einzige Rolle ist, die noch in der Konfigurationstabelle gespeichert ist
                if (count($access_roles_prefs) === 1 && $access_roles_prefs[0] == $pPreferences->config['install']['access_role_id']) {
                    $menu = new MenuEntry($gDb, (int) $pPreferences->config['install']['menu_item_id']);
                    $result_menu = $menu->delete();
                    $result .= ($result_menu ? $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_ERROR'));
                } else {
                    // Menüpunkt nicht entfernen, wenn er noch für eine andere Organisation verwendet wird
                    $result .= $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_NOT_DELETED');
                }
            }

            // Konfigurationsdaten löschen (nur in aktueller Organisation)
            $result_data = false;
            $result_db = false;

            $sql = 'DELETE FROM ' . $pPreferences->getTableName() . '
        			              WHERE plp_name LIKE ?
        			                AND plp_org_id = ? ';
            $result_data = $gDb->queryPrepared($sql, array(
                $pPreferences->getShortcut() . '__%',
                $gCurrentOrgId
            ));

            // wenn die Tabelle nur Eintraege dieses Plugins hatte, sollte sie jetzt leer sein und kann geloescht werden
            $sql = 'SELECT * FROM ' . $pPreferences->getTableName() . ' ';
            $statement = $gDb->queryPrepared($sql);

            if ($statement->rowCount() == 0) {
                $sql = 'DROP TABLE ' . $pPreferences->getTableName() . ' ';
                $result_db = $gDb->queryPrepared($sql);
            }

            $result .= ($result_data ? $gL10n->get('PLG_KEYMANAGER_UNINST_DATA_DELETE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_DATA_DELETE_ERROR'));
            $result .= ($result_db ? $gL10n->get('PLG_KEYMANAGER_UNINST_TABLE_DELETE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_TABLE_DELETE_ERROR'));

            // Schlüsseldaten
            $sql = 'DELETE FROM ' . TBL_KEYMANAGER_DATA . '
                          WHERE kmd_kmk_id IN
              	        (SELECT kmk_id
					       FROM ' . TBL_KEYMANAGER_KEYS . '
                	      WHERE kmk_org_id = ? )';

            $result_data = $gDb->queryPrepared($sql, array(
                $gCurrentOrgId
            ));

            $result .= ($result_data ? $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN', array(
                $g_tbl_praefix . '_keymanager_data'
            )) : $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN_ERROR', array(
                $g_tbl_praefix . '_keymanager_data'
            )));

            $sql = 'DELETE FROM ' . TBL_KEYMANAGER_LOG . '
                          WHERE kml_kmk_id IN
				        (SELECT kmk_id
					       FROM ' . TBL_KEYMANAGER_KEYS . '
                          WHERE kmk_org_id = ? )';

            $result_log = $gDb->queryPrepared($sql, array(
                $gCurrentOrgId
            ));

            $result .= ($result_log ? $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN', array(
                $g_tbl_praefix . '_keymanager_log'
            )) : $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN_ERROR', array(
                $g_tbl_praefix . '_keymanager_log'
            )));

            $sql = 'DELETE FROM ' . TBL_KEYMANAGER_KEYS . '
	        	          WHERE kmk_org_id = ? ';

            $result_keys = $gDb->queryPrepared($sql, array(
                $gCurrentOrgId
            ));
            $result .= ($result_keys ? $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN', array(
                $g_tbl_praefix . '_keymanager_keys'
            )) : $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN_ERROR', array(
                $g_tbl_praefix . '_keymanager_keys'
            )));

            $sql = 'DELETE FROM ' . TBL_KEYMANAGER_FIELDS . '
                          WHERE kmf_org_id = ? ';

            $result_fields = $gDb->queryPrepared($sql, array(
                $gCurrentOrgId
            ));
            $result .= ($result_fields ? $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN', array(
                $g_tbl_praefix . '_keymanager_fields'
            )) : $gL10n->get('PLG_KEYMANAGER_UNINST_KEYDATA_DELETED_IN_ERROR', array(
                $g_tbl_praefix . '_keymanager_fields'
            )));

            // drop tables keys, data, log and fields
            $table_array = array(
                TBL_KEYMANAGER_FIELDS,
                TBL_KEYMANAGER_DATA,
                TBL_KEYMANAGER_KEYS,
                TBL_KEYMANAGER_LOG
            );

            foreach ($table_array as $table_name) {
                $result_db = false;

                // wenn in der Tabelle keine Eintraege mehr sind, dann kann sie geloescht werden
                $sql = 'SELECT * FROM ' . $table_name . ' ';
                $statement = $gDb->queryPrepared($sql);

                if ($statement->rowCount() == 0) {
                    $sql = 'DROP TABLE ' . $table_name . ' ';
                    $result_db = $gDb->queryPrepared($sql);
                    $result .= ($result_db ? $gL10n->get('PLG_KEYMANAGER_UNINST_KEYTABLE_DELETED', array(
                        $table_name
                    )) : $gL10n->get('PLG_KEYMANAGER_UNINST_KEYTABLE_DELETE_ERROR', array(
                        $table_name
                    )));
                } else {
                    $result .= $gL10n->get('PLG_KEYMANAGER_UNINST_KEYTABLE_DELETE_NOTPOSSIBLE', array(
                        $table_name
                    ));
                }
            }

            $gNavigation->clear();
            $gMessage->setForwardUrl($gHomepage);

            $gMessage->show($result);

            break;
    }
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}