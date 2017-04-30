<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of key fields
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/classes/keys.php');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
{
	$gMessage->setForwardUrl($gHomepage, 3000);
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
$headline = $gL10n->get('PLG_KEYMANAGER_KEYFIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

$keys = new Keys($gDb, $gCurrentOrganization->getValue('org_id'));

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
    /**
     * @param {string} direction
     * @param {int}    kmfID
     */
    function moveCategory(direction, kmfID) {
        var actRow = document.getElementById("row_kmf_" + kmfID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (var i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_kmf_" + kmfID) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "up") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
            $.get(gRootPath + "/adm_plugins/keymanager/fields_function.php?kmf_id=" + kmfID + "&mode=4&sequence=" + direction);
        }
    }
');

// get module menu
$fieldsMenu = $page->getMenu();

// show back link
$fieldsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new key field
$fieldsMenu->addItem('menu_item_new_field', ADMIDIO_URL. FOLDER_PLUGINS . $plugin_folder .'/fields_edit_new.php',
                     $gL10n->get('PLG_KEYMANAGER_KEYFIELD_CREATE'), 'add.png');

// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD'),
    '&nbsp;',
    $gL10n->get('SYS_DESCRIPTION'),
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />',
    $gL10n->get('ORG_DATATYPE'),
    '&nbsp;'
);
$table->addRowHeadingByArray($columnHeading);

// Intialize variables
$description = '';
$mandatory   = '';
$kmfSystem   = '';     

foreach ($keys->mKeyFields as $keyField)
{	
    // cut long text strings and provide tooltip
    if (strlen($keyField->getValue('kmf_description')) > 22)
    {
        $description = substr($keyField->getValue('kmf_description', 'database'), 0, 22).'
            <a data-toggle="modal" data-target="#admidio_modal"
                href="'. ADMIDIO_URL. '/adm_program/system/msg_window.php?message_id=key_field_description&amp;message_var1='.$keyField->getValue('kmf_name_intern').'&amp;inline=true"><span  data-html="true" data-toggle="tooltip" data-original-title="'.str_replace('"', '\'', $keyField->getValue('kmf_description')).'">[..]</span></a>';
    }
    elseif ($keyField->getValue('kmf_description') === '')
    {
        $description = '&nbsp;';
    }
    else
    {
        $description = $keyField->getValue('kmf_description');
    }

    if ($keyField->getValue('kmf_mandatory') == 1)
    {
        $mandatory = '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />';
    }
    else
    {
        $mandatory = '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/asterisk_gray.png" alt="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" />';
    }

    $keyFieldText = array(
    					'CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
                        'DATE'         => $gL10n->get('SYS_DATE'),
                        'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                        'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                        'TEXT'         => $gL10n->get('SYS_TEXT').' (100)',
                        'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000)',
                        'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                        'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));

    $kmfSystem = '<a class="admidio-icon-link" href="'.ADMIDIO_URL. FOLDER_PLUGINS . $plugin_folder .'/fields_edit_new.php?kmf_id='.$keyField->getValue('kmf_id').'"><img
                    src="'.THEME_URL.'/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

    if ($keyField->getValue('kmf_system') == 1)
    {
        $kmfSystem .= '<img class="admidio-icon-link" src="'.THEME_URL.'/icons/dummy.png" alt="dummy" />';
    }
    else
    {
    	$kmfSystem .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL. FOLDER_PLUGINS . $plugin_folder .'/fields_delete.php?kmf_id='.$keyField->getValue('kmf_id').'"><img
                    src="'.THEME_URL.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.ADMIDIO_URL. FOLDER_PLUGINS . $plugin_folder .'/fields_edit_new.php?kmf_id='.$keyField->getValue('kmf_id').'">'. convlanguagePKM($keyField->getValue('kmf_name')).'</a>
        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\'up\', '.$keyField->getValue('kmf_id').')">
            <img src="'.THEME_URL.'/icons/arrow_up.png" alt="'.$gL10n->get('ORG_FIELD_UP').'" title="'.$gL10n->get('ORG_FIELD_UP').'" /></a>
        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\'down\', '.$keyField->getValue('kmf_id').')">
            <img src="'.THEME_URL.'/icons/arrow_down.png" alt="'.$gL10n->get('ORG_FIELD_DOWN').'" title="'.$gL10n->get('ORG_FIELD_DOWN').'" /></a>',
        $description,
        $mandatory,
    	$keyFieldText[$keyField->getValue('kmf_type')],
        $kmfSystem
    );
    $table->addRowByArray($columnValues, 'row_kmf_'.$keyField->getValue('kmf_id'));
}

$page->addHtml($table->show());
$page->show();
