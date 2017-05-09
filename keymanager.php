<?php
/**
 ***********************************************************************************************
 * KeyManager
 *
 * Version 1.0.1
 *
 * KeyManager is an Admidio plugin for managing building and room keys.
 * 
 * Author: rmb
 *
 * Compatible with Admidio version 3.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gNavigation;

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

$plugin_folder = '/'.substr(__DIR__,strrpos(__DIR__,DIRECTORY_SEPARATOR)+1);

$gL10n->addLanguagePath(ADMIDIO_PATH . FOLDER_PLUGINS . $plugin_folder . '/languages');

$pPreferences = new ConfigTablePKM();

//Initialisierung und Anzeige des Links nur, wenn vorher keine Deinstallation stattgefunden hat
// (sonst waere die Deinstallation hinfaellig, da hier wieder Default-Werte der config in die DB geschrieben werden)
// Zweite Voraussetzung: Ein User muss erfolgreich eingeloggt sein
// (ansonsten wuerde eine Initialisierung unvollstaendig durchgefuehrt werden)
if (strpos($gNavigation->getUrl(), 'preferences_function.php?mode=3') === false && $gValidLogin)
{
	if ($pPreferences->checkForUpdate())  
	{
		$pPreferences->init();
	}
	else 
	{
		$pPreferences->read();
	}

	// Zeige Link zum Plugin
	if (checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe']) )
	{
		if (isset($pluginMenu))
		{
			// wenn in der my_body_bottom.php ein $pluginMenu definiert wurde, dann innerhalb dieses Menues anzeigen
			$pluginMenu->addItem('keymanager_show', FOLDER_PLUGINS . $plugin_folder .'/keys_show.php',
				$gL10n->get('PLG_KEYMANAGER_KEYMANAGER'), '/icons/list_key.png'); 
		}
		else 
		{
			// wenn nicht, dann innerhalb des (immer vorhandenen) Module-Menues anzeigen
			$moduleMenu->addItem('keymanager_show', FOLDER_PLUGINS . $plugin_folder .'/keys_show.php',
				$gL10n->get('PLG_KEYMANAGER_KEYMANAGER'), '/icons/list_key.png'); 
		}
	}
}		
