<?php
/**
 ***********************************************************************************************
 * Class manages the configuration table
 *
 * @copyright 2004-2021 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Klasse verwaltet die Konfigurationstabelle "adm_plugin_preferences"
 *
 * Folgende Methoden stehen zur Verfuegung:
 *
 * isPffInst()							  - gibt true zurueck, wenn das Plugin FormFiller installiert ist, ansonsten false
 * pffDir()                               - gibt das Directory des Plugins FormFiller zurueck,
 * 											gibt false zurueck, wenn es nicht oder mehr als einmal existiert
 * init()						          -	prueft, ob die Konfigurationstabelle existiert,
 * 									        legt sie ggf. an und befuellt sie mit Default-Werten
 * save() 						          - schreibt die Konfiguration in die Datenbank
 * read()						          -	liest die Konfigurationsdaten aus der Datenbank
 * checkForUpdate()				          -	vergleicht die Angaben in der Datei version.php
 * 									        mit den Daten in der DB
 * deleteConfigData($deinst_org_select)   -	loescht Konfigurationsdaten in der Datenbank
 * deleteKeyData($deinst_org_select)      - Loescht Nutzerdaten in der Datenbank
 *
 *****************************************************************************/
     	
class ConfigTablePKM
{
	public $config = array();        ///< Array mit allen Konfigurationsdaten
	public $configpff = array();     ///< Array mit allen Konfigurationsdaten

	protected $table_name;
	protected static $shortcut = 'PKM';
	protected static $version;
	protected static $stand;
	protected static $dbtoken;
	protected static $dbtoken2;
	protected $isPffInst; 			// (is) (P)lugin (f)orm (f)iller (Inst)alled
	protected $pffDir;				// (p)lugin (f)orm (f)iller (Dir)ectory 
	
	public $config_default = array();	
	
    /**
     * ConfigTablePKM constructor
     */
	public function __construct()
	{
		global $g_tbl_praefix;

		require_once(__DIR__ . '/../version.php');
		include(__DIR__ . '/../configdata.php');
		
		$this->table_name = $g_tbl_praefix.'_plugin_preferences';

		if (isset($plugin_version))
		{
			self::$version = $plugin_version;
		}
		if (isset($plugin_stand))
		{
			self::$stand = $plugin_stand;
		}
		if (isset($dbtoken))
		{
			self::$dbtoken = $dbtoken;
		}

		self::$dbtoken2 = '#!#'; 

		$this->config_default = $config_default;
		$this->findPff();
		$this->checkPffInst();
	}
	
	/**
	 * Checks if the plugin FormFiller is installed
	 * @return void
	 */
	protected function checkPffInst()
	{
		$sql = 'SELECT COUNT(*) AS COUNT
            		       FROM '.$this->table_name.'
            		      WHERE plp_name = ?
            		        AND ( plp_org_id = ?
            	    	     OR plp_org_id IS NULL ) ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array('PFF__Plugininformationen__version', $GLOBALS['gCurrentOrgId']));
		
		if((int) $statement->fetchColumn() === 1  && $this->pffDir != false)
		{
			$this->isPffInst = true;
		}
		else 
		{
			$this->isPffInst = false;
		}
	}
	
	/**
	 * If the plugin FormFiller is installed
	 * then this method will return true otherwise false
	 * @return bool Returns @b true if plugin FormFiller is installed
	 */
	public function isPffInst()
	{
		return $this->isPffInst;
	}
	
	/**
	 * Checks if a FormFiller directory exists
	 * @return void
	 */
	protected function findPff()
	{
		$location = ADMIDIO_PATH . FOLDER_PLUGINS;
		$searchedFile = 'formfiller.php';
		$formFillerfiles = array();
		$tempFiles = array();
		
		$all = opendir($location);
		while ($found = readdir($all))
		{
			if (is_dir($location.'/'.$found) and $found<> ".." and $found<> ".")
			{
				$tempFiles= glob($location.'/'.$found.'/'. $searchedFile);
				if (count($tempFiles) > 0)
				{
					$formFillerfiles[] = $found;              // only directory is needed
				}
			}
		}
		closedir($all);
		unset($all);
		
		if (count($formFillerfiles) != 1)
		{
			$this->pffDir = false;
		}
		else
		{
			$this->pffDir = $formFillerfiles[0];
		}
	}
	
	/**
	 * Returns the Plugin FormFiller directory
	 * @return bool/string Returns the FormFiller directory otherwise false 
	 */
	public function pffDir()
	{
		return $this->pffDir;
	}
	
    /**
     * Prueft, ob die Konfigurationstabelle existiert, legt sie ggf an und befuellt sie mit Standardwerten
     * @return void
     */
	public function init()
	{
		global $gProfileFields;
	
		// pruefen, ob es die Tabelle bereits gibt
		$sql = 'SHOW TABLES LIKE \''.TBL_KEYMANAGER_FIELDS.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		// Tabelle anlegen, wenn es sie noch nicht gibt
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_KEYMANAGER_FIELDS.'
				(kmf_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			kmf_org_id int(10) unsigned NOT NULL,
	  			kmf_type varchar(30)  NOT NULL,
	  			kmf_name  varchar(100)   NOT NULL,
				kmf_name_intern  varchar(110)   NOT NULL,
				kmf_sequence int(10) unsigned NOT NULL,
				kmf_system boolean  NOT NULL DEFAULT \'0\',	
				kmf_mandatory boolean  NOT NULL DEFAULT \'0\',	
	  			kmf_description text,
				kmf_value_list text,
	  			kmf_usr_id_create int(10) unsigned DEFAULT NULL,
	  			kmf_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	  			kmf_usr_id_change int(10) unsigned DEFAULT NULL,
	  			kmf_timestamp_change timestamp NULL DEFAULT NULL,
	  			PRIMARY KEY (kmf_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			$GLOBALS['gDb']->query($sql);
		}
		
		$sql = 'SHOW TABLES LIKE \''.TBL_KEYMANAGER_DATA.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		// Tabelle anlegen, wenn es sie noch nicht gibt
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_KEYMANAGER_DATA.'
				(kmd_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			 kmd_kmf_id int(10) unsigned  NOT NULL,
				 kmd_kmk_id int(10) unsigned  NOT NULL,
	  			 kmd_value varchar(4000),
	  			 PRIMARY KEY (kmd_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			$GLOBALS['gDb']->query($sql);
		}
		
		$sql = 'SHOW TABLES LIKE \''.TBL_KEYMANAGER_KEYS.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		// Tabelle anlegen, wenn es sie noch nicht gibt
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_KEYMANAGER_KEYS.'
				(kmk_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			kmk_org_id int(10) unsigned NOT NULL,
				kmk_former boolean DEFAULT 0,	
				kmk_usr_id_create int(10) unsigned DEFAULT NULL,
	  			kmk_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	  			kmk_usr_id_change int(10) unsigned DEFAULT NULL,
	  			kmk_timestamp_change timestamp NULL DEFAULT NULL,
	  			PRIMARY KEY (kmk_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			$GLOBALS['gDb']->query($sql);
		}
		
		$sql = 'SHOW TABLES LIKE \''.TBL_KEYMANAGER_LOG.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		// Tabelle anlegen, wenn es sie noch nicht gibt
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_KEYMANAGER_LOG.'
				(kml_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				kml_kmk_id int(10) unsigned NOT NULL,	
				kml_kmf_id int(10) unsigned NOT NULL,	
				kml_value_old varchar(4000),	
				kml_value_new varchar(4000),	
				kml_usr_id_create int(10) unsigned DEFAULT NULL,
	  			kml_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,	
	  			kml_comment varchar(255) NULL,	
	  			PRIMARY KEY (kml_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			$GLOBALS['gDb']->query($sql);
		}
		
		$sql = 'SELECT *
            	  FROM '.TBL_KEYMANAGER_FIELDS.'
            	 WHERE kmf_name_intern = \'KEYNAME\'
            	   AND kmf_org_id = \''.$GLOBALS['gCurrentOrgId'].'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		if ($statement->rowCount() == 0)                 
		{
			$keyField = new TableAccess($GLOBALS['gDb'], TBL_KEYMANAGER_FIELDS, 'kmf');
			$keyField->setValue('kmf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$keyField->setValue('kmf_sequence', 1);
			$keyField->setValue('kmf_system', 1);
			$keyField->setValue('kmf_mandatory', 1);
			$keyField->setValue('kmf_name', 'PKM_KEYNAME');
			$keyField->setValue('kmf_name_intern', 'KEYNAME');
			$keyField->setValue('kmf_type', 'TEXT');
			$keyField->setValue('kmf_description', 'Der Name des Schlüssels (z.B. Haupteingang)');
			$keyField->save();
		
			$keyField = new TableAccess($GLOBALS['gDb'], TBL_KEYMANAGER_FIELDS, 'kmf');
			$keyField->setValue('kmf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$keyField->setValue('kmf_sequence', 2);
			$keyField->setValue('kmf_system', 1);
			$keyField->setValue('kmf_mandatory', 0);
			$keyField->setValue('kmf_name', 'PKM_RECEIVER');
			$keyField->setValue('kmf_name_intern', 'RECEIVER');
			$keyField->setValue('kmf_type', 'TEXT');
			$keyField->setValue('kmf_description', 'Der Empfänger des Schlüssels');
			$keyField->save();
		
			$keyField = new TableAccess($GLOBALS['gDb'], TBL_KEYMANAGER_FIELDS, 'kmf');
			$keyField->setValue('kmf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$keyField->setValue('kmf_sequence', 3);
			$keyField->setValue('kmf_system', 1);
			$keyField->setValue('kmf_mandatory', 0);
			$keyField->setValue('kmf_name', 'PKM_RECEIVED_ON');
			$keyField->setValue('kmf_name_intern', 'RECEIVED_ON');
			$keyField->setValue('kmf_type', 'DATE');
			$keyField->setValue('kmf_description', 'Das Empfangsdatum des Schlüssels');
			$keyField->save();
		}

		$config_ist = array();
		
		// pruefen, ob es die Tabelle bereits gibt
		$sql = 'SHOW TABLES LIKE \''.$this->table_name.'\' ';
   	 	$statement = $GLOBALS['gDb']->queryPrepared($sql);
    
    	// Tabelle anlegen, wenn es sie noch nicht gibt
    	if (!$statement->rowCount())
    	{
    		// Tabelle ist nicht vorhanden --> anlegen
        	$sql = 'CREATE TABLE '.$this->table_name.' (
            	plp_id 		integer     unsigned not null AUTO_INCREMENT,
            	plp_org_id 	integer   	unsigned not null,
    			plp_name 	varchar(255) not null,
            	plp_value  	text, 
            	primary key (plp_id) )
            	engine = InnoDB
         		auto_increment = 1
          		default character set = utf8
         		collate = utf8_unicode_ci';
    		$GLOBALS['gDb']->queryPrepared($sql);
    	} 
    
		$this->read();
		
		$this->config['Plugininformationen']['version'] = self::$version;
		$this->config['Plugininformationen']['stand'] = self::$stand;
	
		// die eingelesenen Konfigurationsdaten in ein Arbeitsarray kopieren
		$config_ist = $this->config;

		// die Default-config durchlaufen
		foreach ($this->config_default as $section => $sectiondata)
    	{
        	foreach ($sectiondata as $key => $value)
        	{
        		// gibt es diese Sektion bereits in der config?
        		if (isset($config_ist[$section][$key]))
        		{
        			// wenn ja, diese Sektion in der Ist-config loeschen
        			unset($config_ist[$section][$key]);
        		}
        		else
        		{
        			// wenn nicht, diese Sektion in der config anlegen und mit den Standardwerten aus der Soll-config befuellen
        			$this->config[$section][$key] = $value;
        		}
        	}
        	// leere Abschnitte (=leere Arrays) loeschen
        	if ((isset($config_ist[$section]) && count($config_ist[$section]) == 0))
        	{
        		unset($config_ist[$section]);
        	}
    	}
    
    	// die Ist-config durchlaufen 
    	// jetzt befinden sich hier nur noch die DB-Eintraege, die nicht verwendet werden und deshalb: 
    	// 1. in der DB geloescht werden koennen
    	// 2. in der normalen config geloescht werden koennen
		foreach ($config_ist as $section => $sectiondata)
    	{
    		foreach ($sectiondata as $key => $value)
        	{
        		$plp_name = self::$shortcut.'__'.$section.'__'.$key;
				$sql = 'DELETE FROM '.$this->table_name.'
        				      WHERE plp_name = ? 
        				        AND plp_org_id = ? ';
				$GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
                
				unset($this->config[$section][$key]);
        	}
			// leere Abschnitte (=leere Arrays) loeschen
        	if (count($this->config[$section]) === 0)
        	{
        		unset($this->config[$section]);
        	}
    	}

    	// die aktualisierten und bereinigten Konfigurationsdaten in die DB schreiben 
  		$this->save();
	}

    /**
     * Schreibt die Konfigurationsdaten in die Datenbank
     * @return void
     */
	public function save()
	{
    	foreach ($this->config as $section => $sectiondata)
    	{
        	foreach ($sectiondata as $key => $value)
        	{
            	if (is_array($value))
            	{
                	// um diesen Datensatz in der Datenbank als Array zu kennzeichnen, wird er von Doppelklammern eingeschlossen 
            		$value = '(('.implode(self::$dbtoken,$value).'))';
            	} 
            
  				$plp_name = self::$shortcut.'__'.$section.'__'.$key;
          
            	$sql = ' SELECT plp_id 
            			   FROM '.$this->table_name.' 
            			  WHERE plp_name = ? 
            			    AND ( plp_org_id = ?
                 		     OR plp_org_id IS NULL ) ';
            	$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
            	$row = $statement->fetchObject();

            	// Gibt es den Datensatz bereits?
            	// wenn ja: UPDATE des bestehende Datensatzes  
            	if (isset($row->plp_id) AND strlen($row->plp_id) > 0)
            	{
                	$sql = 'UPDATE '.$this->table_name.' 
                			   SET plp_value = ?
                			 WHERE plp_id = ? ';   
                    $GLOBALS['gDb']->queryPrepared($sql, array($value, $row->plp_id));           
            	}
            	// wenn nicht: INSERT eines neuen Datensatzes 
            	else
            	{
  					$sql = 'INSERT INTO '.$this->table_name.' (plp_org_id, plp_name, plp_value) 
  							VALUES (? , ? , ?)  -- $GLOBALS[\'gCurrentOrgId\'], self::$shortcut.\'__\'.$section.\'__\'.$key, $value '; 
            		$GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId'], self::$shortcut.'__'.$section.'__'.$key, $value));
            	}   
        	} 
    	}
	}

    /**
     * Liest die Konfigurationsdaten aus der Datenbank
     * @return void
     */
	public function read()
	{
		$sql = 'SELECT plp_id, plp_name, plp_value
             	  FROM '.$this->table_name.'
             	 WHERE plp_name LIKE ?
             	   AND ( plp_org_id = ?
                    OR plp_org_id IS NULL ) ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array(self::$shortcut.'__%', $GLOBALS['gCurrentOrgId'])); 
	
		while ($row = $statement->fetch())
		{
			$array = explode('__',$row['plp_name']);
		
			// wenn plp_value von ((  )) eingeschlossen ist, dann ist es als Array einzulesen
			if ((substr($row['plp_value'], 0, 2) == '((' ) && (substr($row['plp_value'], -2) == '))' ))
        	{                                                                          
        		$row['plp_value'] = substr($row['plp_value'], 2, -2);
        		$this->config[$array[1]] [$array[2]] = explode(self::$dbtoken,$row['plp_value']); 
        	}
        	else 
			{
            	$this->config[$array[1]] [$array[2]] = $row['plp_value'];
        	}
		}
	}

	/**
	 * Liest die Konfigurationsdaten des Plugins FormFiller (PFF) aus der Datenbank
	 * @return void
	 */
	public function readPff()
	{
		$sql = ' SELECT plp_id, plp_name, plp_value
             	   FROM '.$this->table_name.'
             	  WHERE plp_name LIKE ?
             	    AND ( plp_org_id = ?
                 	 OR plp_org_id IS NULL ) ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array('PFF__%', $GLOBALS['gCurrentOrgId'])); 
	
		while ($row = $statement->fetch())
		{
			$array = explode('__',$row['plp_name']);
	
			// wenn plp_value von ((  )) eingeschlossen ist, dann ist es als Array einzulesen
			if ((substr($row['plp_value'],0,2) == '((' ) && (substr($row['plp_value'],-2) == '))' ))
			{
				$row['plp_value'] = substr($row['plp_value'], 2, -2);
				$this->configpff[$array[1]] [$array[2]] = explode(self::$dbtoken,$row['plp_value']);
	
				//das erzeugte Array durchlaufen, auf (( )) pruefen und ggf. nochmal zerlegen
				for ($i = 0; $i < count($this->configpff[$array[1]] [$array[2]]); $i++)
				{
					if ((substr($this->configpff[$array[1]] [$array[2]][$i],0,2) == '((' ) && (substr($this->configpff[$array[1]] [$array[2]][$i],-2) == '))' ))
					{
						$temp = substr($this->configpff[$array[1]] [$array[2]][$i], 2, -2);
						$this->configpff[$array[1]] [$array[2]][$i] = array();
						$this->configpff[$array[1]] [$array[2]][$i] = explode(self::$dbtoken2,$temp);
					}
				}
			}
			else
			{
				$this->configpff[$array[1]] [$array[2]] = $row['plp_value'];
			}
		}
	}
	
    /**
     * Vergleicht die Daten in der version.php mit den Daten in der DB
     * @return bool
     */
	public function checkForUpdate()
	{
	 	$ret = false;
 	
	 	// pruefen, ob es die Tabelle ueberhaupt gibt
		$sql = 'SHOW TABLES LIKE \''.$this->table_name.'\' ';
   	 	$tableExistStatement = $GLOBALS['gDb']->queryPrepared($sql);
    
    	if ($tableExistStatement->rowCount())
    	{
			$plp_name = self::$shortcut.'__Plugininformationen__version';
          
    		$sql = 'SELECT plp_value 
            		  FROM '.$this->table_name.' 
            		 WHERE plp_name = ? 
            		   AND ( plp_org_id = ?
            	    	OR plp_org_id IS NULL ) ';
    		$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
    		$row = $statement->fetchObject();

    		// Vergleich Version.php  ./. DB (hier: version)
    		if (!isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value<>self::$version)
    		{
    			$ret = true;    
    		}
	
    		$plp_name = self::$shortcut.'__Plugininformationen__stand';
          
    		$sql = 'SELECT plp_value 
            		  FROM '.$this->table_name.' 
            		 WHERE plp_name = ?
            		   AND ( plp_org_id = ?
                 		OR plp_org_id IS NULL ) ';
            $statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
    		$row = $statement->fetchObject();

    		// Vergleich Version.php  ./. DB (hier: stand)
    		if (!isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value<>self::$stand)
    		{
    			$ret = true;    
    		}
    	}
    	else 
    	{
    		$ret = true; 
    	}
    	return $ret;
	}
	
    /**
     * Loescht die Konfigurationsdaten in der Datenbank
     * @param   int     $deinst_org_select  0 = Daten nur in aktueller Org loeschen, 1 = Daten in allen Org loeschen
     * @return  string  $result             Ergebnismeldung
     */
	public function deleteConfigData($deinst_org_select)
	{
    	$result      = '';		
    	$sqlWhereCondition = '';
		$result_data = false;
		$result_db   = false;
		
		if ($deinst_org_select == 0)                    //0 = Daten nur in aktueller Org loeschen 
		{
			$sqlWhereCondition = 'AND plp_org_id =  \''.$GLOBALS['gCurrentOrgId'].'\' ';	
		}

		$sql = 'DELETE FROM '.$this->table_name.'
        			  WHERE plp_name LIKE ?
                      '. $sqlWhereCondition ;
		$result_data = $GLOBALS['gDb']->queryPrepared($sql, array(self::$shortcut.'__%'));	
		$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN', array($this->table_name)) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN_ERROR', array($this->table_name)));
		
		// wenn die Tabelle nur Eintraege dieses Plugins hatte, sollte sie jetzt leer sein und kann geloescht werden
		$sql = 'SELECT * FROM '.$this->table_name.' ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql);

    	if ($statement->rowCount() == 0)
    	{
        	$sql = 'DROP TABLE '.$this->table_name.' ';
        	$result_db = $GLOBALS['gDb']->queryPrepared($sql);
        	$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_TABLE_DELETED', array($this->table_name )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_TABLE_DELETE_ERROR', array($this->table_name)));
        }
        else
        {
        	$result .= $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_CONFIGTABLE_DELETE_NOTPOSSIBLE', array($this->table_name )) ;
        }
		
		return $result;
	}
	
	/**
	 * Loescht die Nutzerdaten in der Datenbank
	 * @param   int     $deinst_org_select  0 = Daten nur in aktueller Org loeschen, 1 = Daten in allen Orgs loeschen
	 * @return  string  $result             Ergebnismeldung
	 */
	public function deleteKeyData($deinst_org_select)
	{
		global $g_tbl_praefix;
	
		$result = ''; 

		if($deinst_org_select == 0)                   //0 = Daten nur in aktueller Org loeschen
		{
			$sql = 'DELETE FROM '.TBL_KEYMANAGER_DATA.'
                          WHERE kmd_kmk_id IN 
              	        (SELECT kmk_id 
					       FROM ?
                	      WHERE kmk_org_id = ? )';
	
			$result_data = $GLOBALS['gDb']->queryPrepared($sql, array(TBL_KEYMANAGER_KEYS, $GLOBALS['gCurrentOrgId']));	
			$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN', array($g_tbl_praefix . '_keymanager_data' )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN_ERROR', array($g_tbl_praefix . '_keymanager_data' )));
		
			$sql = 'DELETE FROM '.TBL_KEYMANAGER_LOG.'
                          WHERE kml_kmk_id IN 
				        (SELECT kmk_id 
					       FROM ?
                          WHERE kmk_org_id = ? )';

			$result_log = $GLOBALS['gDb']->queryPrepared($sql, array(TBL_KEYMANAGER_KEYS, $GLOBALS['gCurrentOrgId']));	
			$result .= ($result_log ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN', array($g_tbl_praefix . '_keymanager_log' )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN_ERROR', array($g_tbl_praefix . '_keymanager_log')));
		
			$sql = 'DELETE FROM '.TBL_KEYMANAGER_KEYS.'
	        	          WHERE kmk_org_id = ? ';

			$result_keys = $GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId']));
			$result .= ($result_keys ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN', array($g_tbl_praefix . '_keymanager_keys' )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN_ERROR', array($g_tbl_praefix . '_keymanager_keys')));
		
			$sql = 'DELETE FROM '.TBL_KEYMANAGER_FIELDS.'
                          WHERE kmf_org_id = ? ';
			
			$result_fields = $GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId']));
			$result .= ($result_fields ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN', array($g_tbl_praefix . '_keymanager_fields' )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_DATA_DELETED_IN_ERROR', array($g_tbl_praefix . '_keymanager_fields')));
		}
		
		//drop tables keys, data, log and fields 
		$table_array = array(
				TBL_KEYMANAGER_FIELDS,
				TBL_KEYMANAGER_DATA,
				TBL_KEYMANAGER_KEYS,
				TBL_KEYMANAGER_LOG );
	
		foreach ($table_array as $table_name)
		{
			$result_db   = false;
			
			// wenn in der Tabelle keine Eintraege mehr sind, dann kann sie geloescht werden
			// oder wenn 'Daten in allen Orgs loeschen' gewaehlt wurde
			$sql = 'SELECT * FROM '.$table_name.' ';
			$statement = $GLOBALS['gDb']->queryPrepared($sql);
				
			if ($statement->rowCount() == 0 || $deinst_org_select == 1)
			{
				$sql = 'DROP TABLE '.$table_name.' ';
				$result_db = $GLOBALS['gDb']->queryPrepared($sql);
				$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_TABLE_DELETED', array($table_name )) : $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_TABLE_DELETE_ERROR', array($table_name)));
			}
			else 
			{
				$result .= $GLOBALS['gL10n']->get('PLG_KEYMANAGER_DEINST_TABLE_DELETE_NOTPOSSIBLE', array($table_name)) ;
			}
		}
		
		return $result;
	}
}
