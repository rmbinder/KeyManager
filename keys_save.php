<?php
/**
 ***********************************************************************************************
 * Save key data
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * key_id    : ID of the key who should be saved
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getKeyId  = admFuncVariableIsValid($_GET, 'key_id',  'int');

$keys = new Keys($gDb, ORG_ID);
$keys->readKeyData($getKeyId, ORG_ID);

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
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', convlanguagePKM($keyField->getValue('kmf_name'))));
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

$gNavigation->deleteLastUrl();


// go back to key view
if ($gNavigation->count() > 2)                               // only in key copy 
{
	$gNavigation->deleteLastUrl();
}
	
$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
$gMessage->show($gL10n->get('SYS_SAVE_DATA'));


