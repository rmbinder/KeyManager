<?php
/**
 ***********************************************************************************************
 * KeyManager
 *
 * Version 1.1.0
 *
 * KeyManager is an Admidio plugin for managing building and room keys.
 * 
 * Author: rmb
 *
 * Compatible with Admidio version 3.3
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
 
/******************************************************************************
 * Parameters:
 *
 * mode:            Output(html, print, csv-ms, csv-oo, pdf, pdfl)
 * full_screen:     false - (Default) show sidebar, head and page bottom of html page
 *                  true  - Only show the list without any other html unnecessary elements
 * filter_string    general filter string
 * filter_keyname   filter ony for keyname
 * show_all         show all keys (also made to the former)
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

// Einbinden der Sprachdatei
$gL10n->addLanguageFolderPath(ADMIDIO_PATH . FOLDER_PLUGINS . PLUGIN_FOLDER. '/languages');

$getMode           = admFuncVariableIsValid($_GET, 'mode',            'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl')));
$getFullScreen     = admFuncVariableIsValid($_GET, 'full_screen',     'bool');
$getFilterString   = admFuncVariableIsValid($_GET, 'filter_string',   'string', array('defaultValue' => ''));
$getFilterKeyName  = admFuncVariableIsValid($_GET, 'filter_keyname',  'string', array('defaultValue' => ''));
$getFilterReceiver = admFuncVariableIsValid($_GET, 'filter_receiver', 'int');
$getShowAll        = admFuncVariableIsValid($_GET, 'show_all',        'bool');

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
$filename    = $g_organization.'-'.$gL10n->get('PLG_KEYMANAGER_KEYMANAGER');

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
    default:
        break;
}

// Array for valid columns visible for current user.
// Needed for PDF export to set the correct colspan for the layout
// Maybe there are hidden fields.
$arrValidColumns = array();

$csvStr = ''; // CSV file as string

$keys = new Keys($gDb, $gCurrentOrganization->getValue('org_id'));
$keys->showFormerKeys($getShowAll);
$keys->readKeys($gCurrentOrganization->getValue('org_id'));

$user = new User($gDb, $gProfileFields);

// define title (html) and headline
$title = $gL10n->get('PLG_KEYMANAGER_KEYMANAGER');
$headline = $gL10n->get('PLG_KEYMANAGER_KEYMANAGER');

// if html mode and last url was not a list view then save this url to navigation stack
if ($getMode == 'html' && strpos($gNavigation->getUrl(), 'keymanager.php') === false)
{
    $gNavigation->addUrl(CURRENT_URL);
}

if ($getMode != 'csv')
{
    $datatable = false;
    $hoverRows = false;

    if ($getMode == 'print')
    {
        // create html page object without the custom theme files
        $page = new HtmlPage();
        $page->hideThemeHtml();
        $page->hideMenu();
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $table = new HtmlTable('adm_keys_table', $page, $hoverRows, $datatable, $classTable);
    }
    elseif ($getMode == 'pdf')
    {
        require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/tcpdf/tcpdf.php');
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
        $pdf->setHeaderData('', '', $headline, '');

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
        $datatable = true;
        $hoverRows = true;

        // create html page object
        $page = new HtmlPage();

        $inputFilterStringLabel = '<img class="admidio-icon-info" src="'. THEME_URL . '/icons/list.png"
            alt="'.$gL10n->get('PLG_KEYMANAGER_GENERAL').'" title="'.$gL10n->get('PLG_KEYMANAGER_GENERAL').'" />';
        $selectBoxKeyNameLabel = '<img class="admidio-icon-info" src="'. THEME_URL . '/icons/key.png"
            alt="'.$gL10n->get('PLG_KEYMANAGER_KEYNAME').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEYNAME').'" />';
        $selectBoxReceiverLabel = '<img class="admidio-icon-info" src="'. THEME_URL . '/icons/profile.png"
            alt="'.$gL10n->get('PLG_KEYMANAGER_RECEIVER').'" title="'.$gL10n->get('PLG_KEYMANAGER_RECEIVER').'" />';
        
        if ($getFullScreen)
        {
            $page->hideThemeHtml();
            $inputFilterStringLabel = $gL10n->get('PLG_KEYMANAGER_GENERAL');
            $selectBoxKeyNameLabel = $gL10n->get('PLG_KEYMANAGER_KEYNAME');
            $selectBoxReceiverLabel = $gL10n->get('PLG_KEYMANAGER_RECEIVER');
        }

        $page->setTitle($title);
        $page->setHeadline($headline);

        // create filter menu
        $filterNavbar = new HtmlNavbar('menu_list_filter', null, null, 'filter');
        $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php', $page, array('type' => 'navbar', 'setFocus' => false));
        $form->addInput('filter_string', $inputFilterStringLabel, $getFilterString);
        // read all keynames
        $sql = 'SELECT DISTINCT kmd_value, kmd_value
                           FROM '.TBL_KEYMANAGER_DATA.'
                     INNER JOIN '.TBL_KEYMANAGER_FIELDS.'
                             ON kmf_id = kmd_kmf_id
                          WHERE kmf_name_intern = \'KEYNAME\' 
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
                          WHERE kmf_name_intern = \'RECEIVER\'
                       ORDER BY CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value) ASC';
        $form->addSelectBoxFromSql('filter_receiver',$selectBoxReceiverLabel, $gDb, $sql, array('defaultValue' => $getFilterReceiver , 'showContextDependentFirstEntry' => true));
        $form->addInput('show_all', '', $getShowAll, array('property' => FIELD_HIDDEN));
        $form->addInput('full_screen', '', $getFullScreen, array('property' => FIELD_HIDDEN));      
        $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
        $filterNavbar->addForm($form->show(false));
        $page->addHtml($filterNavbar->show());
   
        $page->addHtml('<h5>'.$htmlSubHeadline.'</h5>');
        $page->addJavascript('
            $("#export_list_to").change(function() {
                if ($(this).val().length > 1) {
                    var result = $(this).val();
                    $(this).prop("selectedIndex", 0);
                    self.location.href = "'.ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?" +
                        "mode=" + result + "&filter_string='.$getFilterString.'&filter_keyname='.$getFilterKeyName.'&filter_receiver='.$getFilterReceiver.'&show_all='.$getShowAll.'";
                }
            });

            $("#menu_item_print_view").click(function() {
                window.open("'.ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?filter_string='.$getFilterString.'&filter_keyname='.$getFilterKeyName.'&filter_receiver='.$getFilterReceiver.'&show_all='.$getShowAll.'&mode=print", "_blank");
            });',
            true
        );

        // get module menu
        $listsMenu = $page->getMenu();
        
        $listsMenu->addItem('menu_item_back', $gHomepage, $gL10n->get('SYS_BACK'), 'back.png');

        if ($getFullScreen)
        {
            $listsMenu->addItem('menu_item_normal_picture', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?filter_string='.$getFilterString.'&amp;filter_keyname='.$getFilterKeyName.'&amp;filter_receiver='.$getFilterReceiver.'&amp;show_all='.$getShowAll.'&amp;mode=html&amp;full_screen=false',
                $gL10n->get('SYS_NORMAL_PICTURE'), 'arrow_in.png');
        }
        else
        {
            $listsMenu->addItem('menu_item_full_screen', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?filter_string='.$getFilterString.'&amp;filter_keyname='.$getFilterKeyName.'&amp;filter_receiver='.$getFilterReceiver.'&amp;show_all='.$getShowAll.'&amp;mode=html&amp;full_screen=true',
                $gL10n->get('SYS_FULL_SCREEN'), 'arrow_out.png');
        }

        if ($getShowAll == true)
        {
        	$listsMenu->addItem('show_all', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?filter_string='.$getFilterString.'&amp;filter_keyname='.$getFilterKeyName.'&amp;filter_receiver='.$getFilterReceiver.'&amp;mode=html&amp;full_screen='.$getFullScreen.'&amp;show_all=0',
        			$gL10n->get('PLG_KEYMANAGER_SHOW_ALL_KEYS'), 'checkbox_checked.gif');
        }
        else
        {
        	$listsMenu->addItem('show_all', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keymanager.php?filter_string='.$getFilterString.'&amp;filter_keyname='.$getFilterKeyName.'&amp;filter_receiver='.$getFilterReceiver.'&amp;mode=html&amp;full_screen='.$getFullScreen.'&amp;show_all=1',
        			$gL10n->get('PLG_KEYMANAGER_SHOW_ALL_KEYS'), 'checkbox.gif');
        }
        
        $listsMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'left');   //keys_edit_new.php?key_id='.$key['kmk_id'].
        
        // link to print overlay and exports
        $listsMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png', 'left', 'menu_item_extras');
        
        if (checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
        {
        	$listsMenu->addItem('menu_create_key', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php?key_id=0',
        			$gL10n->get('PLG_KEYMANAGER_KEY_CREATE'), 'add.png', 'left', 'menu_item_extras');
        	$listsMenu->addItem('menu_prefs', ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php',
        			$gL10n->get('PLG_KEYMANAGER_SETTINGS'), 'options.png', 'left', 'menu_item_extras');
        }

        $form = new HtmlForm('navbar_export_to_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        $selectBoxEntries = array(
            ''       => $gL10n->get('LST_EXPORT_TO').' ...',
            'csv-ms' => $gL10n->get('LST_MICROSOFT_EXCEL').' ('.$gL10n->get('SYS_ISO_8859_1').')',
            'pdf'    => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
            'pdfl'   => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
            'csv-oo' => $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')'
        );
        $form->addSelectBox('export_list_to', null, $selectBoxEntries, array('showContextDependentFirstEntry' => false));
        $listsMenu->addForm($form->show(false));

        $table = new HtmlTable('adm_keys_table', $page, $hoverRows, $datatable, $classTable);
        $table->setDatatablesRowsPerPage($gPreferences['lists_members_per_page']);
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
    
    if ($getMode == 'csv')
    {
        $csvStr .= $separator.$valueQuotes.$columnHeader.$valueQuotes;
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
	$table->disableDatatablesColumnsSort(array(count($columnValues)));
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
else
{
    $table->addTableBody();
}

$listRowNumber = 1;

foreach ($keys->keys as $key)
{
	$tmp_csv = '';
	
	$keys->readKeyData($key['kmk_id'], $gCurrentOrganization->getValue('org_id'));

    $columnValues = array();
    $content = '';
    $htmlValue = '';
    $kmfNameIntern = '';
    $columnNumber = 1;

    foreach($keys->mKeyFields as $keyField)
    {
        $kmfNameIntern = $keyField->getValue('kmf_name_intern');
        
        if (($getFilterKeyName <> '' && $kmfNameIntern == 'KEYNAME' && $getFilterKeyName !=  $keys->getValue($kmfNameIntern, 'database'))
        	|| ($getFilterReceiver <> 0 && $kmfNameIntern == 'RECEIVER' && $getFilterReceiver !=  $keys->getValue($kmfNameIntern)))
        {
        	continue 2;
        }

        if ($columnNumber === 1)
        {
            if (in_array($getMode, array('html', 'print', 'pdf'), true))
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
          		$content = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.$content.'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';	
          	}
          	else
          	{
          		$content = $user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME');
          	}
        }
          
		if ($kmfNameIntern == 'KEYNAME' && $getMode == 'html')
        {
          	$content = '<a href="'.ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_edit_new.php?key_id='.$key['kmk_id'].'">'.$content.'</a>';
        }
          
        if ($keys->getProperty($kmfNameIntern, 'kmf_type') == 'CHECKBOX')
        {
        	if ($content != 1)
            {
            	$content = 0;
            }
            	
            if ($getMode == 'csv' || $getMode == 'pdf')
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
        	if (!$key['kmk_former'])
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
    
    if ($getMode == 'html')    //Change/Delete/Print button only in html view
    {
    	$tempValue = '';
    	
    	$tempValue .='<a class="iconLink" href="'.ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_edit_new.php?key_id='.$key['kmk_id'].'">';
    	$tempValue .='<img src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('PLG_KEYMANAGER_KEY_EDIT').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_EDIT').'"/>';
    	$tempValue .='</a>&nbsp;&nbsp;';
    	if ($pPreferences->isPffInst())
    	{
    		$tempValue .= '<a class="iconLink" href="'.ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_export_to_pff.php?key_id='.$key['kmk_id'].'">';
    		$tempValue .='<img src="'.THEME_PATH.'/icons/print.png" alt="'.$gL10n->get('PLG_KEYMANAGER_KEY_PRINT').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_PRINT').'" /></a>';
    		$tempValue .='</a>&nbsp;&nbsp;';
    	}
    	if (checkShowPluginPKM($pPreferences->config['Pluginfreigabe']['freigabe_config']))
    	{
    		$tempValue .= '<a class="iconLink" href="'.ADMIDIO_URL . FOLDER_PLUGINS .'/'.PLUGIN_FOLDER.'/keys_delete.php?key_id='.$key['kmk_id'].'&key_former='.$key['kmk_former'].'">';
    		$tempValue .='<img src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('PLG_KEYMANAGER_KEY_DELETE').'" title="'.$gL10n->get('PLG_KEYMANAGER_KEY_DELETE').'" /></a>';
    	}
    	
    	$columnValues[] = $tempValue;
    }
    
    //pruefung auf filterstring
    if ($getFilterString == '' || ($getFilterString <> '' && (stristr(implode('',$columnValues), $getFilterString  ) || stristr($tmp_csv, $getFilterString))))
    {
    	if ($getMode == 'csv')
    	{
        	$csvStr .= $tmp_csv. "\n";
    	}
    	else
    	{
        	$table->addRowByArray($columnValues, null, array('nobr' => 'true'));
    	}
    }
       
    ++$listRowNumber;
}  // End-While (end found key)

// Settings for export file
if ($getMode == 'csv' || $getMode == 'pdf')
{
    // file name in the current directory...
    $filename .= '.'.$getMode;
    
    // for IE the filename must have special chars in hexadecimal
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
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
	$pdf->writeHTML($table->getHtmlTable(), true, false, true, false, '');
	
	//Save PDF to file
	$pdf->Output(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename, 'F');
	
	//Redirect
	header('Content-Type: application/pdf');
	
	readfile(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename);
	ignore_user_abort(true);
	unlink(ADMIDIO_PATH . FOLDER_DATA . '/'.$filename);
}
elseif ($getMode == 'html' || $getMode == 'print')
{
    // add table list to the page
    $page->addHtml($table->show());

    // show complete html page
    $page->show();
}
