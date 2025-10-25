<?php
/**
 ***********************************************************************************************
 * Installation routine for the Admidio plugin birthday_list
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:  none
 *
 ***********************************************************************************************
 */
use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Plugins\KeyManager\classes\Config\ConfigTable;

try {
    require_once (__DIR__ . '/../../../system/common.php');
    require_once (__DIR__ . '/common_function.php');

    // only administrators are allowed to start this module
    if (! $gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // im ersten Schritt prüfen, ob eine Rolle ("Zugriffsrolle") vorhanden ist? (ggf. neu anlegen oder aktualisieren)

    // dazu zuerst die Id (cat_id) der Kategorie Allgemein ermitteln
    $category = new Category($gDb);
    $category->readDataByColumns(array(
        'cat_org_id' => $gCurrentOrgId,
        'cat_type' => 'ROL',
        'cat_name_intern' => 'COMMON'
    ));
    $categoryCommonId = $category->getValue('cat_id');

    // danach ein neues Objekt erzeugen
    $role = new Role($gDb);

    // eine eventuell vorhandene Rolle einlesen (das Einlesen über 'rol_name' und 'rol_description' funktioniert nur, wenn diese Daten vom Benutzer nicht verändert worden sind)
    $role->readDataByColumns(array(
        'rol_cat_id' => $categoryCommonId,
        'rol_name' => $gL10n->get('PLG_KEYMANAGER_ACCESS_TO_PLUGIN'),
        'rol_description' => $gL10n->get('PLG_KEYMANAGER_ACCESS_TO_PLUGIN_DESC')
    ));

    if ($role->getValue('rol_id') === 0) // nur wenn es keine Rolle gibt, neue Daten eingeben (mehr als eine Rolle wird nicht betrachtet)
    {
        // Daten für diese Rolle eingeben (entweder vorhandene Daten aktualisieren oder neue Daten eingeben)
        $role->saveChangesWithoutRights(); // toDo: ggf. erweiterte Berechtigungen für die Rolle vergeben
        $role->setValue('rol_cat_id', $categoryCommonId, false);
        $role->setValue('rol_name', $gL10n->get('PLG_KEYMANAGER_ACCESS_TO_PLUGIN'));
        $role->setValue('rol_description', $gL10n->get('PLG_KEYMANAGER_ACCESS_TO_PLUGIN_DESC'));
        $role->save();
    }

    // aktuellen Benutzer dieser Rolle hinzufügen
    $role->startMembership((int) $gCurrentUser->getValue('usr_id'));
    $role->save();

    // im zweiten Schritt prüfen, ob ein Menüpunkt vorhanden ist und ggf. neu anlegen

    // dazu zuerst die Id (men_id) der Menüebene Erweiterungen ermitteln
    $menuParent = new MenuEntry($gDb);
    $menuParent->readDataByColumns(array(
        'men_name_intern' => 'extensions'
    ));
    $menIdParent = $menuParent->getValue('men_id');

    // eine neues Objekt erzeugen
    $menu = new MenuEntry($gDb);

    // einen eventuell vorhandenen Menüpunkt einlesen
    $menu->readDataByColumns(array(
        'men_url' => FOLDER_PLUGINS . PLUGIN_FOLDER . '/index.php'
    ));

    // Daten für diesen Memüpunkt eingeben
    $menu->setValue('men_men_id_parent', $menIdParent);
    $menu->setValue('men_url', FOLDER_PLUGINS . PLUGIN_FOLDER . '/index.php');
    $menu->setValue('men_icon', 'key');
    $menu->setValue('men_name', 'PLG_KEYMANAGER_NAME');
    $menu->setValue('men_description', 'PLG_KEYMANAGER_NAME_DESC');
    $menu->save();

    // die vorher angelegte Rolle diesem Menüpunkt hinzufügen ('Sichtbar für')
    $rightMenuView = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
    $rightMenuView->addRoles(array(
        $role->getValue('rol_id')
    ));

    // damit am Bildschirm die Menüeinträge aktualisiert werden: alle Sesssions neu laden
    $gCurrentSession->reloadAllSessions();

    // im letzten Schritt die Konfigurationsdaten bearbeiten

    // eine neues Objekt erzeugen
    $pPreferences = new ConfigTable();

    // prüfen, ob die Konfigurationstabelle bereits vorhanden ist und ggf. neu anlegen oder aktualisieren
    if ($pPreferences->checkforupdate()) {
        $pPreferences->init();
    }

    $pPreferences->config['install']['access_role_id'] = $role->getValue('rol_id'); // für die Uninstall-Routine: die ID der Zugriffsrolle in der Konfigurationstabelle speichern
    $pPreferences->config['install']['menu_item_id'] = $menu->getValue('men_id');   // für die Uninstall-Routine: die ID des Menüpunktes in der Konfigurationstabelle speichern
    $pPreferences->save();

    admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/keymanager.php');
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}


