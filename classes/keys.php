<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2020 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Keys
 * @brief Reads the keys and the key fields structure out of database and give access to it
 *
 */
class Keys
{
	public $mKeyFields = array();   ///< Array with all key fields objects
	public $mKeyData   = array();   ///< Array with all key data objects
	public $keys       = array();   ///< Array with all key objects

    protected $mKeyId;                  ///< KeyId of the current key of this object
    protected $mDb;                     ///< An object of the class Database for communication with the database
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    protected $newKey;                  ///< Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    protected $showFormerKeys;          ///< if true, than former keys will be showed
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    protected $keyFieldsSort = array();
   
    /**
     * constructor that will initialize variables and read the key field structure
     * @param \Database $database       Database object (should be @b $gDb)
     * @param int       $organizationId The id of the organization for which the key field structure should be read
     */
    public function __construct(&$database, $organizationId)
    {
        $this->mDb =& $database;
        $this->readKeyFields($organizationId);
        $this->mKeyId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
        $this->newKey = false;
        $this->showFormerKeys = true;
    }

    /**
     * Set the database object for communication with the database of this class.
     * @param \Database $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        $this->mDb =& $database;
    }

    /**
     * Called on serialization of this object. The database object could not
     * be serialized and should be ignored.
     * @return string[] Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('mDb'));
    }
    
    
    /**
     * key data of all key fields will be initialized
     * the fields array will not be renewed
     */
    public function clearKeyData()
    {
    	$this->mKeyData = array();
    	$this->mKeyId = 0;
    	$this->columnsValueChanged = false;
    }
    

    /**
     * returns for a fieldname intern (kmf_name_intern) the value of the column from table adm_keymanager_fields
     * @param string $fieldNameIntern Expects the @b kmf_name_intern of table @b adm_keymanager_fields
     * @param string $column          The column name of @b adm_keymanager_fields for which you want the value
     * @param string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
    	global $gL10n;
    	$value = '';
        if (array_key_exists($fieldNameIntern, $this->mKeyFields))
        {
        	$value = $this->mKeyFields[$fieldNameIntern]->getValue($column, $format);
        	
        		if ($column == 'kmf_value_list')
        	 	{
        	 		if ($this->mKeyFields[$fieldNameIntern]->getValue('kmf_type') === 'DROPDOWN' || $this->mKeyFields[$fieldNameIntern]->getValue('kmf_type') === 'RADIO_BUTTON')
        	 		{
        	 			$value = $this->getListValue($fieldNameIntern, $value, $format);
        	 		}
        	 }
        }
        else 
        {
        	// if id-field not exists then return zero
        	if (strpos($column, '_id') > 0)
        	{
        		$value = 0;
        	}
        }
        return $value;
    }

    /**
     * returns for a fieldname intern (kmf_name_intern) the value of the column from table adm_keymanager_fields
     * @param string $fieldNameIntern Expects the @b kmf_name_intern of table @b adm_keymanager_fields
     * @param string $column          The column name of @b adm_keymanager_fields for which you want the value
     * @param string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    protected function getListValue($fieldNameIntern, $value , $format)
    {
    	global $gL10n;

    	$arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value
    				 
    	// first replace windows new line with unix new line and then create an array
    	$valueFormated = str_replace("\r\n", "\n", $value);
    	$arrListValues = explode("\n", $valueFormated);
    				 
    	foreach ($arrListValues as $key => &$listValue)
    	{
    		if ($this->mKeyFields[$fieldNameIntern]->getValue('kmf_type') === 'RADIO_BUTTON')
    		{
    			// if value is imagefile or imageurl then show image
    			if (strpos(admStrToLower($listValue), '.png') > 0 || strpos(admStrToLower($listValue), '.jpg') > 0)
    			{
                    // if value is imagefile or imageurl then show image
                    if (Image::isFontAwesomeIcon($listValue)
                    || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false)) // TODO: simplify check for images
                    {
                        // if there is imagefile and text separated by | then explode them
                        if (StringUtils::strContains($listValue, '|'))
                        {
                            list($listValueImage, $listValueText) = explode('|', $listValue);
                        }
                        else
                        {
                            $listValueImage = $listValue;
                            $listValueText  = $this->getValue('usf_name');
                        }

                        // if text is a translation-id then translate it
                        $listValueText = Language::translateIfTranslationStrId($listValueText);

                        if ($format === 'text')
                        {
                            // if no image is wanted then return the text part or only the position of the entry
                            if (StringUtils::strContains($listValue, '|'))
                            {
                                $listValue = $listValueText;
                            }
                            else
                            {
                                $listValue = $key + 1;
                            }
                        }
                        else
                        {
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }
                    }
                }    
    		}
    					 
            // if text is a translation-id then translate it
            $listValue = Language::translateIfTranslationStrId($listValue);

            // save values in new array that starts with key = 1
            $arrListValuesWithKeys[++$key] = $listValue;
    	}
    	unset($listValue);
    	return $arrListValuesWithKeys;
   
    }
    
    /**
     * returns for field id (kmf_id) the value of the column from table adm_keymanager_fields
     * @param int    $fieldId Expects the @b kmf_id of table @b adm_keymanager_fields
     * @param string $column  The column name of @b adm_keymanager_fields for which you want the value
     * @param string $format  Optional the format (is necessary for timestamps)
     * @return string
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach ($this->mKeyFields as $field)
        {
            if ((int) $field->getValue('kmf_id') === (int) $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string     $fieldNameIntern Internal key field name of the field that should be html formated
     * @param string|int $value           The value that should be formated must be commited so that layout
     *                                    is also possible for values that aren't stored in database
     * @param int        $value2          An optional parameter that is necessary for some special fields like email to commit the user id
     * @return string Returns an html formated string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = null)
    {
        global $gSettingsManager;

        if (!array_key_exists($fieldNameIntern, $this->mKeyFields))
        {
            return $value;
        }

        // if value is empty or null, then do nothing
        if ($value != '')
        {
            // create html for each field type
            $htmlValue = $value;

            $kmfType = $this->mKeyFields[$fieldNameIntern]->getValue('kmf_type');
            switch ($kmfType)
            {
                case 'CHECKBOX':
                    if ($value == 1)
                    {
                        $htmlValue = '<i class="fas fa-check-square"></i>';
                    }
                    else
                    {
                        $htmlValue = '<i class="fas fa-square"></i>';
                    }
                    break;
                case 'DATE':
                    if ($value !== '')
                    {
                        // date must be formated
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof \DateTime)
                        {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                    // first replace windows new line with unix new line and then create an array
                    $valueFormated = str_replace("\r\n", "\n", $this->mKeyFields[$fieldNameIntern]->getValue('kmf_value_list', 'database'));
                    $arrListValues = explode("\n", $valueFormated);

                    foreach ($arrListValues as $index => $listValue)
                    {
                        // if value is imagefile or imageurl then show image
                        if ($usfType === 'RADIO_BUTTON' && (Image::isFontAwesomeIcon($listValue)
                        || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false))) // TODO: simplify check for images
                        {
                            // if there is imagefile and text separated by | then explode them
                            if (StringUtils::strContains($listValue, '|'))
                            {
                                list($listValueImage, $listValueText) = explode('|', $listValue);
                            }
                            else
                            {
                                $listValueImage = $listValue;
                                $listValueText  = $this->getValue('kmf_name');
                            }

                            // if text is a translation-id then translate it
                            $listValueText = Language::translateIfTranslationStrId($listValueText);

                            // get html snippet with image tag
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }

                        // if text is a translation-id then translate it
                        $listValue = Language::translateIfTranslationStrId($listValue);
                        
                        // save values in new array that starts with key = 1
                        $arrListValuesWithKeys[++$index] = $listValue;
                    }

                    $htmlValue = $arrListValuesWithKeys[$value];
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
            }

            $value = $htmlValue;
        }
        // special case for type CHECKBOX and no value is there, then show unchecked checkbox
        else
        {
            if ($this->mKeyFields[$fieldNameIntern]->getValue('kmf_type') === 'CHECKBOX')
            {
                $value = '<i class="fas fa-square"></i>';
            }
        }

        return $value;
    }

    /**
     * Returns the key value for this column @n
     * format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     * format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     * format = 'database' : returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern Expects the @b kmf_name_intern of table @b adm_keymanager_fields
     * @param string $format          Returns the field value in a special format @b text, @b html, @b database
     *                                or datetime (detailed description in method description)
     * @return string|int|bool Returns the value for the column.
     */
    public function getValue($fieldNameIntern, $format = '')
    {
        global $gL10n, $gPreferences;

        $value = '';

        // exists a key field with that name ?
        // then check if key has a data object for this field and then read value of this object
        if (array_key_exists($fieldNameIntern, $this->mKeyFields)
        &&  array_key_exists($this->mKeyFields[$fieldNameIntern]->getValue('kmf_id'), $this->mKeyData))
        {
            $value = $this->mKeyData[$this->mKeyFields[$fieldNameIntern]->getValue('kmf_id')]->getValue('kmd_value', $format);

            if ($format === 'database')
            {
                return $value;
            }

            switch ($this->mKeyFields[$fieldNameIntern]->getValue('kmf_type'))
            {
                case 'DATE':
                    if ($value !== '')
                    {
                        // if date field then the current date format must be used
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date === false)
                        {
                            return $value;
                        }

                        // if no format or html is set then show date format from Admidio settings
                        if ($format === '' || $format === 'html')
                        {
                            $value = $date->format($gPreferences['system_date']);
                        }
                        else
                        {
                            $value = $date->format($format);
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    // the value in db is only the position, now search for the text
                    if ($value > 0 && $format !== 'html')
                    {                          
                      	$valueList = $this->mKeyFields[$fieldNameIntern]->getValue('kmf_value_list', $format);
                        $arrListValues = $this->getListValue($fieldNameIntern, $valueList, $format);
                    }
                    break;
            }
        }

        // get html output for that field type and value
        if ($format === 'html')
        {
            $value = $this->getHtmlValue($fieldNameIntern, $value);
        }

        return $value;
    }

    /**
     * This method reads or stores the variable for showing former keys.
     * The values will be stored in database without any inspections !
     *  @param  bool $newValue    If set, than the new value will be stored in @b showFormerKeys.
     *  @return bool Returns the current value of @b showFormerKeys
     */
    public function showFormerKeys($newValue)
    {
    	if ($newValue === null)
    	{
    		$valid =  $this->showFormerKeys;
    	}
    	else
    	{
    		$this->showFormerKeys = $newValue;
    		$valid = $newValue;
    	}
    	return $valid;
    }
    
    
    /**
     * If this method is called than all further calls of method @b setValue will not check the values.
     * The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

    
    /**
     * If the recordset is new and wasn't read from database or was not stored in database
     * then this method will return true otherwise false
     * @return bool Returns @b true if record is not stored in database
     */
    public function isNewKey()
    {
    	return $this->newKey;
    }
    

    /**
     * Reads the key data of all key fields out of database table @b adm_keymanager_data
     * and adds an object for each field data to the @b mKeyData array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $keyId         The id of the key for which the key data should be read.
     * @param int $organizationId The id of the organization for which the key fields
     *                            structure should be read if necessary.
     */
    public function readKeyData($keyId, $organizationId)
    {    	
    	if (count($this->mKeyFields) === 0)
    	{
    		$this->readKeyFields($organizationId);
    	}
    
    	$this->mKeyData = array();
    	
    	if ($keyId > 0)
    	{
    		// remember the key
    		$this->mKeyId = $keyId;
    
    		// read all key data
    		$sql = 'SELECT *
                      FROM '.TBL_KEYMANAGER_DATA.'
                INNER JOIN '.TBL_KEYMANAGER_FIELDS.'
                        ON kmf_id = kmd_kmf_id
                     WHERE kmd_kmk_id = '.$keyId.' ';
    		$keyDataStatement = $this->mDb->query($sql);
    
    		while ($row = $keyDataStatement->fetch())
    		{
    			if (!array_key_exists($row['kmd_kmf_id'], $this->mKeyData))
    			{
    				$this->mKeyData[$row['kmd_kmf_id']] = new TableAccess($this->mDb, TBL_KEYMANAGER_DATA, 'kmd');
    			}
    			$this->mKeyData[$row['kmd_kmf_id']]->setArray($row);
    		}
    	}
    	else 
    	{   	
    		$this->newKey = true;
    	}
    }


    /**
     * save data of every key field
     */
    public function saveKeyData()
    { 	
    	$this->mDb->startTransaction();
    
    	foreach ($this->mKeyData as $value)
    	{
    		if ($value->hasColumnsValueChanged())
    		{
    			$this->columnsValueChanged = true;
    		}
    		
    		// if value exists and new value is empty then delete entry
    		if ($value->getValue('kmd_id') > 0 && $value->getValue('kmd_value') === '')
    		{
    			$value->delete();
    		}
    		else
    		{
    			$value->save();
    		}
    	}
    
    	//for updateFingerPrint a change in db must be executet
    	// why !$this->newKey -> updateFingerPrint will be done in getNewKeyId
    	if (!$this->newKey && $this->columnsValueChanged)
    	{
    		$updateKey = new TableAccess($this->mDb, TBL_KEYMANAGER_KEYS, 'kmk', $this->mKeyId);
    		$updateKey->setValue('kmk_usr_id_change', NULL, false);
    		$updateKey->save();
    	}
    	
    	$this->columnsValueChanged = false;
    	
    	$this->mDb->endTransaction();
    }
    
    /**
     * Reads the key fields structure out of database table @b adm_keymanager_fields
     * and adds an object for each field structure to the @b mKeyFields array.
     * @param int $organizationId The id of the organization for which the key fields
     *                            structure should be read.
     */
    public function readKeyFields($organizationId)
    {
    	// first initialize existing data
    	$this->mKeyFields = array();
    	$this->clearKeyData();
    
    	$sql = 'SELECT *
                  FROM '.TBL_KEYMANAGER_FIELDS.'
                 WHERE kmf_org_id IS NULL
                    OR kmf_org_id = '.$organizationId.' ';
    	$statement = $this->mDb->query($sql);

    	while ($row = $statement->fetch())
    	{
    		if (!array_key_exists($row['kmf_name_intern'], $this->mKeyFields))
    		{
    			$this->mKeyFields[$row['kmf_name_intern']] = new TableAccess($this->mDb, TBL_KEYMANAGER_FIELDS, 'kmf');
    		}
     		$this->mKeyFields[$row['kmf_name_intern']]->setArray($row);
     		$this->keyFieldsSort[$row['kmf_name_intern']] = $row['kmf_sequence'];
    	}
    	
    	array_multisort($this->keyFieldsSort, SORT_ASC, $this->mKeyFields);
    }
    
    
    /**
     * Reads the keys out of database table @b adm_keymanager_keys
     * and stores the values to the @b keys array.
     * @param int $organizationId The id of the organization for which the keys should be read.
     */
    public function readKeys($organizationId)
    {   	 
    	// first initialize existing data
    	$this->keys = array();

    	$sqlWhereCondition = '';
    	if (!$this->showFormerKeys)
    	{
    		$sqlWhereCondition .= 'AND kmk_former = 0';
    	}
    	
    	$sql = 'SELECT DISTINCT kmk_id, kmk_former
          	      		   FROM '.TBL_KEYMANAGER_KEYS.'
          	         INNER JOIN '.TBL_KEYMANAGER_DATA.'
                             ON kmd_kmk_id = kmk_id
                          WHERE kmk_org_id IS NULL
                             OR kmk_org_id = '.$organizationId.'
                             '.$sqlWhereCondition.' ';
    	$statement = $this->mDb->query($sql);

    	while ($row = $statement->fetch())
    	{
    		$this->keys[] = array('kmk_id' => $row['kmk_id'], 'kmk_former' => $row['kmk_former']);
    	} 
    }
    
    
    /**
     * Set a new value for the key field of the table adm_keymanager_data.
     * If the user log is activated than the change of the value will be logged in @b adm_keymanager_log.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                           internal unique profile field name
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will
     *                           not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($fieldNameIntern, $newValue, $checkValue = true)
    {
    	global $gCurrentUser, $gPreferences;
    
    	$kmfId = $this->mKeyFields[$fieldNameIntern]->getValue('kmf_id');
    	
    	if (!array_key_exists($kmfId, $this->mKeyData) )
    	{
    		$oldFieldValue = '';
    	}
    	else 
    	{
    		$oldFieldValue = $this->mKeyData[$kmfId]->getValue('kmd_value');
    	}
    	
    	// key data from adm_keymanager_fields table
    	$newValue = (string) $newValue;
    
    	// format of date will be local but database has stored Y-m-d format must be changed for compare
    	if($this->mKeyFields[$fieldNameIntern]->getValue('kmf_type') === 'DATE')
    	{
    		$date = DateTime::createFromFormat($gPreferences['system_date'], $newValue);
    
    		if($date !== false)
    		{
    			$newValue = $date->format('Y-m-d');
    		}
    	}
    
    	// only to a update if value has changed
    	if (strcmp($oldFieldValue, $newValue) === 0) // https://secure.php.net/manual/en/function.strcmp.php#108563
    	{
    		return true;
    	}
    
    	$returnCode = false;
    
    	if (!array_key_exists($kmfId, $this->mKeyData) )
    	{
    		$this->mKeyData[$kmfId] = new TableAccess($this->mDb, TBL_KEYMANAGER_DATA, 'kmd');
    		$this->mKeyData[$kmfId]->setValue('kmd_kmf_id', $kmfId);
    		$this->mKeyData[$kmfId]->setValue('kmd_kmk_id', $this->mKeyId);
    	}
    	
    	$returnCode = $this->mKeyData[$kmfId]->setValue('kmd_value', $newValue);
    			
    	if ($returnCode && (int) $gPreferences['profile_log_edit_fields'] === 1)
    	{
    		$logEntry = new TableAccess($this->mDb, TBL_KEYMANAGER_LOG, 'kml');
    		$logEntry->setValue('kml_kmk_id', $this->mKeyId);
    		$logEntry->setValue('kml_kmf_id', $kmfId);
    		$logEntry->setValue('kml_value_old', $oldFieldValue);
    		$logEntry->setValue('kml_value_new', $newValue);
    		$logEntry->setValue('kml_comment', '');
    		$logEntry->save();
    	}
    
    	return $returnCode;
    }
    
    
    /**
     * Generates a new KeyId. The new value will be stored in @b mKeyId. 
     * @return int @b mKeyId
     */
    public function getNewKeyId()
    {
     	//If an error occured while generating a key, there is a KeyId but no data for that key.
    	//the following routine deletes these unused KeyIds
  		$sql = 'SELECT *
          	      FROM '.TBL_KEYMANAGER_KEYS.'
             LEFT JOIN '.TBL_KEYMANAGER_DATA.'
                    ON kmd_kmk_id = kmk_id
                 WHERE kmd_kmk_id is NULL ';
    	$statement = $this->mDb->query($sql);
   
    	while ($row = $statement->fetch())
    	{
    		$delKey = new TableAccess($this->mDb, TBL_KEYMANAGER_KEYS, 'kmk', $row['kmk_id']);
    		$delKey->delete();
    	}

    	//generate a new KeyId
    	if ($this->newKey)
    	{
    		$newKey = new TableAccess($this->mDb, TBL_KEYMANAGER_KEYS, 'kmk');
    		$newKey->setValue('kmk_org_id', ORG_ID);
    		$newKey->setValue('kmk_former', 0);
    		$newKey->save();
    	
    		$this->mKeyId = $newKey->getValue('kmk_id');
    		
    		// update key table
    		$this->readKeys(ORG_ID);
    		
    		return $this->mKeyId;
    	}
    }
}
