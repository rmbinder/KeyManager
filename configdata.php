<?php
/**
 ***********************************************************************************************
 * Config data for the Admidio plugin KeyManager
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gProfileFields;

//Standardwerte einer Neuinstallation oder beim Anfuegen einer zusaetzlichen Konfiguration
$config_default['Optionen']['interface_pff'] = 0;
$config_default['Optionen']['profile_addin'] = '';
$config_default['Optionen']['file_name'] = 'KeyManager';
$config_default['Optionen']['add_date'] = 0;
															
$config_default['Plugininformationen']['version'] = '';
$config_default['Plugininformationen']['stand'] = '';

//Zugriffsberechtigung für das Modul preferences
$config_default['access']['preferences'] = array();

/*
 *  Mittels dieser Zeichenkombination werden Konfigurationsdaten, die zur Laufzeit als Array verwaltet werden,
 *  zu einem String zusammengefasst und in der Admidiodatenbank gespeichert. 
 *  Muss die vorgegebene Zeichenkombination (#_#) jedoch ebenfalls, z.B. in der Beschreibung 
 *  einer Konfiguration, verwendet werden, so kann das Plugin gespeicherte Konfigurationsdaten 
 *  nicht mehr richtig einlesen. In diesem Fall ist die vorgegebene Zeichenkombination abzuaendern (z.B. in !-!)
 *  
 *  Achtung: Vor einer Aenderung muss eine Deinstallation durchgefuehrt werden!
 *  Bereits gespeicherte Werte in der Datenbank koennen nach einer Aenderung nicht mehr eingelesen werden!
 */
$dbtoken  = '#_#';  
