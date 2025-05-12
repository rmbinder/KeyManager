<?php
/**
 ***********************************************************************************************
 * Create or edit a key profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * key_id    : ID of the key who should be edited
 * copy      : true - The key of the key_id will be copied and the base for this new key
 *
 *****************************************************************************/

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Utils\SecurityUtils;

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getKeyId = admFuncVariableIsValid($_GET, 'key_id', 'int');
$getCopy  = admFuncVariableIsValid($_GET, 'copy',   'bool');

$pPreferences = new ConfigTablePKM();
$pPreferences->read();

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);

// set headline of the script
if ($getCopy)
{
	$getKeyId = 0;
}

if ($getKeyId === 0)
{
    $headline = $gL10n->get('PLG_KEYMANAGER_KEY_CREATE');
}
else
{
    $headline = $gL10n->get('PLG_KEYMANAGER_KEY_EDIT');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('plg-keymanager-keys-edit-new', $headline);
$page->addJavascriptFile('adm_program/libs/zxcvbn/dist/zxcvbn.js');

// don´t show menu items (copy/print...) if a new key is created
if ($getKeyId != 0)
{
	// show link to view profile field change history
    if ($gSettingsManager->getBool('profile_log_edit_fields')  )
	{
        $page->addPageFunctionsMenuItem('menu_item_change_history', $gL10n->get('SYS_CHANGE_HISTORY'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_history.php', array('key_id' => $getKeyId)), 'fa-history');
	} 

	if (isUserAuthorizedForPreferences())
	{
        $page->addPageFunctionsMenuItem('menu_copy_key', $gL10n->get('PLG_KEYMANAGER_KEY_COPY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php', array('key_id' => $getKeyId, 'copy' => 1)), 'fa-clone');                            
	}
}

// create html form
$form = new HtmlForm('edit_key_form', SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_save.php', array('key_id' => $getKeyId)), $page);

foreach ($keys->mKeyFields as $keyField)
{
	if (!isUserAuthorizedForPreferences())
	{
		$fieldProperty = HtmlForm::FIELD_DISABLED;
	}
	else 
	{
		$fieldProperty = HtmlForm::FIELD_DEFAULT;
	}
	
    // add key fields to form
    $helpId        = '';
    $kmfNameIntern = $keyField->getValue('kmf_name_intern');
       
    if ($keyField->getValue('kmf_type') === 'DATE' && $keyField->getValue('kmf_sequence') === '1')
    {
    	// Wenn in den dargestellten Schluesselfeldern an erster Stelle (ganz oben) ein Datumsfeld steht,
        // dann werden in diesem Feld alle Datumsangaben nach amerikanischer Norm dargestellt (05/03/2017),
        // und auch in diesem Format in der DB gespeichert. Die Ursache konnte nicht festgestellt werden.
        // Es scheint, als wird im Script zu spaet auf das eingestellte Datumsformat initialisiert.
        // Mit folgendem Workaround funktioniert es:
        // Input-Feld vom Typ "date" mit Property "HIDDEN"
        $form->addInput('dummy','dummy', 'dummy', array('type' => 'date', 'property' => HtmlForm::FIELD_HIDDEN) );
    }

    if ($keys->getProperty($kmfNameIntern, 'kmf_mandatory') == 1 && isUserAuthorizedForPreferences())
    {
        // set mandatory field
        $fieldProperty = HtmlForm::FIELD_REQUIRED;
    }

    // code for different field types
    if ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'CHECKBOX')
    {
        $form->addCheckbox(
            'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'),
            $keys->getProperty($kmfNameIntern, 'kmf_name'),
            (bool) $keys->getValue($kmfNameIntern),
            array(
                'property'        => $fieldProperty,
                'helpTextId' => $helpId,
                'icon'            => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database')
            )
        );
    }  
    elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'DROPDOWN' )
    {
        $arrListValues = $keys->getProperty($kmfNameIntern, 'kmf_value_list');
        $defaultValue  = $keys->getValue($kmfNameIntern, 'database');
 
        $form->addSelectBox(
            'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'),
            $keys->getProperty($kmfNameIntern, 'kmf_name'),
            $arrListValues,
            array(
                'property'        => $fieldProperty,
                'defaultValue'    => $defaultValue,
                'helpTextId' => $helpId,
                'icon'            => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database')
            )
        );
    }
    elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'RADIO_BUTTON')
    {
        $showDummyRadioButton = false;
        if ($keys->getProperty($kmfNameIntern, 'kmf_mandatory') == 0)
        {
            $showDummyRadioButton = true;
        }

        $form->addRadioButton(
            'kmf-'.$keys->getProperty($kmfNameIntern, 'kmf_id'),
            $keys->getProperty($kmfNameIntern, 'kmf_name'),
            $keys->getProperty($kmfNameIntern, 'kmf_value_list'),
            array(
                'property'          => $fieldProperty,
                'defaultValue'      => $keys->getValue($kmfNameIntern, 'database'),
                'showNoValueButton' => $showDummyRadioButton,
                'helpTextId'   => $helpId,
                'icon'              => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database')
            )
        );
    }
    elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'TEXT_BIG')
    {
        $form->addMultilineTextInput(
            'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'),
            $keys->getProperty($kmfNameIntern, 'kmf_name'),
            $keys->getValue($kmfNameIntern),
            3,
            array(
                'maxLength'       => 4000,
                'property'        => $fieldProperty,
                'helpTextId' => $helpId,
                'icon'            => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database')
            )
        );
    }
    else
    {
        $fieldType = 'text';
        if ($kmfNameIntern === 'RECEIVER')
        {
        	$memberCondition = ' AND EXISTS
        		(SELECT 1
           		   FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
          		  WHERE mem_usr_id = usr_id
            	    AND mem_rol_id = rol_id
                    AND mem_begin <= \''.DATE_NOW.'\'
                    AND mem_end    > \''.DATE_NOW.'\'
                    AND rol_valid  = 1
                    AND rol_cat_id = cat_id
                    AND ( cat_org_id = '.$gCurrentOrgId. '
                     OR cat_org_id IS NULL )) ';
            	
			$sql = 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value,  IFNULL(CONCAT(\', \', postcode.usd_value),\'\'), IFNULL(CONCAT(\' \', city.usd_value),\'\'), IFNULL(CONCAT(\', \', street.usd_value),\'\')  ) as name
                      FROM '. TBL_USERS. '
                      JOIN '. TBL_USER_DATA. ' as last_name
                        ON last_name.usd_usr_id = usr_id
                       AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                      JOIN '. TBL_USER_DATA. ' as first_name
                        ON first_name.usd_usr_id = usr_id
                       AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                 LEFT JOIN '. TBL_USER_DATA. ' as postcode
                        ON postcode.usd_usr_id = usr_id
                       AND postcode.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '  		
                 LEFT JOIN '. TBL_USER_DATA. ' as city
                        ON city.usd_usr_id = usr_id
                       AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
                 LEFT JOIN '. TBL_USER_DATA. ' as street
                        ON street.usd_usr_id = usr_id
                       AND street.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
                     WHERE usr_valid = 1'.$memberCondition.' ORDER BY last_name.usd_value, first_name.usd_value';

			$form->addSelectBoxFromSql(
            	'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'),
            	convlanguagePKM($keys->getProperty($kmfNameIntern, 'kmf_name')),
            	$gDb, 
            	$sql,
            	array(
            		'property' => $fieldProperty,
            		'helpTextId' => $helpId,
            		'icon' => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database'),
            		'defaultValue' => $keys->getValue($kmfNameIntern),
            		'multiselect' => false
            	)
            );
        }
        else
        {
        	if ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'DATE')
        	{
            	if ($kmfNameIntern === 'BIRTHDAY')
            	{
                	$fieldType = 'birthday';
            	}
            	else
            	{
                	$fieldType = 'date';
            	}
            	$maxlength = '10';
        	}
        	elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') === 'NUMBER')
        	{
            	$fieldType = 'number';
            	$maxlength = array(0, 9999999999, 1);
        	}
        	else
        	{
            	$maxlength = '50';
        	}
        
        	$form->addInput(
        		'kmf-'. $keys->getProperty($kmfNameIntern, 'kmf_id'), 
            	convlanguagePKM($keys->getProperty($kmfNameIntern, 'kmf_name')),
            	$keys->getValue($kmfNameIntern),
            	array(
            		'type' => $fieldType,
                	'maxLength' => $maxlength, 
                	'property' => $fieldProperty, 
                	'helpTextId' => $helpId, 
                	'icon' => $keys->getProperty($kmfNameIntern, 'kmf_icon', 'database')
            	)
        	);
    	}
	}
}

if ($getCopy)
{
	$form->addLine();
	$form->addDescription($gL10n->get('PLG_KEYMANAGER_COPY_PREFERENCES').'<br/>');
	$form->addInput('copy_number', $gL10n->get('PLG_KEYMANAGER_NUMBER'), 1, array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'PLG_KEYMANAGER_NUMBER_DESC'));
	$sql = 'SELECT kmf_id, kmf_name
              FROM '.TBL_KEYMANAGER_FIELDS.'
             WHERE kmf_type = \'NUMBER\'
               AND ( kmf_org_id = '.$gCurrentOrgId.'
                OR kmf_org_id IS NULL )';
	$form->addSelectBoxFromSql('copy_field', $gL10n->get('PLG_KEYMANAGER_FIELD'), $gDb, $sql, array('multiselect' => false, 'helpTextId' => 'PLG_KEYMANAGER_FIELD_DESC'));
	$form->addLine();
}

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => 'offset-sm-3'));

$infoKey = new Entity($gDb, TBL_KEYMANAGER_KEYS, 'kmk', (int) $getKeyId);

// show information about key who creates the recordset and changed it
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $infoKey->getValue('kmk_usr_id_create'), $infoKey->getValue('kmk_timestamp_create'),
    (int) $infoKey->getValue('kmk_usr_id_change'), $infoKey->getValue('kmk_timestamp_change')
));

$page->addHtml($form->show(false));
$page->show();
