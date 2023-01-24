<?php
/**
 ***********************************************************************************************
 * Menu preferences
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

//read formfiller configuration if plugin formfiller is installed
if ($pPreferences->isPffInst())
{
	$pPreferences->readPff();
}

$headline = $gL10n->get('SYS_SETTINGS');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('plg-keymanager-preferences', $headline);

$page->addJavascript('
        $("#tabs_nav_preferences").attr("class", "active");
        $("#tabs-preferences").attr("class", "tab-pane active");', 
        true
);
    
    
$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {

                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                }
            }
        });
    });',
    true
);
                    
$page->addHtml('
<ul id="preferences_tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="tabs_nav_preferences" class="nav-link" href="#tabs-preferences" data-toggle="tab" role="tab">'.$gL10n->get('SYS_SETTINGS').'</a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade" id="tabs-preferences" role="tabpanel">
        <div class="accordion" id="accordion_preferences">');

// PANEL: KEYCREATE

$formKeyCreate = new HtmlForm('keycreate_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php', array('key_id' => 0)), $page);
$formKeyCreate->addSubmitButton('btn_save_keycreate', $gL10n->get('PLG_KEYMANAGER_KEY_CREATE'), array('icon' => 'fa-plus-circle', 'class' => 'offset-sm-3'));
$formKeyCreate->addCustomContent('', $gL10n->get('PLG_KEYMANAGER_KEY_CREATE_DESC'));

$page->addHtml(getPreferencePanel('preferences', 'keycreate', $gL10n->get('PLG_KEYMANAGER_KEY_CREATE'), 'fas fa-plus-circle', $formKeyCreate->show()));

// PANEL: KEYFIELDS

$formKeyFields = new HtmlForm('keyfields_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields.php'), $page);    
$formKeyFields->addSubmitButton('btn_save_keyfields', $gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE'), array('icon' => 'fa-edit', 'class' => 'offset-sm-3'));
$formKeyFields->addCustomContent('', $gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE_DESC'));
                        
$page->addHtml(getPreferencePanel('preferences', 'keyfields', $gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE'), 'fas fa-edit', $formKeyFields->show()));

// PANEL: SYNCHRONIZE

unset($_SESSION['pKeyManager']['synchronize']);

$formSynchronize = new HtmlForm('synchronize_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/synchronize.php'), $page);                        
$formSynchronize->addSubmitButton('btn_save_synchronize', $gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE'), array('icon' => 'fa-sync', 'class' => ' offset-sm-3'));
$formSynchronize->addCustomContent('', $gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_DESC'));
  
$page->addHtml(getPreferencePanel('preferences', 'synchronize', $gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE'), 'fas fa-sync', $formSynchronize->show()));
        
// PANEL: INTERFACE_PFF

// show menu item 'interface to formfiller' only if plugin formfiller is installed
if ($pPreferences->isPffInst())
{                      
    $formInterfacePFF = new HtmlForm('interface_pff_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'interface_pff')), $page, array('class' => 'form-preferences'));
    $formInterfacePFF->addSelectBox('interface_pff', $gL10n->get('PLG_KEYMANAGER_CONFIGURATION'), $pPreferences->configpff['Formular']['desc'], array( 'defaultValue' => $pPreferences->config['Optionen']['interface_pff'], 'showContextDependentFirstEntry' => false));
    $formInterfacePFF->addCustomContent('', $gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF_DESC'));
    $formInterfacePFF->addSubmitButton('btn_save_interface_pff', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
 
    $page->addHtml(getPreferencePanel('preferences', 'interface_pff', $gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF'), 'fas fa-file-pdf', $formInterfacePFF->show()));
}                      
  
// PANEL: PROFILE ADDIN

$formProfileAddin = new HtmlForm('profile_addin_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'profile_addin')), $page, array('class' => 'form-preferences'));

$keys = new Keys($gDb, $gCurrentOrgId);
$valueList = array();
foreach ($keys->mKeyFields as $keyField)
{
    if (!in_array($keyField->getValue('kmf_name_intern'), array('KEYNAME', 'RECEIVER', 'RECEIVED_ON'), true))
    {
        $valueList[$keyField->getValue('kmf_name_intern')] = $keyField->getValue('kmf_name');
    }
}

$formProfileAddin->addSelectBox('profile_addin', $gL10n->get('PLG_KEYMANAGER_KEYFIELD'), $valueList, array('defaultValue' => $pPreferences->config['Optionen']['profile_addin'], 'showContextDependentFirstEntry' => true, 'helpTextIdInline' => 'PLG_KEYMANAGER_PROFILE_ADDIN_DESC', 'helpTextIdLabel' => 'PLG_KEYMANAGER_PROFILE_ADDIN_DESC2'));
$formProfileAddin->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('preferences', 'profile_addin', $gL10n->get('PLG_KEYMANAGER_PROFILE_ADDIN'), 'fas fa-users-cog', $formProfileAddin->show()));

// PANEL: EXPORT

$formExport = new HtmlForm('export_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'export')), $page, array('class' => 'form-preferences'));
$formExport->addInput('file_name', $gL10n->get('PLG_KEYMANAGER_FILE_NAME'), $pPreferences->config['Optionen']['file_name'], array('helpTextIdLabel' => 'PLG_KEYMANAGER_FILE_NAME_DESC', 'property' => HtmlForm::FIELD_REQUIRED));
$formExport->addCheckbox('add_date', $gL10n->get('PLG_KEYMANAGER_ADD_DATE'), $pPreferences->config['Optionen']['add_date'], array('helpTextIdInline' => 'PLG_KEYMANAGER_ADD_DATE_DESC'));
$formExport->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('preferences', 'export', $gL10n->get('PLG_KEYMANAGER_EXPORT'), 'fas fa-file-export', $formExport->show()));

// PANEL: DEINSTALLATION

$formDeinstallation = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('mode' => 2)), $page);
$formDeinstallation->addSubmitButton('btn_save_deinstallation', $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'offset-sm-3'));
$formDeinstallation->addCustomContent('', ''.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION_DESC'));

$page->addHtml(getPreferencePanel('preferences', 'deinstallation', $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION'), 'fas fa-trash-alt', $formDeinstallation->show()));

// PANEL: ACCESS_PREFERENCES
                    
$formAccessPreferences = new HtmlForm('access_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'access_preferences')), $page, array('class' => 'form-preferences'));

$sql = 'SELECT rol.rol_id, rol.rol_name, cat.cat_name
          FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
         WHERE cat.cat_id = rol.rol_cat_id
           AND ( cat.cat_org_id = '.$gCurrentOrgId.'
            OR cat.cat_org_id IS NULL )
      ORDER BY cat_sequence, rol.rol_name ASC';
$formAccessPreferences->addSelectBoxFromSql('access_preferences', '', $gDb, $sql, array('defaultValue' => $pPreferences->config['access']['preferences'], 'helpTextIdInline' => 'PLG_KEYMANAGER_ACCESS_PREFERENCES_DESC', 'multiselect' => true));
$formAccessPreferences->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('preferences', 'access_preferences', $gL10n->get('PLG_KEYMANAGER_ACCESS_PREFERENCES'), 'fas fa-key', $formAccessPreferences->show()));
                  
// PANEL: PLUGIN INFORMATIONS

$formPluginInformations = new HtmlForm('plugin_informations_preferences_form', null, $page, array('class' => 'form-preferences'));                   
$formPluginInformations->addStaticControl('plg_name', $gL10n->get('PLG_KEYMANAGER_PLUGIN_NAME'), $gL10n->get('PLG_KEYMANAGER_NAME_OF_PLUGIN'));
$formPluginInformations->addStaticControl('plg_version', $gL10n->get('PLG_KEYMANAGER_PLUGIN_VERSION'), $pPreferences->config['Plugininformationen']['version']);
$formPluginInformations->addStaticControl('plg_date', $gL10n->get('PLG_KEYMANAGER_PLUGIN_DATE'), $pPreferences->config['Plugininformationen']['stand']);

$page->addHtml(getPreferencePanel('preferences', 'plugin_informations', $gL10n->get('PLG_KEYMANAGER_PLUGIN_INFORMATION'), 'fas fa-info-circle', $formPluginInformations->show()));

$page->addHtml('
        </div>
    </div>
</div>');                    
                     
$page->show();
