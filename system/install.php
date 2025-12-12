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
use Admidio\Infrastructure\Utils\FileSystemUtils;
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

    // nur wenn es keine Rolle gibt, neue Daten eingeben (mehr als eine Rolle wird nicht betrachtet)
    if ($role->getValue('rol_id') === 0) 
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

    // für die Anzeige von KeyManager-Daten inm Profil eines Mitglieds müssen original Admidio-Dateien geändert werden
    
    $zeilenumbruch = "\r\n";
    
    // ADMIDIO_URL auch möglich, aber dann wird 'allow_url_open' (PHP.ini) benötigt
    $templateFile = ADMIDIO_PATH . FOLDER_THEMES . '/simple/templates/modules/profile.view';
    try {
        if (! file_exists($templateFile . '_keymanager_save.tpl')) {
            //zur Sicherheit wird eine Kopie der Originaldatei erzeugt (bei der Deinstallation wird sie wieder gelöscht)
            FileSystemUtils::copyFile($templateFile . '.tpl', $templateFile . '_keymanager_save.tpl');
            
            // Template-Datei einlesen
            $templateString = file_get_contents($templateFile . '.tpl');
            
            // diese Texte in die profile.view.tpl einfügen ($needle => $subst)
            $substArray = array(
                '{if $showRelations}' => '{include file="../../../..' . FOLDER_PLUGINS . PLUGIN_FOLDER  .'/templates/profile.view.include.button.plugin.keymanager.tpl"}'.$zeilenumbruch,
                '<!-- User Relations Tab -->' => '{include file="../../../..' . FOLDER_PLUGINS . PLUGIN_FOLDER  .'/templates/profile.view.include.keymanager.tab.plugin.keymanager.tpl"}'.$zeilenumbruch,
                '<!-- User Relations Accordion -->' => '{include file="../../../..' . FOLDER_PLUGINS . PLUGIN_FOLDER  .'/templates/profile.view.include.keymanager.accordion.plugin.keymanager.tpl"}'.$zeilenumbruch
            );
            foreach ($substArray as $needle => $subst) {
                $templateString = substr_replace($templateString, $subst, strpos($templateString, $needle), 0);
            }
            
            // Template-Datei wieder schreiben
            file_put_contents($templateFile . '.tpl', $templateString);
        } else {
            // es gibt bereits eine save-Datei, d.h. die Änderungen sind bereits eingetragen; irgendein 'Superchecker' führt die Install-Routine ein zweites mal aus
        }
    } catch (\RuntimeException $exception) {
        $gMessage->show($exception->getMessage());
        // => EXIT
    } catch (\UnexpectedValueException $exception) {
        $gMessage->show($exception->getMessage());
        // => EXIT
    }
    
    $profileFile = ADMIDIO_PATH . FOLDER_MODULES . '/profile/profile';
    try {
        if (! file_exists($profileFile . '_keymanager_save.php')) {
            //zur Sicherheit wird eine Kopie der Originaldatei erzeugt (bei der Deinstallation wird sie wieder gelöscht)
            FileSystemUtils::copyFile($profileFile . '.php', $profileFile . '_keymanager_save.php');
            
            // PHP-Datei einlesen
            $profileString = file_get_contents($profileFile . '.php');
            
            // diesen Text in die profile.view.tpl einfügen
            $needle = '$page->show();';
            $subst = "require_once(ADMIDIO_PATH . FOLDER_PLUGINS .'" .PLUGIN_FOLDER . "/system/keymanager_profile_addin.php');";
            $profileString = substr_replace($profileString, $subst . $zeilenumbruch, strpos($profileString, $needle), 0);
            
            // PHP-Datei wieder schreiben
            file_put_contents($profileFile . '.php', $profileString);
        } else {
            // es gibt bereits eine save-Datei, d.h. die Änderungen sind bereits eingetragen; irgendein 'Superchecker' führt die Install-Routine ein zweites mal aus
        }
    } catch (\RuntimeException $exception) {
        $gMessage->show($exception->getMessage());
        // => EXIT
    } catch (\UnexpectedValueException $exception) {
        $gMessage->show($exception->getMessage());
        // => EXIT
    }
    
    // die Konfigurationsdaten bearbeiten

    // eine neues Objekt erzeugen
    $pPreferences = new ConfigTable();

    // prüfen, ob die Konfigurationstabelle bereits vorhanden ist und ggf. neu anlegen oder aktualisieren
    if ($pPreferences->checkForUpdate()) {
        $pPreferences->init();
    }

    $pPreferences->config['install']['access_role_id'] = $role->getValue('rol_id'); // für die Uninstall-Routine: die ID der Zugriffsrolle in der Konfigurationstabelle speichern
    $pPreferences->config['install']['menu_item_id'] = $menu->getValue('men_id');   // für die Uninstall-Routine: die ID des Menüpunktes in der Konfigurationstabelle speichern
    $pPreferences->save();

    admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/system/keymanager.php');
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}


