<?php
/**
 ***********************************************************************************************
 * Save key data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * key_id    : >0 -  ID of the key who should be saved
 * 			   0  -  a new key will be added 
 * copy_numer: number of new keys
 * copy_field: field for a current number
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/../classes/keys.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getKeyId       = admFuncVariableIsValid($_GET, 'key_id',  'int');
$postCopyNumber = admFuncVariableIsValid($_POST, 'copy_number', 'numeric', array('defaultValue' => 1));
$postCopyField  = admFuncVariableIsValid($_POST, 'copy_field',  'int');

$keys = new Keys($gDb, $gCurrentOrgId);

$startIdx = 1;
if ($postCopyField > 0)												// a field for a current number was selected	
{
	if (isset($_POST['kmf-'. $postCopyField]))
	{
		$startIdx = (int) $_POST['kmf-'. $postCopyField] +1;
	}
}
$stopIdx = $startIdx + $postCopyNumber;

for ($i = $startIdx; $i < $stopIdx; ++$i)
{
	$_POST['kmf-'. $postCopyField] = $i;

	$keys->readKeyData($getKeyId, $gCurrentOrgId);
	
	if ($getKeyId == 0)
	{
		$keys->getNewKeyId();
	}

	// check all key fields
	foreach ($keys->mKeyFields as $keyField)
	{
    	$postId = 'kmf-'. $keyField->getValue('kmf_id');

    	if (isset($_POST[$postId]))
   	 	{
        	if ((strlen($_POST[$postId]) === 0 && $keyField->getValue('kmf_mandatory') == 1))
        	{
            	$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array(convlanguagePKM($keyField->getValue('kmf_name')))));
            	// => EXIT
        	}

        	// Wert aus Feld in das Key-Klassenobjekt schreiben
        	$returnCode = $keys->setValue($keyField->getValue('kmf_name_intern'), $_POST[$postId]);

        	// Fehlermeldung
        	if (!$returnCode)
        	{
        		$gMessage->show($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
        		// => EXIT
        	}   
    	}
    	else
    	{
        	// Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
        	if ($keyField->getValue('kmf_type') === 'CHECKBOX')
        	{
            	$keys->setValue($keyField->getValue('kmf_name_intern'), '0');
        	}
    	}
	}

	/*------------------------------------------------------------*/
	// Save key data to database
	/*------------------------------------------------------------*/
	$gDb->startTransaction();

	try
	{
		$keys->saveKeyData();
	}
	catch(AdmException $e)
	{
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    	$gNavigation->deleteLastUrl();
    	$e->showHtml();
    	// => EXIT
	}
	$gDb->endTransaction();
}

$gNavigation->deleteLastUrl();

// go back to key view
if ($gNavigation->count() > 2)                               // only in key copy 
{
	$gNavigation->deleteLastUrl();
}
	
$gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
$gMessage->show($gL10n->get('SYS_SAVE_DATA'));


