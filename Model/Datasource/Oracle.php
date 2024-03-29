<?php
/**
 * Oracle layer for DBO.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Model.Datasource.Database
 * @since         CakePHP v 1.2.0.4041
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('DboSource', 'Model/Datasource');

/**
 * Oracle layer for DBO.
 *
 * Long description for class
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model.datasources.dbo
 */
class Oracle extends DboSource {

/**
 * Configuration options
 *
 * @var array
 * @access public
 */
	var $config = array();

/**
 * Alias
 *
 * @var string
 */
	var $alias = '';

/**
 * Sequence names as introspected from the database
 */
	var $_sequences = array();

/**
 * Transaction in progress flag
 *
 * @var boolean
 */
	var $__transactionStarted = false;

/**
 * Column definitions
 *
 * @var array
 * @access public
 */
	var $columns = array(
		'primary_key' => array('name' => ''),
		'string' => array('name' => 'varchar2', 'limit' => '255'),
		'text' => array('name' => 'varchar2', 'limit' => '4000'),
		'integer' => array('name' => 'number', 'limit' => '11'),
		'float' => array('name' => 'float'),
		'datetime' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'timestamp' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'time' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'date' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'binary' => array('name' => 'bytea'),
		'boolean' => array('name' => 'number', 'limit'=>'1'),
		'number' => array('name' => 'number'),
		'inet' => array('name' => 'inet'));

/**
 * Connection object
 *
 * @var mixed
 * @access protected
 */
	var $connection;

/**
 * Query limit
 *
 * @var int
 * @access protected
 */
	var $_limit = -1;

/**
 * Query offset
 *
 * @var int
 * @access protected
 */
	var $_offset = 0;

/**
 * Enter description here...
 *
 * @var unknown_type
 * @access protected
 */
	var $_map;

/**
 * Current Row
 *
 * @var mixed
 * @access protected
 */
	var $_currentRow;

/**
 * Number of rows
 *
 * @var int
 * @access protected
 */
	var $_numRows;

/**
 * Query results
 *
 * @var mixed
 * @access protected
 */
	var $_results;

/**
 * Last error issued by oci extension
 *
 * @var unknown_type
 */
	var $_error;

/**
 * Base configuration settings for MySQL driver
 *
 * @var array
 */
	var $_baseConfig = array(
		'persistent' => true,
		'host' => 'localhost',
		'login' => 'system',
		'password' => '',
		'database' => 'cake',
		'nls_sort' => '',
		'nls_sort' => ''
	);

/**
 * Table-sequence map
 *
 * @var unknown_type
 */
	var $_sequenceMap = array();

/**
 * The starting character that this DataSource uses for quoted identifiers.
 *
 * @var string
 */
	public $startQuote = '"';

/**
 * The ending character that this DataSource uses for quoted identifiers.
 *
 * @var string
 */
	public $endQuote = '"';

/**
 * Connects to the database using options in the given configuration array.
 *
 * @return boolean True if the database could be connected, else false
 * @access public
 */
	function connect() {
		$config = $this->config;
		$this->connected = false;
		$config['charset'] = !empty($config['charset']) ? $config['charset'] : null;

		if (!$config['persistent']) {
			$this->connection = oci_connect($config['login'], $config['password'], $config['database'], $config['charset']);
		} else {
			$this->connection = oci_pconnect($config['login'], $config['password'], $config['database'], $config['charset']);
		}

		if ($this->connection) {
			$this->connected = true;
			if (!empty($config['nls_sort'])) {
				$this->execute('ALTER SESSION SET NLS_SORT='.$config['nls_sort']);
			}

			if (!empty($config['nls_comp'])) {
				$this->execute('ALTER SESSION SET NLS_COMP='.$config['nls_comp']);
			}
			$this->execute("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'");
		} else {
			$this->connected = false;
			$this->_setError();
			return false;
		}
		return $this->connected;
	}

/**
 * Keeps track of the most recent Oracle error
 *
 */
	function _setError($source = null, $clear = false) {
		if ($source) {
			$e = oci_error($source);
		} else {
			$e = oci_error();
		}
		$this->_error = $e['message'];
		if ($clear) {
			$this->_error = null;
		}
	}

/**
 * Sets the encoding language of the session
 *
 * @param string $lang language constant
 * @return bool
 */
	function setEncoding($lang) {
		if (!$this->execute('ALTER SESSION SET NLS_LANGUAGE='.$lang)) {
			return false;
		}
		return true;
	}

/**
 * Gets the current encoding language
 *
 * @return string language constant
 */
	function getEncoding() {
		$sql = 'SELECT VALUE FROM NLS_SESSION_PARAMETERS WHERE PARAMETER=\'NLS_LANGUAGE\'';
		if (!$this->execute($sql)) {
			return false;
		}

		if (!$row = $this->fetchRow()) {
			return false;
		}
		return $row[0]['VALUE'];
	}

/**
 * Disconnects from database.
 *
 * @return boolean True if the database could be disconnected, else false
 * @access public
 */
	function disconnect() {
		if ($this->connection) {
			$this->connected = !oci_close($this->connection);
			return !$this->connected;
		}
	}

/**
 * Scrape the incoming SQL to create the association map. This is an extremely
 * experimental method that creates the association maps since Oracle will not tell us.
 *
 * @param string $sql
 * @return false if sql is nor a SELECT
 * @access protected
 */
	function _scrapeSQL($sql) {
		$sql = str_replace("\"", '', $sql);

		if (1) {
			// in Common Table Expression there are way more selects
			// we care only about the final select
			// workaround:
			$selectParts = preg_split('/\SELECT\b/', $sql);
			$sql = array_pop($selectParts);
		}

		$preFrom = preg_split('/\bFROM\b/', $sql);
		$preFrom = $preFrom[0];
		$find = array('SELECT');
		$replace = array('');
		$fieldList = trim(str_replace($find, $replace, $preFrom));
		$fields = preg_split('/,\s+/', $fieldList);//explode(', ', $fieldList);
		$lastTableName	= '';

		foreach($fields as $key => $value) {
			if ($value != 'COUNT(*) AS count') {
				if (preg_match('/\s+(\w+(\.\w+)*)$/', $value, $matches)) {
					$fields[$key]	= $matches[1];

					if (preg_match('/^(\w+\.)/', $value, $matches)) {
						$fields[$key]	= $matches[1] . $fields[$key];
						$lastTableName	= $matches[1];
					}
				}
				/*
				if (preg_match('/(([[:alnum:]_]+)\.[[:alnum:]_]+)(\s+AS\s+(\w+))?$/i', $value, $matches)) {
					$fields[$key]	= isset($matches[4]) ? $matches[2] . '.' . $matches[4] : $matches[1];
				}
				*/
			}
		}
		$this->_map = array();

		foreach($fields as $f) {
			$e = explode('.', $f);
			if (count($e) > 1) {
				$table = $e[0];
				// here used to be a strtolower call
				// https://github.com/ptica/Oracle/commit/aacb1fe7df0bf5315ef8d35eb6105468a46fb54b
				$field = $e[1];
			} else {
				$table = 0;
				$field = $e[0];
			}
			$this->_map[] = array($table, $field);
		}
	}

/**
 * Modify a SQL query to limit (and offset) the result set
 *
 * @param integer $limit Maximum number of rows to return
 * @param integer $offset Row to begin returning
 * @return modified SQL Query
 * @access public
 */
	function limit($limit = -1, $offset = 0) {
		$this->_limit = (int) $limit;
		$this->_offset = (int) $offset;
	}

/**
 * Returns number of rows in previous resultset. If no previous resultset exists,
 * this returns false.
 *
 * @return integer Number of rows in resultset
 * @param mixed $source Not used
 * @access public
 */
	function lastNumRows($source = null) {
		return $this->_numRows;
	}

/**
 * Executes given SQL statement. This is an overloaded method.
 *
 * @param string $sql SQL statement
 * @param array $params list of params to be bound to query NOT IMPLEMENTED !!!
 * @param array $prepareOptions Options to be used in the prepare statement NOT IMPLEMENTED !!!
 * @return resource Result resource identifier or null
 * @access protected
 */
	function _execute($sql, $params = array(), $prepareOptions = array()) {
		$this->_statementId = oci_parse($this->connection, $sql);
		if (!$this->_statementId) {
			$this->_setError($this->connection);
			return false;
		}

		if ($this->__transactionStarted) {
			$mode = OCI_DEFAULT;
		} else {
			$mode = OCI_COMMIT_ON_SUCCESS;
		}

		if (!oci_execute($this->_statementId, $mode)) {
			$this->_setError($this->_statementId);
			return false;
		}

		$this->_setError(null, true);

		switch(oci_statement_type($this->_statementId)) {
			case 'DESCRIBE':
			case 'SELECT':
				$this->_scrapeSQL($sql);
			break;
			default:
				return $this->_statementId;
			break;
		}

		if ($this->_limit >= 1) {
			oci_set_prefetch($this->_statementId, $this->_limit);
		} else {
			oci_set_prefetch($this->_statementId, 3000);
		}
		$this->_numRows = oci_fetch_all($this->_statementId, $this->_results, $this->_offset, $this->_limit, OCI_NUM | OCI_FETCHSTATEMENT_BY_ROW);
		$this->_currentRow = 0;
		$this->limit();
		return $this->_statementId;
	}

/**
 * Fetch result row
 *
 * @return array
 * @param string $sql Some SQL to be executed. WE DO NOT DO LAZY execute YET !!!
 * @access public
 */
	function fetchRow($sql = NULL) {
		if ($this->_currentRow >= $this->_numRows) {
			#oci_free_statement($this->_statementId);
			$this->_statementId = null;
			$this->_map = null;
			$this->_results = null;
			$this->_currentRow = null;
			$this->_numRows = null;
			return false;
		}
		$resultRow = array();

		foreach($this->_results[$this->_currentRow] as $index => $field) {
			list($table, $column) = $this->_map[$index];

			if (strpos($column, ' count')) {
				$resultRow[0]['count'] = $field;
			} else {
				$resultRow[$table][$column] = $this->_results[$this->_currentRow][$index];
			}
		}

		if (isset($resultRow[0])) {
			$this->fetchVirtualField($resultRow);
		}

		$this->_currentRow++;
		return $resultRow;
	}

/**
 * Fetches the next row from the current result set
 *
 * @return unknown
 */
	function fetchResult() {
		return $this->fetchRow();
	}

/**
 * Inserts multiple values into a table
 *
 * @param string $table The table being inserted into.
 * @param array $fields The array of field/column names being inserted.
 * @param array $values The array of values to insert. The values should
 *   be an array of rows. Each row should have values keyed by the column name.
 *   Each row must have the values in the same order as $fields.
 * @return bool
 */
	public function insertMulti($table, $fields, $values) {
		$table = $this->fullTableName($table);
		//$holder = implode(',', array_fill(0, count($fields), '?'));
		$holder = implode(', ', array_map(function($n) { return sprintf(':my%d', $n); },  array_keys($fields)));
		$fields = implode(', ', $fields);

		/*$pdoMap = array(
			'integer' => PDO::PARAM_INT,
			'float' => PDO::PARAM_STR,
			'boolean' => PDO::PARAM_BOOL,
			'string' => PDO::PARAM_STR,
			'text' => PDO::PARAM_STR
		);
		$columnMap = array(); */

		$sql = "INSERT INTO {$table} ({$fields}) VALUES ({$holder})";
		$statement = oci_parse($this->connection, $sql);
		// TODO check transactions
		$this->begin();

		/*foreach ($values[key($values)] as $key => $val) {
			$type = $this->introspectType($val);
			$columnMap[$key] = $pdoMap[$type];
		}*/

		foreach ($values as $value) {
			$i = 0;
			foreach ($value as $col => $val) {
				//$statement->bindValue($i, $val, $columnMap[$col]);
				$val = (int) $val;
				// HAS TO BE $value[$col] as oracle binds the php ref!
				oci_bind_by_name($statement, ':my'.$col, $value[$col]);
				$i += 1;
			}
			//$statement->execute();
			$res = oci_execute($statement);
			// TODO  $statement->closeCursor();
			if (!$res) {
				return false;
			}

			if ($this->fullDebug) {
				$this->logQuery($sql, $value);
			}
		}
		return $this->commit();
		oci_free_statement($statement);
		return true;
	}



/**
 * Checks to see if a named sequence exists
 *
 * @param string $sequence
 * @return bool
 * @access public
 */
	function sequenceExists($sequence) {
		$sql = "SELECT SEQUENCE_NAME FROM USER_SEQUENCES WHERE SEQUENCE_NAME = '$sequence'";
		if (!$this->execute($sql)) {
			return false;
		}
		return $this->fetchRow();
	}

/**
 * Creates a database sequence
 *
 * @param string $sequence
 * @return bool
 * @access public
 */
	function createSequence($sequence) {
		$sql = "CREATE SEQUENCE $sequence";
		return $this->execute($sql);
	}

/**
 * Create trigger
 *
 * @param string $table
 * @return mixed
 * @access public
 */
	function createTrigger($table) {
		$sql = "CREATE OR REPLACE TRIGGER pk_$table" . "_trigger BEFORE INSERT ON $table FOR EACH ROW BEGIN SELECT pk_$table.NEXTVAL INTO :NEW.ID FROM DUAL; END;";
		return $this->execute($sql);
	}

/**
 * Caches/returns cached results for child instances
 *
 * @param mixed $data Unused in this class.
 * @return array tablenames in the database
 * @access public
 */
	function listSources($data = null) {
		$cache = parent::listSources();
		if ($cache != null) {
			return $cache;
		}
		$sql = 'SELECT view_name AS name FROM all_views UNION SELECT table_name AS name FROM all_tables';

		if (!$this->execute($sql)) {
			return false;
		}
		$sources = array();

		while($r = $this->fetchRow()) {
			// list of all views and tables - not sure if it has to be strlowered
			$sources[] = strtolower($r[0]['name']);
		}
		parent::listSources($sources);
		return $sources;
	}

/**
 * Returns an array of the fields in given table name.
 *
 * @param object instance of a model to inspect
 * @return array Fields in table. Keys are name and type
 * @access public
 */
	public function describe($model) {
		$key = $this->fullTableName($model, false);
		$table = $this->fullTableName($model, false);

		// set sequence name
		if (!empty($model->sequence)) {
			$this->_sequenceMap[$table] = $model->sequence;
		} elseif (!empty($model->table)) {
			$this->_sequenceMap[$table] = $model->table . '_seq';
		}

		$cache = parent::describe($key);
		if ($cache) {
			return $cache;
		}

		$dbLink    = isset($model->oracleDbLink) ? '@'.$model->oracleDbLink : '';
		$tableName = isset($model->oracleSrcTable) ? $model->oracleSrcTable : $table;

		$sql = "SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, DATA_PRECISION FROM all_tab_columns$dbLink WHERE table_name = '$tableName'";

		if (!$this->execute($sql)) {
			return false;
		}

		$fields = array();

		for ($i = 0; $row = $this->fetchRow(); $i++) {
			$fields[$row[0]['COLUMN_NAME']] = array(
				//'type'=> $this->column($row[0]['DATA_TYPE']),
				'type'=> $this->column($this->_describe_format($row[0])),
				'length'=> $row[0]['DATA_LENGTH'],
			);
		}
		$this->_cacheDescription($this->fullTableName($model, false), $fields);

		return $fields;
	}
	protected function _describe_format($col) {
		$type   = $col['DATA_TYPE'];
		$length = $col['DATA_LENGTH'];
		if ($type == 'NUMBER') {
			$length = $col['DATA_PRECISION'];
			return "$type($length)";
		}
		return $type;
	}

/**
 * Deletes all the records in a table and drops all associated auto-increment sequences.
 * Using DELETE instead of TRUNCATE because it causes locking problems.
 *
 * @param mixed $table A string or model class representing the table to be truncated
 * @param integer $reset If -1, sequences are dropped, if 0 (default), sequences are reset,
 *						and if 1, sequences are not modified
 * @return boolean	SQL TRUNCATE TABLE statement, false if not applicable.
 * @access public
 *
 */
	function truncate($table, $reset = 0) {

		if (empty($this->_sequences)) {
			$sql = "SELECT sequence_name FROM all_sequences";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_sequences[] = strtolower($row[0]['sequence_name']);
			}
		}

		$this->execute('DELETE FROM ' . $this->fullTableName($table));
		if (!isset($this->_sequenceMap[$table]) || !in_array($this->_sequenceMap[$table], $this->_sequences)) {
			return true;
		}
		if ($reset === 0) {
			$this->execute("SELECT {$this->_sequenceMap[$table]}.nextval FROM dual");
			$row = $this->fetchRow();
			$currval = $row[$this->_sequenceMap[$table]]['nextval'];

			$this->execute("SELECT min_value FROM all_sequences WHERE sequence_name = '{$this->_sequenceMap[$table]}'");
			$row = $this->fetchRow();
			$min_value = $row[0]['min_value'];

			if ($min_value == 1) $min_value = 0;
			$offset = -($currval - $min_value);

			$this->execute("ALTER SEQUENCE {$this->_sequenceMap[$table]} INCREMENT BY $offset MINVALUE $min_value");
			$this->execute("SELECT {$this->_sequenceMap[$table]}.nextval FROM dual");
			$this->execute("ALTER SEQUENCE {$this->_sequenceMap[$table]} INCREMENT BY 1");
		} else {
			//$this->execute("DROP SEQUENCE {$this->_sequenceMap[$table]}");
		}
		return true;
	}

/**
 * Enables, disables, and lists table constraints
 *
 * Note: This method could have been written using a subselect for each table,
 * however the effort Oracle expends to run the constraint introspection is very high.
 * Therefore, this method caches the result once and loops through the arrays to find
 * what it needs. It reduced my query time by 50%. YMMV.
 *
 * @param string $action
 * @param string $table
 * @return mixed boolean true or array of constraints
 */
	function constraint($action, $table) {
		if (empty($table)) {
			trigger_error(__('Must specify table to operate on constraints', true));
		}

		$table = strtoupper($table);

		if (empty($this->_keyConstraints)) {
			$sql = "SELECT
					  table_name,
					  c.constraint_name
					FROM all_cons_columns cc
					LEFT JOIN all_indexes i ON (cc.constraint_name = i.index_name)
					LEFT JOIN all_constraints c ON(c.constraint_name = cc.constraint_name)";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_keyConstraints[] = array($row[0]['table_name'], $row['c']['constraint_name']);
			}
		}

		$relatedKeys = array();
		foreach ($this->_keyConstraints as $c) {
			if ($c[0] == $table) {
				$relatedKeys[] = $c[1];
			}
		}

		if (empty($this->_constraints)) {
			$sql = "SELECT
					  table_name,
					  constraint_name,
					  r_constraint_name
					FROM
					  all_constraints";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_constraints[] = $row[0];
			}
		}

		$constraints = array();
		foreach ($this->_constraints as $c) {
			if (in_array($c['r_constraint_name'], $relatedKeys)) {
				$constraints[] = array($c['table_name'], $c['constraint_name']);
			}
		}

		foreach ($constraints as $c) {
			list($table, $constraint) = $c;
			switch ($action) {
				case 'enable':
					$this->execute("ALTER TABLE $table ENABLE CONSTRAINT $constraint");
					break;
				case 'disable':
					$this->execute("ALTER TABLE $table DISABLE CONSTRAINT $constraint");
					break;
				case 'list':
					return $constraints;
					break;
				default:
					trigger_error(__('DboOracle::constraint() accepts only enable, disable, or list', true));
			}
		}
		return true;
	}

/**
 * Returns an array of the indexes in given table name.
 *
 * @param string $model Name of model to inspect
 * @return array Fields in table. Keys are column and unique
 */
	function index($model) {
		$index = array();
		$table = $this->fullTableName($model, false);
		if ($table) {
			$indexes = $this->query('SELECT
			  cc.table_name,
			  cc.column_name,
			  cc.constraint_name,
			  c.constraint_type,
			  i.index_name,
			  i.uniqueness
			FROM all_cons_columns cc
			LEFT JOIN all_indexes i ON(cc.constraint_name = i.index_name)
			LEFT JOIN all_constraints c ON(c.constraint_name = cc.constraint_name)
			WHERE cc.table_name = \'' . strtoupper($table) .'\'');
			foreach ($indexes as $i => $idx) {
				if ($idx['c']['constraint_type'] == 'P') {
					$key = 'PRIMARY';
				} else {
					continue;
				}
				if (!isset($index[$key])) {
					$index[$key]['column'] = strtolower($idx['cc']['column_name']);
					$index[$key]['unique'] = intval($idx['i']['uniqueness'] == 'UNIQUE');
				} else {
					if (!is_array($index[$key]['column'])) {
						$col[] = $index[$key]['column'];
					}
					$col[] = strtolower($idx['cc']['column_name']);
					$index[$key]['column'] = $col;
				}
			}
		}
		return $index;
	}

/**
 * Generate a Oracle Alter Table syntax for the given Schema comparison
 *
 * @param unknown_type $schema
 * @return unknown
 */
	function alterSchema($compare, $table = null) {
		if (!is_array($compare)) {
			return false;
		}
		$out = '';
		$colList = array();
		foreach($compare as $curTable => $types) {
			if (!$table || $table == $curTable) {
				$out .= 'ALTER TABLE ' . $this->fullTableName($curTable) . " \n";
				foreach($types as $type => $column) {
					switch($type) {
						case 'add':
							foreach($column as $field => $col) {
								$col['name'] = $field;
								$alter = 'ADD '.$this->buildColumn($col);
								if (0 && isset($col['after'])) {
									$alter .= ' AFTER '. $this->name($col['after']);
								}
								$colList[] = $alter;
							}
						break;
						case 'drop':
							foreach($column as $field => $col) {
								$col['name'] = $field;
								$colList[] = 'DROP COLUMN '.$this->name($field);
							}
						break;
						case 'change':
							foreach($column as $field => $col) {
								if (!isset($col['name'])) {
									$col['name'] = $field;
								}
								// remove the not null statement if needed
								// as Oracle forbids setting NOT NULL if the constraint is already there!
								if (isset($col['null']) && $col['null'] === $this->_has_null_flag($curTable, $col['name'])) {
									unset($col['null']);
								}
								$colList[] = 'MODIFY ('.$this->buildColumn($col).')';
							}
						break;
					}
				}
				$out .= "\t" . implode(",\n\t", $colList) . "\n\n";
			}
		}
		return $out;
	}

	function _has_null_flag($table, $column) {
		$sql = "SELECT NULLABLE FROM all_tab_columns WHERE table_name='$table' AND column_name='$column'";
		if ($this->execute($sql)) {
			$row = $this->fetchRow();
			return $row[0]['NULLABLE'] === 'Y';
		}
		return false;
	}

/**
 * This method should quote Oracle identifiers. Well it doesn't.
 * It would break all scaffolding and all of Cake's default assumptions.
 *
 * @param unknown_type $var
 * @return unknown
 * @access public
 */
	function name($name) {
		if (strpos($name, '"') === false) {
			if (strpos($name, '.') !== false) {
				list($model, $field) = explode('.', $name);
				$model = "\"$model\"";
				if (1 || $field[0] == "_") {
					$field = "\"$field\"";
				}
				$name = "$model.$field";
			} else {
				if ($name !== '*') {
					$name = "\"$name\"";
				}
			}
		}
		return $name;
	}

/**
 * Begin a transaction
 *
 * @param unknown_type $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions).
 */
	function begin() {
		$this->__transactionStarted = true;
		return true;
	}

/**
 * Rollback a transaction
 *
 * @param unknown_type $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 */
	function rollback() {
		return oci_rollback($this->connection);
	}

/**
 * Commit a transaction
 *
 * @param unknown_type $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 */
	function commit() {
		$this->__transactionStarted = false;
		return oci_commit($this->connection);
	}

/**
 * Converts database-layer column types to basic types
 *
 * @param string $real Real database-layer column type (i.e. "varchar(255)")
 * @return string Abstract column type (i.e. "string")
 * @access public
 */
	function column($real) {
		if (is_array($real)) {
			$col = $real['name'];

			if (isset($real['limit'])) {
				$col .= '('.$real['limit'].')';
			}
			return $col;
		} else {
			// column type names
			// behaviour is broken without strtolower
			$real = strtolower($real);
		}
		$col = str_replace(')', '', $real);
		$limit = null;
		if (strpos($col, '(') !== false) {
			list($col, $limit) = explode('(', $col);
		}

		if (in_array($col, array('date', 'timestamp'))) {
			return $col;
		}
		if ($col == 'number' && $limit == 1) {
			return 'boolean';
		}
		if (strpos($col, 'number') !== false) {
			return 'integer';
		}
		if (strpos($col, 'integer') !== false) {
			return 'integer';
		}
		if (strpos($col, 'char') !== false) {
			return 'string';
		}
		if (strpos($col, 'text') !== false) {
			return 'text';
		}
		if (strpos($col, 'blob') !== false) {
			return 'binary';
		}
		if (in_array($col, array('float', 'double', 'decimal'))) {
			return 'float';
		}
		if ($col == 'boolean') {
			return $col;
		}
		return 'text';
	}

/**
 * Returns a quoted and escaped string of $data for use in an SQL statement.
 *
 * @param string $data String to be prepared for use in an SQL statement
 * @return string Quoted and escaped
 * @access public
 */
	function value($data, $column = null, $safe = false) {

		if (is_array($data) && !empty($data)) {
			return array_map(
				array(&$this, 'value'),
				$data, array_fill(0, count($data), $column)
			);
		} elseif (is_object($data) && isset($data->type, $data->value)) {
			if ($data->type == 'identifier') {
				return $this->name($data->value);
			} elseif ($data->type == 'expression') {
				return $data->value;
			}
		} elseif (in_array($data, array('{$__cakeID__$}', '{$__cakeForeignKey__$}'), true)) {
			return $data;
		}

		if ($data === null || (is_array($data) && empty($data))) {
			return 'NULL';
		}

		if (empty($column)) {
			$column = $this->introspectType($data);
		}


		switch ($column) {
			case 'date':
				$data = date('Y-m-d H:i:s', strtotime($data));
				$data = "TO_DATE('$data', 'YYYY-MM-DD HH24:MI:SS')";
				break;
			case 'string':
			case 'text':
				$data = "'".$data."'";
				break;
			case 'binary':
			case 'integer' :
			case 'float' :
			case 'boolean':
			default:
				if ($data === '') {
					return 'NULL';

				} elseif (is_float($data)) {
					return str_replace(',', '.', strval($data));

				} elseif ((is_int($data) || $data === '0') || (
					is_numeric($data) && strpos($data, ',') === false &&
					$data[0] != '0' && strpos($data, 'e') === false)
				) {
					return $data;
				}
				$data = str_replace("'", "''", $data);
				$data = "'$data'";
				return $data;
				break;
		}
		return $data;
	}

/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param string
 * @return integer
 * @access public
 */
	function lastInsertId($source = null) {
		$sequence = $this->_sequenceMap[$source];
		$sql = "SELECT \"$sequence\".currval FROM dual";

		if (!$this->execute($sql)) {
			return false;
		}

		while($row = $this->fetchRow()) {
			return $row[$sequence]['currval'];
		}
		return false;
	}

/**
 * Returns a formatted error message from previous database operation.
 *
 * @return string Error message with error number
 * @param PDOStatement $query the query to extract the error from if any - NOT ON PDO YET !!!
 * @access public
 */
	function lastError(PDOStatement $query = null) {
		return $this->_error;
	}

/**
 * Returns number of affected rows in previous database operation. If no previous operation exists, this returns false.
 *
 * @return int Number of affected rows
 * @param mixed $source The source to check.
 * @access public
 */
	function lastAffected($source = null) {
		return $this->_statementId ? oci_num_rows($this->_statementId): false;
	}

/**
 * Renders a final SQL statement by putting together the component parts in the correct order
 *
 * @param string $type
 * @param array $data
 * @return string
 */
	function renderStatement($type, $data) {
		extract($data);
		$aliases = null;

		switch (strtolower($type)) {
			case 'select':
				return "SELECT {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order} {$limit}";
			break;
			case 'create':
				return "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
			break;
			case 'update':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} ";
				}
				return "UPDATE {$table} {$aliases}SET {$fields} {$conditions}";
			break;
			case 'delete':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} ";
				}
				return "DELETE FROM {$table} {$aliases}{$conditions}";
			break;
			case 'schema':
				// filter KEY indexes as oracle seems not to support KEY definition during create table
				// TODO: should create the key somehow
				$indexes = array_filter($indexes, function ($value) {
					return preg_match("/^PRIMARY/", $value);
				});
				foreach (array('columns', 'indexes') as $var) {
					if (is_array(${$var})) {
						${$var} = "\t" . implode(",\n\t", array_filter(${$var}));
					}
				}
				if (trim($indexes) != '') {
					$columns .= ',';
				}
				return "CREATE TABLE {$table} (\n{$columns}{$indexes})";
			break;
			case 'alter':
				break;
		}
	}

/**
 * ptica; OVERLOADED FOR NO OBVIOUS REASON
 *        + out of sync now, causes https://github.com/ptica/Oracle/issues/15
 *        = switching out for now
 *
 * @param Model $Model
 * @param unknown_type $LinkModel
 * @param string $type Association type
 * @param unknown_type $association
 * @param unknown_type $assocData
 * @param unknown_type $queryData
 * @param unknown_type $external
 * @param unknown_type $resultSet
 * @param integer $recursive Number of levels of association
 * @param array $stack
 */
	function queryAssociation_SEEMS_OUDATED(Model $Model, Model $LinkModel, $type, $association, $assocData, &$queryData, $external, &$resultSet, $recursive, $stack) {
		if ($query = $this->generateAssociationQuery($Model, $LinkModel, $type, $association, $assocData, $queryData, $external, $resultSet)) {
			if (!isset($resultSet) || !is_array($resultSet)) {
				if (Configure::read() > 0) {
					echo '<div style = "font: Verdana bold 12px; color: #FF0000">' . sprintf(__('SQL Error in model %s:', true), $Model->alias) . ' ';
					if (isset($this->error) && $this->error != null) {
						echo $this->error;
					}
					echo '</div>';
				}
				return null;
			}
			$count = count($resultSet);

			if ($type === 'hasMany' && (!isset($assocData['limit']) || empty($assocData['limit']))) {
				$ins = $fetch = array();
				for ($i = 0; $i < $count; $i++) {
					if ($in = $this->insertQueryData('{$__cakeID__$}', $resultSet[$i], $association, $Model, $stack)) {
						$ins[] = $in;
					}
				}

				if (!empty($ins)) {
					$fetch = array();
					$ins = array_chunk($ins, 1000);
					foreach ($ins as $i) {
						$q = str_replace('{$__cakeID__$}', implode(', ', $i), $query);
						$q = str_replace('= (', 'IN (', $q);
						$res = $this->fetchAll($q, $Model->cacheQueries, $Model->alias);
						$fetch = array_merge($fetch, $res);
					}
				}

				if (!empty($fetch) && is_array($fetch)) {
					if ($recursive > 0) {

						foreach ($LinkModel->__associations as $type1) {
							foreach ($LinkModel->{$type1} as $assoc1 => $assocData1) {
								$deepModel =& $LinkModel->{$assoc1};
								$tmpStack = $stack;
								$tmpStack[] = $assoc1;

								if ($LinkModel->useDbConfig === $deepModel->useDbConfig) {
									$db =& $this;
								} else {
									$db =& ConnectionManager::getDataSource($deepModel->useDbConfig);
								}
								$db->queryAssociation($LinkModel, $deepModel, $type1, $assoc1, $assocData1, $queryData, true, $fetch, $recursive - 1, $tmpStack);
							}
						}
					}
				}
				return $this->_mergeHasMany($resultSet, $fetch, $association, $Model, $LinkModel, $recursive);
			} elseif ($type === 'hasAndBelongsToMany') {
				$ins = $fetch = array();
				for ($i = 0; $i < $count; $i++) {
					if ($in = $this->insertQueryData('{$__cakeID__$}', $resultSet[$i], $association, $Model, $stack)) {
						$ins[] = $in;
					}
				}

				$foreignKey = $Model->hasAndBelongsToMany[$association]['foreignKey'];
				$joinKeys = array($foreignKey, $Model->hasAndBelongsToMany[$association]['associationForeignKey']);
				list($with, $habtmFields) = $Model->joinModel($Model->hasAndBelongsToMany[$association]['with'], $joinKeys);
				$habtmFieldsCount = count($habtmFields);

				if (!empty($ins)) {
					$fetch = array();
					$ins = array_chunk($ins, 1000);
					foreach ($ins as $i) {
						$q = str_replace('{$__cakeID__$}', '(' .implode(', ', $i) .')', $query);
						$q = str_replace('= (', 'IN (', $q);
						$q = str_replace('  WHERE 1 = 1', '', $q);

						$q = $this->insertQueryData($q, null, $association, $Model, $stack);
						if ($q != false) {
							$res = $this->fetchAll($q, $Model->cacheQueries, $Model->alias);
							$fetch = array_merge($fetch, $res);
						}
					}
				}
			}

			for ($i = 0; $i < $count; $i++) {
				$row =& $resultSet[$i];

				if ($type !== 'hasAndBelongsToMany') {
					$q = $this->insertQueryData($query, $resultSet[$i], $association, $Model, $stack);
					if ($q != false) {
						$fetch = $this->fetchAll($q, $Model->cacheQueries, $Model->alias);
					} else {
						$fetch = null;
					}
				}

				if (!empty($fetch) && is_array($fetch)) {
					if ($recursive > 0) {

						foreach ($LinkModel->__associations as $type1) {
							foreach ($LinkModel->{$type1} as $assoc1 => $assocData1) {

								$deepModel =& $LinkModel->{$assoc1};
								if (($type1 === 'belongsTo') || ($deepModel->alias === $Model->alias && $type === 'belongsTo') || ($deepModel->alias != $Model->alias)) {
									$tmpStack = $stack;
									$tmpStack[] = $assoc1;
									if ($LinkModel->useDbConfig == $deepModel->useDbConfig) {
										$db =& $this;
									} else {
										$db =& ConnectionManager::getDataSource($deepModel->useDbConfig);
									}
									$db->queryAssociation($LinkModel, $deepModel, $type1, $assoc1, $assocData1, $queryData, true, $fetch, $recursive - 1, $tmpStack);
								}
							}
						}
					}
					if ($type == 'hasAndBelongsToMany') {
						$merge = array();
						foreach($fetch as $j => $data) {
							if (isset($data[$with]) && $data[$with][$foreignKey] === $row[$Model->alias][$Model->primaryKey]) {
								if ($habtmFieldsCount > 2) {
									$merge[] = $data;
								} else {
									$merge[] = Set::diff($data, array($with => $data[$with]));
								}
							}
						}
						if (empty($merge) && !isset($row[$association])) {
							$row[$association] = $merge;
						} else {
							$this->_mergeAssociation($resultSet[$i], $merge, $association, $type);
						}
					} else {
						$this->_mergeAssociation($resultSet[$i], $fetch, $association, $type);
					}
					$resultSet[$i][$association] = $LinkModel->afterfind($resultSet[$i][$association]);

				} else {
					$tempArray[0][$association] = false;
					$this->_mergeAssociation($resultSet[$i], $tempArray, $association, $type);
				}
			}
		}
	}

/**
 * Generate a "drop table" statement for the given Schema object
 *
 * @param CakeSchema $schema An instance of a subclass of CakeSchema
 * @param string $table Optional.  If specified only the table name given will be generated.
 *						Otherwise, all tables defined in the schema are generated.
 * @return string
 */
		function dropSchema(CakeSchema $schema, $table = null) {
			$out = '';

			foreach ($schema->tables as $curTable => $columns) {
				if (!$table || $table == $curTable) {
					$out .= 'DROP TABLE ' . $this->fullTableName($curTable) . "\n";
				}
			}
			return $out;
		}

                function hasResult()
                {
                    return true;
                }
	/**
	* Creates a WHERE clause by parsing given conditions array. Used by DboSource::conditions().
	*
	* @param array $conditions Array or string of conditions
	* @param bool $quoteValues If true, values should be quoted
	* @param Model $Model A reference to the Model instance making the query
	* @return string SQL fragment
	*/
		public function conditionKeysToString($conditions, $quoteValues = true, Model $Model = null) {
			$out = array();
			$data = $columnType = null;
			$bool = array('and', 'or', 'not', 'and not', 'or not', 'xor', '||', '&&');

			foreach ($conditions as $key => $value) {
				$join = ' AND ';
				$not = null;

				if (is_array($value)) {
					$valueInsert = (
						!empty($value) &&
						(substr_count($key, '?') === count($value) || substr_count($key, ':') === count($value))
					);
				}

				if (is_numeric($key) && empty($value)) {
					continue;
				} elseif (is_numeric($key) && is_string($value)) {
					$out[] = $this->_quoteFields($value);
				} elseif ((is_numeric($key) && is_array($value)) || in_array(strtolower(trim($key)), $bool)) {
					if (in_array(strtolower(trim($key)), $bool)) {
						$join = ' ' . strtoupper($key) . ' ';
					} else {
						$key = $join;
					}
					$value = $this->conditionKeysToString($value, $quoteValues, $Model);

					if (strpos($join, 'NOT') !== false) {
						if (strtoupper(trim($key)) === 'NOT') {
							$key = 'AND ' . trim($key);
						}
						$not = 'NOT ';
					}

					if (empty($value)) {
						continue;
					}

					if (empty($value[1])) {
						if ($not) {
							$out[] = $not . '(' . $value[0] . ')';
						} else {
							$out[] = $value[0];
						}
					} else {
						$out[] = '(' . $not . '(' . implode(') ' . strtoupper($key) . ' (', $value) . '))';
					}
				} else {
					if (is_object($value) && isset($value->type)) {
						if ($value->type === 'identifier') {
							$data .= $this->name($key) . ' = ' . $this->name($value->value);
						} elseif ($value->type === 'expression') {
							if (is_numeric($key)) {
								$data .= $value->value;
							} else {
								$data .= $this->name($key) . ' = ' . $value->value;
							}
						}
					} elseif (is_array($value) && !empty($value) && !$valueInsert) {
						$keys = array_keys($value);
						if ($keys === array_values($keys)) {
							$count = count($value);
							if ($count === 1 && !preg_match('/\s+(?:NOT|\!=)$/', $key)) {
								$data = $this->name($key) . ' IN (';
								if ($quoteValues) {
									if ($Model !== null) {
										$columnType = $Model->getColumnType($key);
									}
									$data .= implode(', ', $this->value($value, $columnType));
								}
								$data .= ')';
							} else {
								$data = $this->_parseKey($key, $value, $Model);
							}
						} else {
							$ret = $this->conditionKeysToString($value, $quoteValues, $Model);
							if (count($ret) > 1) {
								$data = '(' . implode(') AND (', $ret) . ')';
							} elseif (isset($ret[0])) {
								$data = $ret[0];
							}
						}
					} elseif (is_numeric($key) && !empty($value)) {
						$data = $this->_quoteFields($value);
					} else {
						$data = $this->_parseKey(trim($key), $value, $Model);
					}

					if ($data) {
						$out[] = $data;
						$data = null;
					}
				}
			}
			return $out;
		}

/**
 * Auxiliary function to quote matches `Model.fields` from a preg_replace_callback call
 *
 * @param string $match matched string
 * @return string quoted string
 */
        protected function _quoteMatchedField($match) {
		if (preg_match('/^\'.*\'$/', $match[0])) {
			// do not quote single quoted expressions
			return $match[0];
		}
                if (is_numeric($match[0])) {
                        return $match[0];
                }
                return $this->name($match[0]);
        }
}
