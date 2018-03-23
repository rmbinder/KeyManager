<?php
/**
 ***********************************************************************************************
 * Menu preferences
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
{
    $gMessage->setForwardUrl($gHomepage, 3000);
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

//read formfiller configuration if plugin formfiller is installed
if ($pPreferences->isPffInst())
{
	$pPreferences->readPff();
}

$headline = $gL10n->get('PLG_KEYMANAGER_KEYMANAGER');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('$("#tabs_nav_options").attr("class", "active");
    $("#tabs-options").attr("class", "tab-pane active");
    ', true);

$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        $("#"+id+" .form-alert").hide();

        // disable default form submit
        event.preventDefault();

        $.ajax({
            type:    "POST",
            url:     action,
            data:    $(this).serialize(),
            success: function(data) {
                if(data == "success") {
                    $("#"+id+" .form-alert").attr("class", "alert alert-success form-alert");
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    $("#"+id+" .form-alert").fadeIn("slow");
                    $("#"+id+" .form-alert").animate({opacity: 1.0}, 2500);
                    $("#"+id+" .form-alert").fadeOut("slow");
                }
                else if(data == "convert_error") {
                    $("#"+id+" .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-remove\"></span><strong>'.$gL10n->get('PLG_KEYMANAGER_NO_DATA_TO_CONVERT').'</strong>");
                    $("#"+id+" .form-alert").fadeIn("slow");
                    $("#"+id+" .form-alert").animate({opacity: 1.0}, 10000);
                    $("#"+id+" .form-alert").fadeOut("slow");
                }
                else {
                    $("#"+id+" .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#"+id+" .form-alert").fadeIn();
                    $("#"+id+" .form-alert").html("<span class=\"glyphicon glyphicon-remove\"></span>"+data);
                }
            }
        });
    });
    ', true);

// create module menu with back link
$headerMenu = new HtmlNavbar('menu_preferences', $headline, $page);
$headerMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml($headerMenu->show(false));

$page->addHtml('
<ul class="nav nav-tabs" id="preferences_tabs">
	<li id="tabs_nav_options"><a href="#tabs-options" data-toggle="tab">'.$gL10n->get('PLG_KEYMANAGER_SETTINGS').'</a></li>
</ul>

<div class="tab-content">	
    <div class="tab-pane" id="tabs-options">
        <div class="panel-group" id="accordion_options">

			<div class="panel panel-default" id="panel_keyfields">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_options" href="#collapse_keyfields">
                            <img src="'. THEME_URL .'/icons/edit.png" alt="'.$gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE').'" />'.$gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_keyfields" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('keyfields_form', null, $page, array('class' => 'form-preferences'));
                        $form->addButton('btn_keyfields', $gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE'), array('icon' => THEME_URL .'/icons/download.png', 'link' => 'fields.php', 'class' => 'btn-primary col-sm-offset-3'));
                        $form->addCustomContent('', '<br/>'.$gL10n->get('PLG_KEYMANAGER_KEYFIELDSMANAGE_DESC'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
                    	
            <div class="panel panel-default" id="panel_deinstallation">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_options" href="#collapse_deinstallation">
                            <img src="'. THEME_URL .'/icons/delete.png" alt="'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION').'" title="'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION').'" />'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_deinstallation" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('configurations_form', null , $page, array('class' => 'form-preferences'));
                        $form->addButton('btn_deinstallation', $gL10n->get('PLG_KEYMANAGER_DEINSTALLATION'), array('icon' => THEME_URL .'/icons/delete.png', 'link' => 'preferences_function.php?mode=2', 'class' => 'btn-primary col-sm-offset-3'));
                        $form->addCustomContent('', '<br/>'.$gL10n->get('PLG_KEYMANAGER_DEINSTALLATION_DESC'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>');
                    
            // show menu item 'interface to formfiller' only if plugin formfiller is installed
            if ($pPreferences->isPffInst())
            { 
        		$page->addHtml('<div class="panel panel-default" id="panel_interface_pff">
                	<div class="panel-heading">
                    	<h4 class="panel-title">
                        	<a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_options" href="#collapse_interface_pff">
                            	<img src="'. THEME_URL .'/icons/print.png" alt="'.$gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF').'" title="'.$gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF').'" />'.$gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF').'
                        	</a>
                    	</h4>
                	</div>
                	<div id="collapse_interface_pff" class="panel-collapse collapse">
                    	<div class="panel-body">');
                        	// show form
                    		$form = new HtmlForm('interface_pff_form', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php?form=interface_pff', $page, array('class' => 'form-preferences'));
                        	$form->addSelectBox('interface_pff', $gL10n->get('PLG_KEYMANAGER_CONFIGURATION'), $pPreferences->configpff['Formular']['desc'], array( 'defaultValue' => $pPreferences->config['Optionen']['interface_pff'], 'showContextDependentFirstEntry' => false));
                        	$form->addCustomContent('', '<br/>'.$gL10n->get('PLG_KEYMANAGER_INTERFACE_PFF_DESC'));
                        	$form->addSubmitButton('btn_save_interface_pff', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL .'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        	$page->addHtml($form->show(false));
                    	$page->addHtml('</div>
                	</div>
            	</div>');
            }
            
            $page->addHtml('<div class="panel panel-default" id="panel_plugin_control">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_options" href="#collapse_plugin_control">
                            <img src="'. THEME_URL .'/icons/lock.png" alt="'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_CONTROL').'" title="'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_CONTROL').'" />'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_CONTROL').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_plugin_control" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // show form
                        $form = new HtmlForm('plugin_control_preferences_form', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php?form=plugin_control', $page, array('class' => 'form-preferences'));
                        $sql = 'SELECT rol.rol_id, rol.rol_name, cat.cat_name
                                FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
                                WHERE cat.cat_id = rol.rol_cat_id
                                AND (  cat.cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                                OR cat.cat_org_id IS NULL )';
                        $form->addSelectBoxFromSql('freigabe', $gL10n->get('PLG_KEYMANAGER_ROLE_SELECTION'), $gDb, $sql, array('defaultValue' => $pPreferences->config['Pluginfreigabe']['freigabe'], 'helpTextIdInline' => 'PLG_KEYMANAGER_ROLE_SELECTION_DESC', 'multiselect' => true, 'property' => FIELD_REQUIRED));
                        $form->addSelectBoxFromSql('freigabe_config', '', $gDb, $sql, array('defaultValue' => $pPreferences->config['Pluginfreigabe']['freigabe_config'], 'helpTextIdInline' => 'PLG_KEYMANAGER_ROLE_SELECTION_DESC2', 'multiselect' => true, 'property' => FIELD_REQUIRED));
                        $form->addSubmitButton('btn_save_plugin_control_preferences', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL .'/icons/disk.png', 'class' => ' col-sm-offset-3'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
                    		
            <div class="panel panel-default" id="panel_plugin_informations">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a class="icon-text-link" data-toggle="collapse" data-parent="#accordion_options" href="#collapse_plugin_informations">
                            <img src="'. THEME_URL .'/icons/info.png" alt="'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_INFORMATION').'" title="'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_INFORMATION').'" />'.$gL10n->get('PLG_KEYMANAGER_PLUGIN_INFORMATION').'
                        </a>
                    </h4>
                </div>
                <div id="collapse_plugin_informations" class="panel-collapse collapse">
                    <div class="panel-body">');
                        // create a static form
                        $form = new HtmlForm('plugin_informations_preferences_form', null, $page);
                        $form->addStaticControl('plg_name', $gL10n->get('PLG_KEYMANAGER_PLUGIN_NAME'), $gL10n->get('PLG_KEYMANAGER_NAME_OF_PLUGIN'));
                        $form->addStaticControl('plg_version', $gL10n->get('PLG_KEYMANAGER_PLUGIN_VERSION'), $pPreferences->config['Plugininformationen']['version']);
                        $form->addStaticControl('plg_date', $gL10n->get('PLG_KEYMANAGER_PLUGIN_DATE'), $pPreferences->config['Plugininformationen']['stand']);
                    //    $html = '<a class="btn" href="http://www.admidio.de/dokuwiki/doku.php?id=de:plugins:formfiller" target="_blank"><img
                    //                src="'. THEME_URL . '/icons/eye.png" alt="'.$gL10n->get('PLG_KEYMANAGER_DOCUMENTATION_OPEN').'" />'.$gL10n->get('PLG_KEYMANAGER_DOCUMENTATION_OPEN').'</a>';
                    //    $form->addCustomContent($gL10n->get('PLG_KEYMANAGER_DOCUMENTATION'), $html, array('helpTextIdInline' => 'PLG_KEYMANAGER_DOCUMENTATION_OPEN_DESC'));
                        $page->addHtml($form->show(false));
                    $page->addHtml('</div>
                </div>
            </div>
                    		
        </div>
    </div>
</div>
');

$page->show();
