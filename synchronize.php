<?php
/**
 ***********************************************************************************************
 * Makes a user to a former if he does not have a key.
 *
 * @copyright 2004-2023 The Admidio Team
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

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'preview', 'validValues' => array('preview', 'write', 'print')));

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$user = new User($gDb, $gProfileFields);

$icon = array();
$icon['member'] = array('image' => 'fa-user', 'text' => $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))));
$icon['not_member'] = array('image' => 'fa-user-slash', 'text' => $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))));
$icon['error'] = array('image' => 'fa-times', 'text' => $gL10n->get('SYS_ERROR'));

// set headline of the script
$headline = $gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE');

if (!StringUtils::strContains($gNavigation->getUrl(), 'synchronize.php'))
{
    $gNavigation->addUrl(CURRENT_URL, $headline);
}

// create html page object
$page = new HtmlPage('plg-keymanager-synchronize', $headline);

if ($getMode == 'preview')     //Default
{
	$members = array();
	
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
               AND cat_org_id = '.$gCurrentOrgId. ') ';
	
	$userStatement = $gDb->queryPrepared($sql, array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), DATE_NOW, DATE_NOW));
	
	while ($row = $userStatement->fetch())
	{
		$members[$row['usr_id']] = array(
				'last_name' => $row['last_name'],
				'first_name' => $row['first_name'],
				'count' => 0,
				'delete_marker' => true,
				'info' => '<i class="fas '.$icon['member']['image'].'" data-toggle="tooltip" title="'.$icon['member']['text'].'"></i> -> <i class="fas '.$icon['not_member']['image'].'" data-toggle="tooltip" title="'.$icon['not_member']['text'].'"></i>');
				
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
               AND ( kmf_org_id = ? -- $gCurrentOrgId
              	OR kmf_org_id IS NULL )
          ORDER BY last_name.usd_value, first_name.usd_value ASC';
	
	$receiverStatement =  $gDb->queryPrepared($sql, array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), $gCurrentOrgId));
	
	while ($row = $receiverStatement->fetch())
	{
		$members[$row['kmd_value']]['info'] = '';
		$members[$row['kmd_value']]['delete_marker'] = false;
		$members[$row['kmd_value']]['count']++;
	} 
	
	$form = new HtmlForm('synchronize_preview_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/synchronize.php', array('mode' => 'write')), $page);
	
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
        $columnValues[] = '<i class="fas fa-key" data-toggle="tooltip" title="'.$gL10n->get('PLG_KEYMANAGER_NUMBER_OF_KEYS').'"></i>';
		$columnValues[] = '<i class="fas fa-info-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_INFORMATIONS').'"></i>';
		$table->addRowHeadingByArray($columnValues);
		
		foreach ($members as $memberId => $data)
		{
            $user->readDataById($memberId);
        
			$columnValues = array();
			$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$data['last_name'].', '.$data['first_name'].'</a>';
			$columnValues[] = $data['count'];
			$columnValues[] = $data['info'];
			$table->addRowByArray($columnValues);
		}
		
		$page->addHtml($table->show(false));

		if (array_search(true, array_column($members, 'delete_marker')))
		{
            $form->addSubmitButton('btn_next_page', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' btn-primary'));
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
            window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/synchronize.php', array('mode' => 'print')).'", "_blank");
        });',
			true
			);

	// links to print and exports
	$page->addPageFunctionsMenuItem('menu_item_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
	
	$form = new HtmlForm('synchronize_saved_form', null, $page);
	
	$datatable = true;
	$hoverRows = true;
	$classTable  = 'table table-condensed';
	$table = new HtmlTable('table_saved_synchronize', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'center'));
    $columnValues = array($gL10n->get('SYS_NAME'), '<i class="fas fa-info-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_INFORMATIONS').'"></i>');
	$table->addRowHeadingByArray($columnValues);
	
	$member = new TableMembers($gDb);
	$errorMessage = '';
	
	foreach ($_SESSION['pKeyManager']['synchronize'] as $memberId => $data)
	{
		if ($data['delete_marker'] == true)
		{
            $user->readDataById($memberId);
		    
            $delete_marker = true;
			$columnValues = array();
			$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$data['last_name'].', '.$data['first_name'].'</a>';

			$sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              		  FROM '.TBL_MEMBERS.'
       		    INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = 1
                       AND ( cat_org_id = ? -- $gCurrentOrgId
                        OR cat_org_id IS NULL )
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                       AND mem_usr_id = ? -- $memberId ';
			
			$membersStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, DATE_NOW, DATE_NOW, $memberId));
			
			try {
			    while ($row = $membersStatement->fetch()) {
			        // stop all role memberships of this organization
			        $role = new TableRoles($gDb, $row['mem_rol_id']);
			        $role->stopMembership($memberId);
			    }
			} 
			catch (AdmException $e) 
			{
			    $delete_marker = false;
			}
			
			if ($delete_marker)
			{
			    $columnValues[] = '<i class="fas '.$icon['not_member']['image'].'" data-toggle="tooltip" title="'.$icon['not_member']['text'].'"></i>';
			}
			else
			{
			    $columnValues[] = '<i class="fas '.$icon['error']['image'].'" data-toggle="tooltip" title="'.$icon['error']['text'].'"></i>';
			    $errorMessage .= '<br/>-'.$data['last_name'].', '.$data['first_name'];
			    $_SESSION['pKeyManager']['synchronize'][$memberId]['delete_marker'] = false;
			}
			
			$table->addRowByArray($columnValues);
		}
	}
	
	$page->addHtml($table->show(false));
	$form->addDescription('<strong>'.$gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_SAVED').'</strong>');
	if ($errorMessage != '')
	{
	    $form->addDescription($gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE_ERROR', array('<i class="fas '.$icon['error']['image'].'" ></i>')).$errorMessage);
	}
	
	//seltsamerweise wird in diesem Abschnitt nichts angezeigt wenn diese Anweisung fehlt
	$form->addStaticControl('', '', '');
	
	$page->addHtml($form->show(false));
}
elseif ($getMode == 'print')
{	
    $gNavigation->clear();
    
	// create html page object without the custom theme files
	$hoverRows = false;
	$datatable = false;
	$classTable  = 'table table-condensed table-striped';
	$page->setPrintMode();
	$page->setHeadline($gL10n->get('PLG_KEYMANAGER_SYNCHRONIZE'));
	$table = new HtmlTable('table_print_synchronize', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'center'));
	$columnValues = array($gL10n->get('SYS_NAME'),  '<i class="fas fa-info-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_INFORMATIONS').'"></i>');
	$table->addRowHeadingByArray($columnValues);
	
	foreach ($_SESSION['pKeyManager']['synchronize'] as $member => $data)
	{
		if ($data['delete_marker'] == true)
		{
			$columnValues = array();
			$columnValues[] = $data['last_name'].', '. $data['first_name'];
			$columnValues[] = '<i class="fas '.$icon['not_member']['image'].'" data-toggle="tooltip" title="'.$icon['not_member']['text'].'"></i>';
			$table->addRowByArray($columnValues);
		}
	}
	$page->addHtml($table->show(false));
}

$page->show();


