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
 * mode     : html   - show dialog for uninstallation
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

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'html',
        'validValues' => array(
            'html',
            'uninst'
        )
    ));
    $postUninstAccessRole = admFuncVariableIsValid($_POST, 'uninst_access_role', 'bool');
    $postUninstAccessRoleOrgSelect = admFuncVariableIsValid($_POST, 'uninst_access_role_org_select', 'bool');
    $postUninstConfigData = admFuncVariableIsValid($_POST, 'uninst_config_data', 'bool');
    $postUninstConfigDataOrgSelect = admFuncVariableIsValid($_POST, 'uninst_config_data_org_select', 'bool');
    $postUninstMenuItem = admFuncVariableIsValid($_POST, 'uninst_menu_item', 'bool');

    switch ($getMode) {
        case 'html':

            global $gL10n;

            $title = $gL10n->get('PLG_KEYMANAGER_UNINSTALLATION');
            $headline = $gL10n->get('PLG_KEYMANAGER_UNINSTALLATION');

            $gNavigation->addUrl(CURRENT_URL, $headline);

            // create html page object
            $page = PagePresenter::withHtmlIDAndHeadline('plg-keymanager-uninstallation-html');
            $page->setTitle($title);
            $page->setHeadline($headline);

            $formUninstallation = new FormPresenter('adm_preferences_form_uninstallation', '../templates/uninstallation.plugin.keymanager.tpl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/uninstallation.php', array(
                'mode' => 'uninst'
            )), $page, array(
                'class' => 'form-preferences'
            ));

            $radioButtonEntries = array(
                '0' => $gL10n->get('PLG_KEYMANAGER_UNINST_ACTORGONLY'),
                '1' => $gL10n->get('PLG_KEYMANAGER_UNINST_ALLORG')
            );

            $formUninstallation->addCheckbox('uninst_access_role', $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE'));
            $formUninstallation->addRadioButton('uninst_access_role_org_select', '', $radioButtonEntries, array(
                'defaultValue' => '0',
                'alertWarning' => $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE_ALERT')
            ));

            $formUninstallation->addCheckbox('uninst_config_data', $gL10n->get('PLG_KEYMANAGER_UNINST_CONFIG_DATA'));
            $formUninstallation->addRadioButton('uninst_config_data_org_select', '', $radioButtonEntries, array(
                'defaultValue' => '0'
            ));

            $formUninstallation->addCheckbox('uninst_menu_item', $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM'), false, array(
                'alertWarning' => $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_ALERT')
            ));

            $formUninstallation->addSubmitButton('adm_button_uninstallation', $gL10n->get('PLG_KEYMANAGER_UNINSTALLATION'), array(
                'icon' => 'bi-trash',
                'class' => 'offset-sm-3'
            ));

            $formUninstallation->addToHtmlPage(false);

            $page->show();

            break;

        case 'uninst':

            $result = $gL10n->get('PLG_KEYMANAGER_UNINST_STARTMESSAGE');

            if ($postUninstAccessRole) {
                $result_role = true;

                $role = new Role($gDb);

                if ($postUninstAccessRoleOrgSelect) // Zugriffsrollen in allen Orgs löschen
                {
                    $access_roles = $pPreferences->getAllAccessRoles(); // alle Zugriffsrollen org-übergreifend einlesen
                    foreach ($access_roles as $access_role) {
                        $role->readDataById($access_role);
                        $result_role = $result_role && $role->delete();
                    }
                } else // Zugriffsrolle nur in aktueller Org löschen
                {
                    $role->readDataById($pPreferences->config['install']['access_role_id']);
                    $result_role = $result_role && $role->delete();
                }

                $result .= ($result_role ? $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLE_ERROR'));
            }

            if ($postUninstMenuItem) {
                $result_menu = false;

                $access_roles_prefs = $pPreferences->getAllAccessRoles(); // Zugriffsrollen org-übergreifend einlesen

                // den vorhandenen Menüpunkt einlesen und die Rollen die unter 'Sichtbar für' eingetragen sind, auslesen
                $menu = new MenuEntry($gDb);
                $menu->readDataByColumns(array(
                    'men_url' => FOLDER_PLUGINS . PLUGIN_FOLDER . '/index.php'
                ));
                $rightMenuView = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
                $access_roles_menu = $rightMenuView->getRolesIds();

                $roles = array_intersect($access_roles_prefs, $access_roles_menu);

                if (count($roles) === 0) {
                    $result_menu = $menu->delete();
                    $result .= ($result_menu ? $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_MENU_ITEM_ERROR'));
                } else {
                    $result .= $gL10n->get('PLG_KEYMANAGER_UNINST_ACCESS_ROLES_STILL_PRESENT');
                }
            }

            if ($postUninstConfigData) {
                $result_data = false;
                $result_db = false;

                // Konfigurationsdaten
                if (! $postUninstConfigDataOrgSelect) // Konfigurationsdaten nur in aktueller Org loeschen
                {
                    $sql = 'DELETE FROM ' . $pPreferences->config['Plugininformationen']['table_name'] . '
        			              WHERE plp_name LIKE ?
        			                AND plp_org_id = ? ';
                    $result_data = $gDb->queryPrepared($sql, array(
                        $pPreferences->config['Plugininformationen']['shortcut'] . '__%',
                        $gCurrentOrgId
                    ));
                } else // Konfigurationsdaten in allen Org loeschen
                {
                    $sql = 'DELETE FROM ' . $pPreferences->config['Plugininformationen']['table_name'] . '
        			              WHERE plp_name LIKE ? ';
                    $result_data = $gDb->queryPrepared($sql, array(
                        $pPreferences->config['Plugininformationen']['shortcut'] . '__%'
                    ));
                }

                // wenn die Tabelle nur Eintraege dieses Plugins hatte, sollte sie jetzt leer sein und kann geloescht werden
                $sql = 'SELECT * FROM ' . $pPreferences->config['Plugininformationen']['table_name'] . ' ';
                $statement = $gDb->queryPrepared($sql);

                if ($statement->rowCount() == 0) {
                    $sql = 'DROP TABLE ' . $pPreferences->config['Plugininformationen']['table_name'] . ' ';
                    $result_db = $gDb->queryPrepared($sql);
                }

                $result .= ($result_data ? $gL10n->get('PLG_KEYMANAGER_UNINST_DATA_DELETE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_DATA_DELETE_ERROR'));
                $result .= ($result_db ? $gL10n->get('PLG_KEYMANAGER_UNINST_TABLE_DELETE_SUCCESS') : $gL10n->get('PLG_KEYMANAGER_UNINST_TABLE_DELETE_ERROR'));

                // Schlüsseldaten
                if (! $postUninstConfigDataOrgSelect) // Konfigurationsdaten nur in aktueller Org loeschen
                {
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
                }
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
                    // oder wenn 'Daten in allen Orgs loeschen' gewaehlt wurde
                    $sql = 'SELECT * FROM ' . $table_name . ' ';
                    $statement = $gDb->queryPrepared($sql);

                    if ($statement->rowCount() == 0 || $postUninstConfigDataOrgSelect) {
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
            }

            $gNavigation->clear();
            $gMessage->setForwardUrl($gHomepage);

            $gMessage->show($result);

            break;
    }
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}