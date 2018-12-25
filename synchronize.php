<?php
/**
 ***********************************************************************************************
 * Makes a user to a former if he does not have a key.
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode       : preview - preview 
 *              write   - make user to former 
 *              print   - preview for printing  
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'preview', 'validValues' => array('preview', 'write', 'print')));

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

$icon = array();
$icon['member'] = array('image' => 'profile.png', 'text' => $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))));
$icon['not_member'] = array('image' => 'no_profile.png', 'text' => $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))));

// set headline of the script
$headline = $gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE');

// create html page object
$page = new HtmlPage($headline);

if ($getMode == 'preview')     //Default
{
	$members = array();
	
	$user = new User($gDb, $gProfileFields);
	
	// read in all members
	$sql = 'SELECT usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name
              FROM '.TBL_USERS.'
         LEFT JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
           	   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE usr_valid = 1
        AND EXISTS (SELECT 1
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES.  ','. TBL_USER_DATA. '
             WHERE mem_usr_id = usr_id
               AND mem_rol_id = rol_id
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND rol_valid  = 1
               AND rol_cat_id = cat_id
               AND cat_org_id = '. ORG_ID. ') ';
	
	$userStatement = $gDb->queryPrepared($sql, array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), DATE_NOW, DATE_NOW));
	
	while ($row = $userStatement->fetch())
	{
		$members[$row['usr_id']] = array(
				'last_name' => $row['last_name'],
				'first_name' => $row['first_name'],
				'count' => 0,
				'delete_marker' => true,
				'info' => '<img src="'.THEME_URL.'/icons/'.$icon['member']['image'].'" alt="'.$icon['member']['text'].'" title="'.$icon['member']['text'].'" /> -> <img src="'.THEME_URL.'/icons/'.$icon['not_member']['image'].'" alt="'.$icon['not_member']['text'].'" title="'.$icon['not_member']['text'].'" />');
				
		$user->readDataById($row['usr_id']);
		
		if ($user->isAdministrator() || $gCurrentUser->getValue('usr_id') == $row['usr_id'])
		{
			$members[$row['usr_id']]['info'] = $gL10n->get('PLG_KEYMANAGER_SPECIAL_CASE_CURUSER_OR_ADMIN');
			$members[$row['usr_id']]['delete_marker'] = false;
		}
	}
	
	// read in all receiver
	$sql = 'SELECT kmd_value, last_name.usd_value as last_name , first_name.usd_value as first_name
              FROM '.TBL_KEYMANAGER_DATA.'
        INNER JOIN '.TBL_KEYMANAGER_FIELDS.'
                ON kmf_id = kmd_kmf_id
         LEFT JOIN '. TBL_USER_DATA. ' as last_name
                ON last_name.usd_usr_id = kmd_value
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
         LEFT JOIN '. TBL_USER_DATA. ' as first_name
                ON first_name.usd_usr_id = kmd_value
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE kmf_name_intern = \'RECEIVER\'
               AND ( kmf_org_id = '.ORG_ID.'
              	OR kmf_org_id IS NULL )
          ORDER BY last_name.usd_value, first_name.usd_value ASC';
	
	$receiverStatement =  $gDb->queryPrepared($sql, array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id')));
	
	while ($row = $receiverStatement->fetch())
	{
		$members[$row['kmd_value']]['info'] = '';
		$members[$row['kmd_value']]['delete_marker'] = false;
		$members[$row['kmd_value']]['count']++;
	} 
	
	$headerMenu = $page->getMenu();
	$headerMenu->addItem('menu_item_back', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', $gL10n->get('SYS_BACK'), 'back.png');
	
	$form = new HtmlForm('synchronize_preview_form', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/synchronize.php?mode=write', $page);
	
	if (sizeof($members) > 0)
	{
		// save members in session (for mode write and mode print)
		$_SESSION['pKeyManager']['synchronize'] = $members;
		
		$datatable = true;
		$hoverRows = true;
		$classTable  = 'table table-condensed';
		$table = new HtmlTable('table_new_synchronize', $page, $hoverRows, $datatable, $classTable);
		$table->setColumnAlignByArray(array('left', 'center', 'center'));
		$columnValues = array();
		$columnValues[] = $gL10n->get('SYS_NAME');
		$columnValues[] = '<img src="'.THEME_URL.'/icons/key.png" alt="'.$gL10n->get('PLG_KEYMANAGER_NUMBER_OF_KEYS').'" title="'.$gL10n->get('PLG_KEYMANAGER_NUMBER_OF_KEYS').'" />';
		$columnValues[] = '<img src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'" />';
		$table->addRowHeadingByArray($columnValues);
		
		foreach ($members as $memberId => $data)
		{
			$columnValues = array();
			$columnValues[] = '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $memberId)).'">'.$data['last_name'].', '.$data['first_name'].'</a>';
			$columnValues[] = $data['count'];
			$columnValues[] = $data['info'];
			$table->addRowByArray($columnValues);
		}
		
		$page->addHtml($table->show(false));

		if (array_search(true, array_column($members, 'delete_marker')))
		{
			$form->addSubmitButton('btn_next_page', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL .'/icons/disk.png', 'class' => 'btn-primary'));
		}
		$form->addDescription('<br/>'.$gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_PREVIEW'));
		
		//seltsamerweise wird in diesem Abschnitt nichts angezeigt wenn diese Anweisung fehlt
		$form->addStaticControl('', '', '');
	}
	else
	{
		$form->addDescription($gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_NO_ASSIGN'));
		
		//seltsamerweise wird in diesem Abschnitt nichts angezeigt wenn diese Anweisung fehlt
		$form->addStaticControl('', '', '');
	}
	$page->addHtml($form->show(false));
}
elseif ($getMode == 'write')
{
	$page->addJavascript('
    	$("#menu_item_print_view").click(function() {
            window.open("'.ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/synchronize.php?mode=print", "_blank");
        });',
			true
			);

	$headerMenu = $page->getMenu();
	$headerMenu->addItem('menu_item_back', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', $gL10n->get('SYS_BACK'), 'back.png');
	$headerMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');
	
	$form = new HtmlForm('synchronize_saved_form', null, $page);
	
	$datatable = true;
	$hoverRows = true;
	$classTable  = 'table table-condensed';
	$table = new HtmlTable('table_saved_synchronize', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'center'));
	$columnValues = array($gL10n->get('SYS_NAME'), '<img src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'" />');
	$table->addRowHeadingByArray($columnValues);
	
	$member = new TableMembers($gDb);
	
	foreach ($_SESSION['pKeyManager']['synchronize'] as $memberId => $data)
	{
		if ($data['delete_marker'] == true)
		{
			$columnValues = array();
			$columnValues[] = '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $memberId)).'">'.$data['last_name'].', '.$data['first_name'].'</a>';
			$columnValues[] = '<img src="'.THEME_URL.'/icons/'.$icon['not_member']['image'].'" alt="'.$icon['not_member']['text'].'" title="'.$icon['not_member']['text'].'" />';
			$table->addRowByArray($columnValues);
			
			$sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              		  FROM '.TBL_MEMBERS.'
       		    INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = 1
                       AND ( cat_org_id = '.ORG_ID.'
                        OR cat_org_id IS NULL )
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                       AND mem_usr_id = ? -- $memberId';
			
			$membersStatement = $gDb->queryPrepared($sql, array(DATE_NOW, DATE_NOW, $memberId));
			
			while ($row = $membersStatement->fetch())
			{
				// alle Rollen der aktuellen Organisation auf ungueltig setzen
				$member->setArray($row);
				$member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
			}
		}
	}
	
	$page->addHtml($table->show(false));
	$form->addDescription('<strong>'.$gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_SAVED').'</strong>');
	
	//seltsamerweise wird in diesem Abschnitt nichts angezeigt wenn diese Anweisung fehlt
	$form->addStaticControl('', '', '');
	
	$page->addHtml($form->show(false));
}
elseif ($getMode == 'print')
{	
	// create html page object without the custom theme files
	$hoverRows = false;
	$datatable = false;
	$classTable  = 'table table-condensed table-striped';
	$page->hideThemeHtml();
	$page->hideMenu();
	$page->setPrintMode();
	$page->setHeadline($gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE'));
	$table = new HtmlTable('table_print_synchronize', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'center'));
	$columnValues = array($gL10n->get('SYS_NAME'),  '<img src="'.THEME_URL.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'" />');
	$table->addRowHeadingByArray($columnValues);
	
	foreach ($_SESSION['pKeyManager']['synchronize'] as $member => $data)
	{
		if ($data['delete_marker'] == true)
		{
			$columnValues = array();
			$columnValues[] = $data['last_name'].', '. $data['first_name'];
			$columnValues[] = '<img src="'.THEME_URL.'/icons/'.$icon['not_member']['image'].'" alt="'.$icon['not_member']['text'].'" title="'.$icon['not_member']['text'].'" />';
			$table->addRowByArray($columnValues);
		}
	}
	$page->addHtml($table->show(false));
}

$page->show();


