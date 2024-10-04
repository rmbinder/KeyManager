<?php
/**
 ***********************************************************************************************
 * KeyManager
 *
 * Version 2.3.1
 *
 * KeyManager is an Admidio plugin for managing building and room keys.
 * 
 * Note: KeyManager uses the external class XLSXWriter (https://github.com/mk-j/PHP_XLSXWriter)
 * 
 * Author: rmb
 *
 * Compatible with Admidio version 4.3
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
 
/******************************************************************************
 * Parameters:
 *
 * mode              : Output(html, print, csv-ms, csv-oo, pdf, pdfl, xlsx)
 * filter_string     : general filter string
 * filter_keyname    : filter for keyname
 * filter_receiver   : filter for receiver
 * show_all          : 0 - (Default) show active keys only
 *                     1 - show all keys (also made to the former)
 * export_and_filter : 0 - (Default) No filter and export menu
 *                     1 - Filter and export menu is enabled
 * same_side         : 0 - (Default) side was called by another side
 *                     1 - internal call of the side
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

//$scriptName ist der Name wie er im Menue eingetragen werden muss, also ohne evtl. vorgelagerte Ordner wie z.B. /playground/adm_plugins/keymanager...
$scriptName = substr($_SERVER['SCRIPT_NAME'], strpos($_SERVER['SCRIPT_NAME'], FOLDER_PLUGINS));

// only authorized user are allowed to start this module
if (!isUserAuthorized($scriptName))
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isset($_SESSION['pKeyManager']['filter_string']))
{
    $_SESSION['pKeyManager']['filter_string'] = '';
}
if (!isset($_SESSION['pKeyManager']['filter_keyname']))
{
    $_SESSION['pKeyManager']['filter_keyname'] = '';
}
if (!isset($_SESSION['pKeyManager']['filter_receiver']))
{
    $_SESSION['pKeyManager']['filter_receiver'] = 0;
}
if (!isset($_SESSION['pKeyManager']['show_all']))
{
    $_SESSION['pKeyManager']['show_all'] = false;
}
if (!isset($_SESSION['pKeyManager']['export_and_filter']))
{
    $_SESSION['pKeyManager']['export_and_filter'] = false;
}

$getMode            = admFuncVariableIsValid($_GET, 'mode',              'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl', 'xlsx')));
$getFilterString    = admFuncVariableIsValid($_GET, 'filter_string',     'string');
$getFilterKeyName   = admFuncVariableIsValid($_GET, 'filter_keyname',    'string');
$getFilterReceiver  = admFuncVariableIsValid($_GET, 'filter_receiver',   'int');
$getShowAll         = admFuncVariableIsValid($_GET, 'show_all',          'bool', array('defaultValue' => false));
$getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false));
$getSameSide        = admFuncVariableIsValid($_GET, 'same_side',         'bool', array('defaultValue' => false));

if ($getSameSide)
{
    $_SESSION['pKeyManager']['filter_string'] = $getFilterString;
    $_SESSION['pKeyManager']['filter_keyname'] = $getFilterKeyName;
    $_SESSION['pKeyManager']['filter_receiver'] = $getFilterReceiver;
    $_SESSION['pKeyManager']['show_all'] = $getShowAll;
    $_SESSION['pKeyManager']['export_and_filter'] = $getExportAndFilter;
}
else
{
    $getFilterString = $_SESSION['pKeyManager']['filter_string'];
    $getFilterKeyName = $_SESSION['pKeyManager']['filter_keyname'];
    $getFilterReceiver = $_SESSION['pKeyManager']['filter_receiver'];
    $getShowAll = $_SESSION['pKeyManager']['show_all'];
    $getExportAndFilter = $_SESSION['pKeyManager']['export_and_filter'];
}

$pPreferences = new ConfigTablePKM();
if ($pPreferences->checkForUpdate())
{
	$pPreferences->init();
}
else
{
	$pPreferences->read();
}

// initialize some special mode parameters
$separator   = '';
$valueQuotes = '';
$charset     = '';
$classTable  = '';
$orientation = '';
$filename    = umlautePKM($pPreferences->config['Optionen']['file_name']);
if ($pPreferences->config['Optionen']['add_date'])
{
    $filename .= '_'.date('Y-m-d');
}

switch ($getMode)
{
    case 'csv-ms':
        $separator   = ';';  // Microsoft Excel 2007 or new needs a semicolon
        $valueQuotes = '"';  // all values should be set with quotes
        $getMode     = 'csv';
        $charset     = 'iso-8859-1';
        break;
    case 'csv-oo':
        $separator   = ',';   // a CSV file should have a comma
        $valueQuotes = '"';   // all values should be set with quotes
        $getMode     = 'csv';
        $charset     = 'utf-8';
        break;
    case 'pdf':
        $classTable  = 'table';
        $orientation = 'P';
        $getMode     = 'pdf';
        break;
    case 'pdfl':
        $classTable  = 'table';
        $orientation = 'L';
        $getMode     = 'pdf';
        break;
    case 'html':
        $classTable  = 'table table-condensed';
        break;
    case 'print':
        $classTable  = 'table table-condensed table-striped';
        break;
    case 'xlsx':
	    include_once(__DIR__ . '/libs/PHP_XLSXWriter/xlsxwriter.class.php');
	    $getMode     = 'xlsx';
	    break;
    default:
        break;
}

// Array for valid columns visible for current user.
// Needed for PDF export to set the correct colspan for the layout
// Maybe there are hidden fields.
$arrValidColumns = array();

$csvStr         = '';                   // CSV file as string
$header         = array();              //'xlsx'
$rows           = array();              //'xlsx'
$strikethroughs = array();              //'xlsx'

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->showFormerKeys($getShowAll);
$keys->readKeys($gCurrentOrgId);

$user = new User($gDb, $gProfileFields);

// define title (html) and headline
$title = $gL10n->get('PLG_KEYMANAGER_KEYMANAGER');
$headline = $gL10n->get('PLG_KEYMANAGER_KEYMANAGER');

// if html mode and last url was not a list view then save this url to navigation stack
if ($gNavigation->count() === 0 || ($getMode == 'html' && strpos($gNavigation->getUrl(), 'keymanager.php') === false))             
{
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-key');
}

if ($getMode != 'csv' && $getMode != 'xlsx' )
{
    $datatable = false;
    $hoverRows = false;

    if ($getMode == 'print')
    {
        // create html page object without the custom theme files
        $page = new HtmlPage('plg-keymanager-main-print');
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $table = new HtmlTable('adm_keys_table', $page, $hoverRows, $datatable, $classTable);
    }
    elseif ($getMode == 'pdf')
    {
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($headline);

        // remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->setHeaderMargin(10);
        $pdf->setFooterMargin(0);

        // headline for PDF
        $pdf->setHeaderData('', 0, $headline, '');

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_keys_table', null, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
    }
    elseif ($getMode == 'html')
    {
         if ($getExportAndFilter)
        {
            $datatable = false;
        }
        else
        {
            $datatable = true;
        }
        $hoverRows = true;

        $inputFilterStringLabel = '<i class="fas fa-search" alt="'.$gL10n->get('PLG_KEYMANAGER_GENERAL').'" title="'.$gL10n->get('PLG_KEYMANAGER_GENERAL').'"></i>';
        $selectBoxKeyNameLabel ='<i class="fas fa-key" alt="'.$gL10n->get('PLG_KEYMANAGER_KEYNAME').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEYNAME').'"></i>';
        $selectBoxReceiverLabel = '<i class="fas fa-user" alt="'.$gL10n->get('PLG_KEYMANAGER_RECEIVER').'" title="'.$gL10n->get('PLG_KEYMANAGER_RECEIVER').'"></i>';
        
        // create html page object
        $page = new HtmlPage('plg-keymanager-main-html');
        $page->setTitle($title);
        $page->setHeadline($headline);

        $page->addJavascript('
            $("#filter_keyname").change(function () {
              
                    self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                        'mode'              => 'html',
                        'filter_string'     => $getFilterString,
                        'filter_receiver'   => $getFilterReceiver,
                        'export_and_filter' => $getExportAndFilter,
                        'same_side'         => true,
                        'show_all'          => $getShowAll
                    )) . '&filter_keyname=" + $(this).val();
                
            });
            $("#filter_receiver").change(function () {
           
                    self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                        'mode'              => 'html',
                        'filter_string'     => $getFilterString,
                        'filter_keyname'    => $getFilterKeyName,
                        'export_and_filter' => $getExportAndFilter,
                        'same_side'         => true,
                        'show_all'          => $getShowAll
                    )) . '&filter_receiver=" + $(this).val();
       
            });
            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString, 
                    'filter_keyname'    => $getFilterKeyName, 
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,                   
                    'show_all'          => $getShowAll,  
                    'mode'              => 'print'
                )) . '", "_blank");
            });
            $("#export_and_filter").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });
            $("#show_all").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });

            $("#filter_string").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });
            ',
            true
        );

        if ($getExportAndFilter)
        {
            // links to print and exports
            $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
        
            $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT_TO'), '#', 'fa-file-download');
            $page->addPageFunctionsMenuItem('menu_item_lists_xlsx', $gL10n->get('SYS_MICROSOFT_EXCEL').' (XLSX)',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_keyname'    => $getFilterKeyName,
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'xlsx')),
                'fa-file-excel', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv_ms', $gL10n->get('SYS_MICROSOFT_EXCEL').' (CSV)',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_keyname'    => $getFilterKeyName,
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'csv-ms')),
                'fa-file-excel', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdf', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_keyname'    => $getFilterKeyName,
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'pdf')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdfl', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_keyname'    => $getFilterKeyName,
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'pdfl')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv', $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_keyname'    => $getFilterKeyName,
                    'filter_receiver'   => $getFilterReceiver,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'csv-oo')),
                'fa-file-csv', 'menu_item_lists_export');
        }
        
        if (isUserAuthorizedForPreferences())
		{
    		$page->addPageFunctionsMenuItem('menu_preferences', $gL10n->get('SYS_SETTINGS'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php'),  'fa-cog');
		} 
        
		$form = new HtmlForm('navbar_birthdaylist_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', array('headline' => $headline)), $page, array('type' => 'navbar', 'setFocus' => false));
        
		if ($getExportAndFilter)
		{  
            $form->addInput('filter_string', $inputFilterStringLabel, $getFilterString);
        
            // read all keynames
            $sql = 'SELECT DISTINCT kmd_value, kmd_value
                               FROM '.TBL_KEYMANAGER_DATA.'
                         INNER JOIN '.TBL_KEYMANAGER_FIELDS.'
                                 ON kmf_id = kmd_kmf_id
                              WHERE (  kmf_org_id  = '. $gCurrentOrgId .'
                                 OR kmf_org_id IS NULL ) 
                                AND kmf_name_intern = \'KEYNAME\' 
                           ORDER BY kmd_value ASC';
            $form->addSelectBoxFromSql('filter_keyname', $selectBoxKeyNameLabel, $gDb, $sql, array('defaultValue' => $getFilterKeyName , 'showContextDependentFirstEntry' => true));
        
            // read all receiver
            $sql = 'SELECT DISTINCT kmd_value, CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value)
                               FROM '.TBL_KEYMANAGER_DATA.'
                         INNER JOIN '.TBL_KEYMANAGER_FIELDS.'
                                 ON kmf_id = kmd_kmf_id
                          LEFT JOIN '. TBL_USER_DATA. ' as last_name
                                 ON last_name.usd_usr_id = kmd_value
                                AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                          LEFT JOIN '. TBL_USER_DATA. ' as first_name
                                 ON first_name.usd_usr_id = kmd_value
                                AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                              WHERE ( kmf_org_id  = '. $gCurrentOrgId .'
                                 OR kmf_org_id IS NULL )
                                AND kmf_name_intern = \'RECEIVER\'
                           ORDER BY CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value) ASC';
            $form->addSelectBoxFromSql('filter_receiver',$selectBoxReceiverLabel, $gDb, $sql, array('defaultValue' => $getFilterReceiver , 'showContextDependentFirstEntry' => true));
        }
        else
        {
            $form->addInput('filter_string', '', $getFilterString, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('filter_keyname', '', $getFilterKeyName, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('filter_receiver', '', $getFilterReceiver, array('property' => HtmlForm::FIELD_HIDDEN));          
        }
 
        $form->addCheckbox('show_all', $gL10n->get('PLG_KEYMANAGER_SHOW_ALL_KEYS'), $getShowAll);                           
        $form->addCheckbox('export_and_filter', $gL10n->get('PLG_KEYMANAGER_EXPORT_AND_FILTER'), $getExportAndFilter);
        $form->addInput('same_side', '', '1', array('property' => HtmlForm::FIELD_HIDDEN));
        
        $page->addHtml($form->show());        

        $table = new HtmlTable('adm_keys_table', $page, $hoverRows, $datatable, $classTable);
        if ($datatable)
        {
            // ab Admidio 4.3 verursacht setDatatablesRowsPerPage, wenn $datatable "false" ist, folgenden Fehler:
            // "Fatal error: Uncaught Error: Call to a member function setDatatablesRowsPerPage() on null"
            $table->setDatatablesRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
        }
    }
    else
    {
        $table = new HtmlTable('adm_keys_table', $page, $hoverRows, $datatable, $classTable);
    }
}

// initialize array parameters for table and set the first column for the counter
if ($getMode == 'html')
{
    $columnAlign  = array('left');
    $columnValues = array($gL10n->get('SYS_ABR_NO'));
}
else
{
    $columnAlign  = array('center');
    $columnValues = array($gL10n->get('SYS_ABR_NO'));
}

// headlines for columns
$columnNumber = 1;

foreach ($keys->mKeyFields as $keyField)
{
    $kmfNameIntern = $keyField->getValue('kmf_name_intern');
  
    $columnHeader = convlanguagePKM($keys->getProperty($kmfNameIntern, 'kmf_name'));

    if ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'CHECKBOX'
        ||  $keys->getProperty($kmfNameIntern, 'kmf_type') == 'RADIO_BUTTON'
        ||  $keys->getProperty($kmfNameIntern, 'kmf_type') == 'GENDER')
    {
        $columnAlign[] = 'center';
    }
    elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'NUMBER'
       ||   $keys->getProperty($kmfNameIntern, 'kmf_type') == 'DECIMAL')
    {
        $columnAlign[] = 'right';
    }
    else
    {
        $columnAlign[] = 'left';
    }

    if ($getMode == 'csv' && $columnNumber === 1)
    {
        // add serial
        $csvStr .= $valueQuotes.$gL10n->get('SYS_ABR_NO').$valueQuotes;
    }

    if ($getMode == 'pdf' && $columnNumber === 1)
    {
        // add serial
        $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
    }
    
    if ($getMode == 'xlsx' && $columnNumber === 1)
    {
        // add serial
        $header[$gL10n->get('SYS_ABR_NO')] = 'string';
    }
    
    if ($getMode == 'csv')
    {
        $csvStr .= $separator.$valueQuotes.$columnHeader.$valueQuotes;
    }
    elseif ($getMode == 'xlsx')
    {
        $header[$columnHeader] = 'string';
    }
    elseif ($getMode == 'pdf')
    {
        $arrValidColumns[] = $columnHeader;
    }
    elseif ($getMode == 'html' || $getMode == 'print')
    {
        $columnValues[] = $columnHeader;
    }

 	$columnNumber++;
}  // End-For

if ($getMode == 'html')    //change/delete/print button only in html-view
{
	$columnAlign[]  = 'center';
	$columnValues[] = '&nbsp;';

	if ($datatable)
	{
	    $table->disableDatatablesColumnsSort(array(count($columnValues)));
	}
}

if ($getMode == 'csv')
{
    $csvStr .= "\n";
}
elseif ($getMode == 'html' || $getMode == 'print')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
}
elseif ($getMode == 'pdf')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addTableHeader();
    $table->addRow();
    $table->addAttribute('align', 'center');
    $table->addColumn($headline, array('colspan' => count($arrValidColumns)));
    $table->addRow();

    // Write valid column headings
    for ($column = 0, $max = count($arrValidColumns); $column < $max; ++$column)
    {
        $table->addColumn($arrValidColumns[$column], array('style' => 'text-align: '.$columnAlign[$column].';font-size:14;background-color:#C7C7C7;'), 'th');
    }
}
elseif ($getMode == 'xlsx')
{
    // nothing to do
}
else
{
    $table->addTableBody();
}

$listRowNumber = 1;

foreach ($keys->keys as $key)
{
	$tmp_csv = '';
	
	$keys->readKeyData($key['kmk_id'], $gCurrentOrgId);

    $columnValues = array();
    $content = '';
    $htmlValue = '';
    $kmfNameIntern = '';
    $columnNumber = 1;
    $strikethrough = false;
    if ($key['kmk_former'])
    {
        $strikethrough = true;
    }

    foreach($keys->mKeyFields as $keyField)
    {
        $kmfNameIntern = $keyField->getValue('kmf_name_intern');
        
        if ($getExportAndFilter 
            && (($getFilterKeyName <> '' && $kmfNameIntern == 'KEYNAME' && $getFilterKeyName !=  $keys->getValue($kmfNameIntern, 'database'))
        	   || ($getFilterReceiver <> 0 && $kmfNameIntern == 'RECEIVER' && $getFilterReceiver !=  $keys->getValue($kmfNameIntern))))
        {
        	continue 2;
        }

        if ($columnNumber === 1)
        {
            if (in_array($getMode, array('html', 'print', 'pdf', 'xlsx'), true))
            {
                // add serial
                $columnValues[] = $listRowNumber;
            }
            else
            {
                // 1st column may show the serial
            	$tmp_csv .= $valueQuotes.$listRowNumber.$valueQuotes;
            }
        }
        
   		$content = $keys->getValue($kmfNameIntern, 'database');
   			
		if ($kmfNameIntern == 'RECEIVER' && strlen($content) > 0)
        {
        	$user->readDataById($content);
          	if ($getMode == 'html')
          	{
          		$content = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';	
          	}
          	else
          	{
          		$content = $user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME');
          	}
        }
          
		if ($kmfNameIntern == 'KEYNAME' && $getMode == 'html')
        {
          	$content = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php', array('key_id' => $key['kmk_id'])).'">'.$content.'</a>';
        }
          
        if ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'CHECKBOX')
        {
        	if ($content != 1)
            {
            	$content = 0;
            }
            	
            if ($getMode == 'csv' || $getMode == 'pdf' || $getMode == 'xlsx')
            {
            	if ($content == 1)
                {
                    $content = $gL10n->get('SYS_YES');
                }
                else
                {
                    $content = $gL10n->get('SYS_NO');
                }
            }
            else 
            {
            	$content = $keys->getHtmlValue($kmfNameIntern, $content);
            }
        }
        elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'DATE')
        {
            $content = $keys->getHtmlValue($kmfNameIntern, $content);
        }
        elseif ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'DROPDOWN'
             || $keys->getProperty($kmfNameIntern, 'kmf_type') == 'RADIO_BUTTON')
        {
        	if ($getMode == 'csv')
            {
            	$arrListValues = $keys->getProperty($kmfNameIntern, 'kmf_value_list', 'text');
            	$content = $arrListValues[$content];
            }
            else
            {
            	$content = $keys->getHtmlValue($kmfNameIntern, $content);
            }
        }

        // format value for csv export
        if ($getMode == 'csv')
        {
        	$tmp_csv .= $separator.$valueQuotes.$content.$valueQuotes;
        }
        // create output in html layout
        else
        {
        	if (!$key['kmk_former'] || $getMode == 'xlsx')
            {
            	$columnValues[] = $content;
            }
            else 
            {
            	$columnValues[] = '<s>'.$content.'</s>';
            }
        }
   		$columnNumber++;
    }
    
    if ($getMode == 'html')    //Delete/Print button only in html view
    {
    	$tempValue = '';
    	
    	if ($pPreferences->isPffInst())
    	{
    		$tempValue .= '<a class="admidio-icon-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_export_to_pff.php', array('key_id' => $key['kmk_id'])). '">
    	                       <i class="fas fa-print" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_PRINT').'"></i>
    	                   </a>';
    	}
    	if (isUserAuthorizedForPreferences())
    	{
    		$tempValue .= '<a class="admidio-icon-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_delete.php', array('key_id' => $key['kmk_id'], 'key_former' => $key['kmk_former'])). '">
    	                       <i class="fas fa-minus-circle" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_DELETE').'"></i>
    	                   </a>';
    	}
    	
    	$columnValues[] = $tempValue;
    }
    
    $showRow = false;                                               // Marker, ob die aktuelle Zeile angezeigt werden soll
    if ($getFilterString == '')                                     // wenn Filterstring leer ist, dann Zeile immer anzeigen oder drucken
    {
        $showRow = true;
    }
    
    if ($getExportAndFilter && $getFilterString <> '')              // weitere Prüfungen, wenn Filter aktiviert und Filterstring nicht leer ist
    {
        $showRowException = false;
        
        $filterArray = explode(',', $getFilterString );
        foreach ($filterArray as $filterString)
        {
            $filterString = trim($filterString);
            if (substr($filterString, 0, 1) == '-')                 // ein Ausnahmebegriff wurde angegeben (Filterstring beginnt mit '-')
            {
                $filterString = substr($filterString, 1);
                if (stristr(implode('',$columnValues), $filterString ) || stristr($tmp_csv, $filterString))
                {
                    $showRowException = true;                       // unabhängig von den weiteren Prüfungen: diese Zeile nicht anzeigen
                }
            }
            if (stristr(implode('',$columnValues), $filterString ) || stristr($tmp_csv, $filterString))
            {
                $showRow = true;
            }
        }
        if ($showRowException)
        {
            $showRow = false;
        }
    }

    if ($showRow)
    {
    	if ($getMode == 'csv')
    	{
        	$csvStr .= $tmp_csv. "\n";
    	}
        elseif($getMode == 'xlsx')
    	{
        	$rows[] = $columnValues;
            $strikethroughs[] = $strikethrough;
    	}
    	else
    	{
        	$table->addRowByArray($columnValues, '', array('nobr' => 'true'));
    	}
    }
       
    ++$listRowNumber;
}  // End-While (end found key)

// Settings for export file
if ($getMode == 'csv' || $getMode == 'pdf' || $getMode == 'xlsx')
{
    // file name in the current directory...
    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;
    
    // for IE the filename must have special chars in hexadecimal
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)               //toDo notwendig???????
    {
        $filename = urlencode($filename);
    }

    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // necessary for IE6 to 8, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}

if ($getMode == 'csv')
{
    // download CSV file
    header('Content-Type: text/comma-separated-values; charset='.$charset);

    if ($charset == 'iso-8859-1')
    {
        echo utf8_decode($csvStr);
    }
    else
    {
        echo $csvStr;
    }
}
// send the new PDF to the User
elseif ($getMode == 'pdf')
{
   // output the HTML content
    $pdf->writeHTML($table->getHtmlTable(), true, false, true);

    $file = ADMIDIO_PATH . FOLDER_DATA . '/' . $filename;

    // Save PDF to file
    $pdf->Output($file, 'F');

    // Redirect
    header('Content-Type: application/pdf');

    readfile($file);
    ignore_user_abort(true);

    try
    {
        FileSystemUtils::deleteFileIfExists($file);
    }
    catch (\RuntimeException $exception)
    {
        $gLogger->error('Could not delete file!', array('filePath' => $file));
    }    
    
}
elseif ($getMode == 'xlsx')
{
    header('Content-disposition: attachment; filename="'.XLSXWriter::sanitize_filename($filename).'"');
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    $writer = new XLSXWriter();
    $writer->setAuthor($gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'));
    $writer->setTitle($filename);
    $writer->setSubject($gL10n->get('PLG_KEYMANAGER_KEYLIST'));
    $writer->setCompany($gCurrentOrganization->getValue('org_longname'));
    $writer->setKeywords(array($gL10n->get('PLG_KEYMANAGER_NAME_OF_PLUGIN'), $gL10n->get('PLG_KEYMANAGER_KEY')));
    $writer->setDescription($gL10n->get('PLG_KEYMANAGER_CREATED_WITH'));
    
    $writer->writeSheetHeader('Sheet1', $header );
    for ($i = 0; $i < count($rows); $i++)
    {
        if ($strikethroughs[$i])
        {
            $writer->writeSheetRow('Sheet1', $rows[$i] , array('font-style' => 'strikethrough'));
        }
        else
        {
            $writer->writeSheetRow('Sheet1', $rows[$i]);
        }
        
    }
    $writer->writeToStdOut();
}
elseif ($getMode == 'html' && $getExportAndFilter)
{ 
    $page->addHtml('<div style="width:100%; height: 500px; overflow:auto; border:20px;">');
    $page->addHtml($table->show(false));
    $page->addHtml('</div><br/>');
   
    $page->show();
}
elseif (($getMode == 'html' && !$getExportAndFilter) || $getMode == 'print')
{
    $page->addHtml($table->show(false));
    
    $page->show();
}
