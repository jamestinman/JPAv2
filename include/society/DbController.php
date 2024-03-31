<?php
namespace Society;
use PDO;
// Society's simplified database connection class
require_once(dirname(__FILE__) . '/essentials.php');
date_default_timezone_set("Europe/London");

class DbController
{
  private $username;
  private $password;
  private $dbName;
  private $server;
  private $startTime;
  
  // Stats
	var $logQueries = 0; // Switch this on to enable performance debugging -2=log all (but don't display) -1=log all, 0=log none, X=log X most expensive
	var $persistent = 1; // Persistent connections open the database once when this class is constructed, only closing once the page finished loading
	var $tolerant = 0;
	var $log = [];
	var $stats = ['numGets' => 0, 'numWrites' => 0, 'numGetsDD' => 0, 'rowsRetrievedDB' => 0, 'minMS' => 0, 'totalMS' => 0];
	// Internal vars

  var $ddSrc = "information_schema";
	var $tables = false; // TABLES cache
	var $columns = false; // COLUMNS cache
	var $subArr = []; // Substitution Array to store keys for cloning functionality - subArr[$keyCol][$oldPK]=$newPK; e.g. subArr['pupils'][123]=>456;
	var $rememberKeysInTable = false; // Set this to a tableName to log cloned keys
	// Connections
	var $mysqli = false;
	var $pdo = false;
	// Cached prepared statements
	var $preparedStmts = []; // SQL is this array's index(!), PDOStatement the content
	// Internal vars
	var $sql = false;
	var $charSet = false; // "utf8mb4";

	// Passing a dbName creates a connection to that database schema
	// Not passing a name assumes that global $this->conn already exists, or will be set later
	function __construct($username, $password, $dbName = null, $server = "localhost", $startSession = false)
	{
		$this->startTime = microtime(true);
		$this->dbName = $dbName;
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
	}

	// Open MySQLi connection (used for raw SQL SELECTS due to superior (associated array) returns over PDO)
	function openMysqli($dbName = false)
	{
		$dbName = ($dbName) ? $dbName : $this->dbName;
		if ($dbName == $this->dbName && $this->mysqli && $this->mysqli->ping()) {
      if (mysqli_stat($this->mysqli)) {
        return true; // Already connected
      }
    }
		$this->closeMysqli(1); // Ensure we are closed / a different connection is closed
		try {
			$this->mysqli = new \mysqli($this->server, $this->username, $this->password, $dbName);
			if ($this->charSet) $this->mysqli->set_charset($this->charSet);
			if ($this->mysqli->connect_errno) {
				$this->close("DB.openMysqli: connection to " . $this->server . ":" . $dbName . " using " . $this->username . " failed (" . $this->mysqli->connect_errno . ") " . $this->mysqli->connect_error);
			}
		} catch (\Exception $e) {
			$this->close("DB.openMysqli DB connection err: " . $e->getMessage());
		}
	}

	// Open PDO connection (used for INSERT/UPDATE due to superior (named parameter) prepared statement support over mysqli)
	function openPDO($dbName = false)
	{
		$dbName = ($dbName) ? $dbName : $this->dbName;
		if ($dbName == $this->dbName && $this->pdo) {
      // Already used previously
      try {
        if ($this->pdo->getAttribute(PDO::ATTR_SERVER_INFO)!='MySQL server has gone away') {
          return true; // Still connected
        }
      } catch(PDOException $e) {
        trace("DB.openPDO:PDO connection is dead:".$e->getMessage());
      }
    }
		try {
			$this->pdo=new PDO("mysql:".(($this->charSet)?"charset=".$this->charSet.";":"")."host=".$this->server.";dbname=".$dbName, $this->username, $this->password,[PDO::ATTR_PERSISTENT => true]);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
		} catch(PDOException $e) {
			trace("DB.openPDO:PDO connection has died:".$e->getMessage());
		}
		if ($this->pdo === null) die("Could not openPDO for " . $dbName);
	}

	function closeMysqli($force = 0)
	{
		if ($this->persistent && !$force) {
			$ret = false;
			return $ret;
		};
		if ($this->mysqli) $this->mysqli->close();
		$this->mysqli = null;
		unset($this->mysqli);
		$this->mysqli = null; // Re-instate
	}

	function closePDO($force = 0)
	{
		if ($this->persistent && !$force) {
			$ret = false;
			return $ret;
		};
		$this->pdo = null;
		unset($this->pdo);
		$this->pdo = null; // Re-instate
	}

	// Close all data connections. Pass a error message if this happens unnaturally
	function close($dieMsg = false)
	{
		$this->closeMysqli(1);
		$this->closePDO(1);
		if ($dieMsg) die($dieMsg);
	}

	// Turn SQL into a unique cache var
	function hashSQL($sql)
	{
		return md5($sql); // md5 seems to be almost as quick as CRC32, but ideally we would use xxHash for non-cryptographic hashes such as this :(
	}

	// Now resides in e.php
	function escSQL($s)
	{
		return escSQL($s);
	}

	// Check SQL starts with the correct command
	function check($sql, $cmd = "SELECT")
	{
		if (!in($cmd, "SELECT,INSERT,UPDATE,DELETE")) return false;
		if (strtoupper(substr($sql, 0, 6)) == "SELECT") return true;
		$sql = trim($sql);
		$posSpace = strpos($sql, ' ');
		if (strpos($sql, '--')) {
			error_log("SQL injection from " . getIp() . "? " . $sql);
			$ret = false;
			return $ret;
		}
		if (strpos($sql, '/*')) {
			if (strpos($sql, '*/')) {
				error_log("SQL injection from " . getIp() . "? " . $sql);
				$ret = false;
				return $ret;
			}
		}
		$firstCmd = strtoupper(substr($sql, $posSpace - strlen($cmd), strlen($cmd)));
		if ($firstCmd == "SELECT") {
			$this->sql = $sql;
			return true;
		}
		backtrace("Cannot execute non-select cmd=[" . $firstCmd . "] SQL (" . $sql . ")");
		return false;
	}

	// Log stats in memory
	function log($funcName, $sql, $numRows, $tsStart)
	{
		if (!$this->logQueries) return false;
		$ms = round((microtime(true) - $tsStart) * 1000, 3);
		$this->stats[$funcName] = (isset($this->stats[$funcName])) ? $this->stats[$funcName] + 1 : 1;
		if (in($funcName, "GetAll,GetArr,GetRow,GetKeyRow")) {
			$this->stats['numGets']++;
			$this->stats['rowsRetrievedDB'] += $numRows;
		}
		if (in($funcName, "execute,doInsert,doUpsert,doUpdate")) $this->stats['numWrites']++;
		$this->stats['totalMS'] += $ms;
		if (!$this->logQueries) return false;
		if ($this->logQueries < 0 || $ms > $this->stats['minMS']) {
			// Add me to to the mostExpensive list
			$this->log[$ms . "ms"] = [
				'funcName' => $funcName,
				'sql' => $sql,
				'numRows' => $numRows,
				'ms' => $ms
			];
			if ($this->logQueries > 0 && safeCount($this->log) > $this->logQueries) {
				unset($this->log[$this->stats['minMS'] . "ms"]); // Cull the least expensive
				$this->stats['minMS'] = false; // Find a new least expensive
				foreach ($this->log as $i => $tmp) {
					if (!$this->stats['minMS'] || $tmp['ms'] < $this->stats['minMS']) $this->stats['minMS'] = $tmp['ms'];
				}
			}
		}
	}

	private function getType($var)
	{
		if (is_string($var)) return 's';
		if (is_float($var)) return 'd';
		if (is_int($var)) return 'i';
		return 'b';
	}

	// Get... functions accept either plain SQL string, or an array to use prepared stmts
	// e.g. these are equivalent:
	//   $DB->getRow("SELECT * FROM pupils WHERE foreignKeyID=1 AND surname='LEEDS'");
	//   $DB->getRow("SELECT * FROM pupils WHERE foreignKeyID=? AND surname=?", 1, 'LEEDS');
	// Note: MySQLi is used for SELECTs so data cannot be given as an associative array - this may change when MySQLi can handle this, or PDO is upgraded to handle _returning_ associative arrays
  /**
   * @param $sql
   * @param array $namedParameters
   * @return bool|array
   */
  function getRow($sql, $namedParameters = []): bool|array
  {
		if (!$this->check($sql)) {
			$ret = false;
			return $ret;
		};
		$numParams = func_num_args();
		if ($numParams == 1) {
			// Use MySQLi for complete SQL query
			$this->openMysqli();
			$res = $this->mysqli->query($sql);
			if (!$res) {
				backtrace("DB.getRow:" . $this->mysqli->error . " [" . $sql . "]",1);
				return false;
			}
			if ($res->num_rows === 0) {
				$ret = false;
				return $ret;
			}
			if ($this->logQueries) $this->log('GetRow', $sql, $res->num_rows, $ts);
			$res->data_seek(0); // Not needed?
			$data = $res->fetch_array(MYSQLI_ASSOC);
			$res->free();
			$this->closeMysqli();
		} else {
			// Kill the first argument (the sql)
			$args = array_slice(func_get_args(), 1);
			$types = '';
			$args_ref = array();
			foreach ($args as $k => &$arg) {
				if (is_array($args[$k])) {
					foreach ($args[$k] as $j => &$a) {
						$types .= $this->getType($args[$k][$j]);
						$args_ref[] = &$a;
					}
				} else {
					$types .= $this->getType($args[$k]);
					$args_ref[] = &$arg;
				}
			}
			$ts = microtime(true);
			// Use PDO for named/numbered parameters
			$this->openPDO();
			$stmt = $this->prepare($sql, false, $namedParameters);
			if (!$stmt) {
				$this->closePDO();
				$ret = false;
				return $ret;
			}
			try {
				$ok = $stmt->execute();
			} catch (Exception $e) {
				trace("DB.GetRow PDO err: " . $e->getMessage());
				$this->closePDO();
				$ret = false;
				return $ret;
			}
			$indexType = 1;
			if (!$ok) {
				$data = false;
			} else if ($indexType == 1) {
				$data = $stmt->fetch(PDO::FETCH_ASSOC);
			} else if ($indexType == 2) {
				$data = $stmt->fetch(PDO::FETCH_NUM);
			} else {
				$data = $stmt->fetch(PDO::FETCH_BOTH);
			}
			if ($this->logQueries) $this->log('GetRow', $sql, safeCount($data), $ts);
			$this->closePDO();
		}
		return $data;
	}

  // Return a row from a table given it's primary key
  function getByID($table, $id) {
    $keyCol = $this->getKeyCol($table);
    return $this->getRow("SELECT * FROM ".$table." WHERE ".$keyCol."=:".$keyCol, [$keyCol=>$id]);
  }

	function getMentionedTables($sql)
	{
		$command = substr($sql, 0, strpos($sql, " "));
		$tableArr = [];
		if ($command == "SELECT") {
			$a = strpos($sql, " FROM ");
			$b = strpos($sql, " WHERE ");
			if (!$a) return false;
			$a += 6;
			$b -= $a;
			$tables = substr($sql, $a, $b);
			foreach (explode(',', $tables) as $chunk) {
				$chunk = trim($chunk);
				$table = substr($chunk, 0, strpos($chunk, ' '));
				if (!$table) $table = $chunk;
				$alias = ($table != $chunk) ? str_replace($table . " ", "", $chunk) : $table;
				$tableArr[$alias] = $table;
			}
		}
		return $tableArr;
	}

	// Retrieve all results from the given SQL query as a standard 0-based array
	// Index types: 0/false=named indices, 1=numbered indicies, 2=both
	// Returns empty [] if nothing found to allow foreach ($DB->getAll() as $blah) loops without erroring
	function getAll($sql, $namedParameters = false, $indexType = 0)
	{
		if ($namedParameters !== false && !is_array($namedParameters)) {
			if (isNum($namedParameters) || $namedParameters === 0) {
				$indexType = $namedParameters;
				$namedParameters = false;
			} // legacy parameter ordering
		}
		$data = [];
		if (!$this->check($sql)) return $data;
		$ts = microtime(true);
		if (!$namedParameters) {
			// Use MySQLi
			$this->openMysqli();
			$res = $this->mysqli->query($sql);
			if (!$res) {
				$msg = "DB.GetAll:" . $this->mysqli->error . " [" . $sql . "]";
				error_log($msg);
				trace($msg);
				return $data;
			}
			if ($res->num_rows === 0) return $data;
			if ($this->logQueries) $this->log('GetAll', $sql, $res->num_rows, $ts);
			if (!$indexType) {
				$data = $res->fetch_all(MYSQLI_ASSOC);
			} else if ($indexType == 2) {
				$data = $res->fetch_all(MYSQLI_NUM);
			} else {
				$data = $res->fetch_all(MYSQLI_BOTH);
			}
			$res->free();
			$this->closeMysqli();
		} else {
			// Use PDO for named/numbered parameters
			$this->openPDO();
			$stmt = $this->prepare($sql);

			if (!$stmt) {
				$this->closePDO();
				$ret = false;
				return $ret;
			}
			try {

				$ok = $stmt->execute($namedParameters);
			} catch (Exception $e) {
				trace("DB.GetAll PDO err: " . $e->getMessage());
				$this->closePDO();
				$ret = false;
				return $ret;
			}
			if (!$ok) {
				$data = false;
			} else if ($indexType == 2) {
				$data = $stmt->fetchAll(PDO::FETCH_BOTH);
			} else if ($indexType == 1) {
				$data = $stmt->fetchAll(PDO::FETCH_NUM);
			} else {
				$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
			}
			if ($this->logQueries) $this->log('GetAll', $sql, safeCount($data), $ts);
			$this->closePDO();
		}
		return $data;
	}

	function getOne($sql, $namedParameters = false)
	{
		$row = $this->getRow($sql, $namedParameters);
		if (!$row) {
			$ret = false;
			return $ret;
		}
		foreach ($row as $i => $d) {
			return $d;
		}
	}

	// Like GetAll, but returns associative array indexed on $indexCol (or the first col)
	// If the indexCol has non-unique entries, later duplicate keys overwrite earlier ones
	// You can get all non-unique records as sub-arrays by setting $arrayStructure = 1
	function getArr($sql, $indexCol = false, $arrayStructure = 0)
	{
		startFunc();
		if (!$this->check($sql)) {
			$ret = false;
			return $ret;
		}
		if ($this->logQueries) $ts = microtime(true);
		$count = 0;
		$data = [];
		$indexes = [];
		$keyCol = false;
		$this->openMysqli();
		$res = $this->mysqli->query($sql);
		if (!$res) {
			trace("DB.GetArr:" . $this->mysqli->error . " [" . $sql . "]");
			return $data;
		}
		if ($res->num_rows === 0) {
			$ret = false;
			return $ret;
		}
		if ($arrayStructure <= 0) {
			$data = $res->fetch_all(MYSQLI_ASSOC);
			// Examine the first record returned
			$firstRecord = $data[0];
			if (!$indexCol) {
				$indexCol = getFirstKey($firstRecord);
			}
			$dataCol = NULL;
			if ($arrayStructure == -1) {
				// Leave the sub-arrays alone
			} else {
				// Helpfully return a single indexed value if only 1 or 2 columns are selected
				$depth = ($firstRecord) ? sizeOf($firstRecord) : 0;
				if ($depth == 1) {
					$dataCol = $indexCol;
				} else if ($depth == 2) {
					$dataCol = getFirstKey($firstRecord, 0, 2);
				}
				// Use PHP's built in array_column for speed whenever possible
				$data = array_column($data, $dataCol, $indexCol);
				$count = ($data) ? sizeOf($data) : 0;
			}
		} else {
			// Construct sub-arrays of matching records
			while ($r1 = $res->fetch_array()) {
				if (sizeOf($r1) > 0) {
					$i = ($indexCol && isset($r1[$indexCol])) ? $r1[$indexCol] : $r1[0];
					// Helpfully return a single indexed value if only 1 or 2 columns are selected
					if (sizeOf($r1) == 2) { // Single value (see i. above)
						$row = $r1[0];
					} else if (sizeOf($r1) == 3) { // 2 col special cases (see ii. & iii. above)
						$row = $r1[1];
					} else if ($arrayStructure != 1 && sizeOf($r1) == 4) { // Standard 2 col case
						$row = $r1[1];
					} else {
						// For all other cases create a keyed array, e.g. arr[key]=>array(colName=>value,colName=>value, etc.)
						$n = 0;
						foreach ($r1 as $col => $val) {
							// ignore the numbered indexes (only process named columns)
							if (!(isnum($col))) {
								if (!$keyCol && notnull($col)) $keyCol = $col; // Remember the first column (regard as the PK)
								$row[$col] = $val;
							}
							$n++;
						}
					}
					if (!isset($data[$i])) $data[$i] = [];
					$data[$i][$row[$keyCol]] = $row; // The first entry
					$count++;
				}
			}
		}
		if ($this->logQueries) $this->log('GetArr', $sql, $res->num_rows, $ts);
		$res->free();
		$this->closeMysqli();
		endFunc();
		if ($count == 0) {
			$ret = false;
			return $ret;
		}
		return $data;
	}

	// Returns every value of one column from the given SQL as a comma separated string
	function getKeys($sql, $col = 0, $outputAsArray = false)
	{
		return crushOut($this->getAll($sql, false, 2), $col, $outputAsArray);
	}

	// Takes a column definition and formats a value for it
	function formatForColDef($val, $colDef, $quoteStrings = false)
	{
		if ((is_array($val) && sizeOf($val) == 0) || isnull($val)) return null;
		$colType = $colDef['DATA_TYPE'];
		if (in($colType, "char,longtext,mediumtext,text,varchar,date,datetime,timestamp")) {
			// if (substr($val,0,1)=="'" && substr($val,strlen($val)-2,1)=="'") $val=substr($val,1,strlen($val)-2);
			if ($colType != "longtext") {
				$val = (in($colType, "date,datetime,timestamp")) ? convertDate($val, "-") : substr($val, 0, $colDef['CHARACTER_MAXIMUM_LENGTH']);
			}
			return ($quoteStrings) ? fss($val) : $val;
		} else if (in($colType, "int,bigint,decimal,double,mediumint,smallint,tinyint")) {
			$val = castAsNum($val);
		}
		return $val;
	}

	// Coder-friendly version of formatForColType, accepting table and column name
	function formatForCol($val, $table, $colName, $quoteStrings = false)
	{
		return $this->formatForColDef($val, $this->getColFromDD($table, $colName), $quoteStrings);
	}

	// Format an array of key=>value pairs depending on column definition, stripping out weirdos
	function formatPairs($table, $arr, $skipNulls = false)
	{
		$pairs = [];
		$cols = $this->getColsFromDD($table);
		if (!$cols) return false;
		foreach ($cols as $colDef) { // Note: loop over table cols (rather than passed data) to shed crap columns
			$col = $colDef['COLUMN_NAME'];
			$val = (isset($arr[$col])) ? $arr[$col] : false;
			if ($val !== false && (!$skipNulls || notnull($val) || $val === 0)) {
				$pairs[$col] = $this->formatForColDef($val, $colDef);
			}
		}
		return $pairs;
	}

	function prepare($sql, $tables = false, $data = false)
	{ // Pass in a prepared statement. Will re-use an existing PDOStatement if already used this session
		$this->sql = $sql;
		/* WARNING! PDO Caching does not appear to work - the lastInsertId() routine does not always correctly update when re-using stmts
		if (isset($this->preparedStmts[$sql])) return $this->preparedStmts[$sql];
		*/
		try {
			$stmt = $this->pdo->prepare($sql);
		} catch(PDOException $e) {
			trace("DB.prepare:PDO exception:".$e->getMessage());
      return false;
		} catch (Exception $e) {
			trace("DB.prepare PDO err: " . $e->getMessage() . " using [" . $sql . "]");
			return false;
		}
		if (is_array($data)) {
			// Bind params
			if (!$tables) $tables = $this->getMentionedTables($sql);
			if (!is_array($tables)) $tables = [$tables => $tables];
			$count = 0;
			foreach ($data as $col => $val) {
				// Check column exists
				$colDef = $this->getColFromDD($tables, $col);
				if ($colDef) {
					$table = $colDef['table'];
					if (isnull($val)) {
						$stmt->bindValue(':' . $col, NULL, PDO::PARAM_NULL);
					} else if (in($colDef['DATA_TYPE'], "int,bigint,mediumint,smallint,tinyint")) {
						$stmt->bindValue(':' . $col, (int)$val, PDO::PARAM_INT);
					} else {
						//            echo "binding table:".$table." string col:".$col." to val:".$val." for SQL:".$sql;
						$stmt->bindValue($col, $val, PDO::PARAM_STR);
					}
					$count++;
				}
			}
		}
		// $this->preparedStmts[$sql]=$stmt; // Cache me if you can
		return $stmt;
	}

	// Update one specific row. Upgraded Aug 2013 to PDO prepared statements
	function doUpdate($table, $pairs, $keyVal = false, $keyCol = false)
	{
		if (!$keyCol) $keyCol = $this->getKeyCol($table);
		if (!$keyVal) $keyVal = (isset($pairs[$keyCol])) ? $pairs[$keyCol] : false;
		if (!$keyVal) return false;
		if (!$pairs) return false; // Nothing to update
		$ts = microtime(true);
		$keyVal = (int)$keyVal;
		// Build prepared statement
		$comma = "";
		$sql = "UPDATE " . $table . " SET ";
		foreach ($pairs as $col => $val) {
			if ($col != $keyCol) {
				$colDef = $this->getColFromDD($table, $col); // Check column exists
				if ($colDef) {
					$sql .= $comma . $col . "=:" . $col;
					$comma = ",";
				} else {
					// trace("No colDef for ".$table.".".$col);
				}
			}
		}
		$sql .= " WHERE " . $keyCol . "=:" . $keyCol;
		$this->openPDO();
		$stmt = $this->prepare($sql);
		if (!$stmt) {
			trace("DB.doUpdate: Failed to create PDO stmt on " . $table . " [" . $sql . "]!");
			$this->closePDO();
			return false;
		}
		// Bind data
		foreach ($pairs as $col => $val) {
			if ($col != $keyCol) {
				$colDef = $this->getColFromDD($table, $col); // Check column exists
				if ($colDef) {
					if (isnull($val)) {
						$stmt->bindValue(':' . $col, $val, PDO::PARAM_NULL);
					} else if (in($colDef['DATA_TYPE'], "int,bigint,mediumint,smallint,tinyint")) {
						$stmt->bindValue(':' . $col, (int)$val, PDO::PARAM_INT);
					} else {
						$stmt->bindValue(':' . $col, $val, PDO::PARAM_STR);
					}
				}
			}
		}
		$stmt->bindValue(':' . $keyCol, (int)$keyVal, PDO::PARAM_INT);
		if (!$stmt) {
			$this->closePDO();
			return false;
		}
		try {
			$ok = $stmt->execute();
		} catch (Exception $e) {
			$msg = "DB.doUpdate(" . $table . ") PDO err: " . $e->getMessage();
			if ($this->tolerant) {
				e($msg);
			} else {
				$this->close($msg);
			}
		}
		if ($this->logQueries) $this->log('doUpdate', $sql, 0, $ts);
		$this->closePDO();
		return $keyVal;
	}

	// NB: Use massInsert to optimise PDO inserts by preparing/executing 1 large(r) insert statement
	function doInsert($table, $valuesArr = false, $allowPK = false, $massInsert = false, $onDuplicateKey = false)
	{
		if (!is_array($valuesArr) || sizeOf($valuesArr) < 1) return false;
		$keyCol = $this->getKeyCol($table);
		if (!isset($this->columns[$table])) $this->loadColumns($table);
		$colDefs = (isset($this->columns[$table])) ? $this->columns[$table]['iCols'] : false;
		// Grab the valid table columns
		if (!$colDefs) return false;
		$ts = microtime(true);
		// Build up SQL and check columns using first entry from values array
		if (!$massInsert) $valuesArr = [1 => $valuesArr];
		foreach ($valuesArr as $subArr) {
			break;
		}
		if (!is_array($subArr)) return false;
		$sqlA = $sqlB = $questionMarks = $comma = false;
		$cols = [];
		$i = 0;
		foreach ($subArr as $col => $val) {
			if (($col != $keyCol || $allowPK) && isset($colDefs[$col])) {
				$cols[$i++] = $col;
				$sqlA .= (($sqlA) ? "," : "") . $col;
				$questionMarks .= (($questionMarks) ? "," : "") . "?";
			}
		}
		$numRows = sizeOf($valuesArr);
		// Need a block of (?,?,?) question marks for each data row
		for ($n = 0; $n < $numRows; $n++) {
			$sqlB .= (($sqlB) ? "," : "") . "(" . $questionMarks . ")";
		}
		// Build prepared statement
		$sql = "INSERT INTO " . $table . "(" . $sqlA . ") VALUES " . $sqlB;
		if ($onDuplicateKey) { // genius mysql functionality updating on a primary key hit
			$sql .= " ON DUPLICATE KEY UPDATE ";
			if ($onDuplicateKey == 1) $onDuplicateKey = crushOut($valuesArr, -1, true);
			foreach ($onDuplicateKey as $colName) {
				$sql .= $comma . $colName . "= VALUES(" . $colName . ")";
				$comma = ",";
			}
		}
		$this->openPDO();
		$stmt = $this->prepare($sql); // Re-use previous prepared statements if pos
		if (!$stmt) {
			trace("DB.doInsert: Failed to create PDO stmt on " . $table . " [" . $sql . "]!");
			$this->closePDO();
			return false;
		}
		// Bind params
		$numRows = 0;
		$count = 0;
		foreach ($valuesArr as $pairs) {
			foreach ($cols as $col) {
				$count++;
				$val = (isset($pairs[$col])) ? $pairs[$col] : null;
				if (isnull($val)) {
					$stmt->bindValue($count, NULL, PDO::PARAM_NULL);
				} else if (in($colDefs[$col]['DATA_TYPE'], "int,bigint,mediumint,smallint,tinyint")) {
					$stmt->bindValue($count, (int)$val, PDO::PARAM_INT);
				} else {
					$stmt->bindValue($count, $val, PDO::PARAM_STR);
				}
			}
			$numRows++;
		}
		$ok = false;
		if (!$stmt) {
			trace("Failed to create PDO stmt on " . $table . " [" . $sql . "]!");
			$this->closePDO();
			return false;
		}
		try {
			$ok = $stmt->execute();
		} catch (Exception $e) {
			$msg = "DB.doInsert(" . $table . ") PDO err: " . $e->getMessage() . " " . getTiming();
			if ($this->tolerant) {
				e($msg);
			} else {
				$this->close($msg);
			}
			// Incorrect string value gets caught here... so simply bail (but keep processing)
			return false;
		}
		if (!$ok) {
			$this->close("Failed to execute stmt for [" . $sql . "]");
		}
		$id = $this->pdo->lastInsertId();
		if (!($id)) {
			// Weird bug where pdo occasionally returns 0 here even though record created
			$id = $this->retrieveKey($table, $pairs, $keyCol, 'MAX');
			if (!$id) {
				$msg = "PDO failed to retrieve lastInsertId, as did retrieveKey() for " . $table . ".";
				if ($this->tolerant) {
					e($msg);
				} else {
					$this->close($msg);
					return false;
				}
			}
		}
		$this->closePDO();
		if ($this->logQueries) $this->log('doInsert', $sql, $numRows, $ts);
		return $id;
	}

	// Update / Insert one specific row, creating it if none matches the where clause
	function doUpsert($table, $pairs, $where, $unique = false)
	{
		$keyCol = $this->getKeyCol($table);
		// Look for an existing match on $where
		if ($keyVals = $this->getAll(where("SELECT " . $keyCol . " FROM " . $table, $where), 0)) {
			$keyVal = ($unique && sizeOf($keyVals) > 1) ? $this->stripToOneRow($table, $where, $keyVals[0][$keyCol]) : $keyVals[0][$keyCol];
			$success = $this->doUpdate($table, $pairs, (int)$keyVal, $keyCol);
			if (!$success) return false; // if failed, don't report success
		} else {
			// Extract pairs from where to also include in the insert
			$wherePairs = getPairs($where, " AND ", "=", " IS ", "'");
			$pairs = mergeOptions($wherePairs, $pairs); // If a col appears in both the data (head) and the where, use the data value for the insert
			$pairs = $this->formatPairs($table, $pairs);
			$keyVal = $this->doInsert($table, $pairs);
		}
		return $keyVal;
	}

	// Delete specific row using prepared statement
	function doDelete($table, $keyVal, $keyCol = false)
	{
		$ts = microtime(true);
		$keyCol = (!$keyCol ? $this->getKeyCol($table) : $keyCol);
		$sql = "DELETE FROM " . $table . " WHERE " . $keyCol . "=:" . $keyCol;
		$this->openPDO();
		$stmt = $this->prepare($sql);
		$this->stats['numWrites'] += $stmt->execute([':' . $keyCol => (int)$keyVal]);
		$this->closePDO();
		if ($this->logQueries) $this->log('doDelete', $sql, 0, $ts);
		return $keyVal;
	}

	// Searches for a key in table that matches the values given in the key=>value array
	function search($table, $arr, $skipNulls = false, $minMax = 'MIN')
	{
		$keyCol = $this->getKeyCol($table);
		if (nvl(getIfSet($arr, $keyCol), 0) > 0) {
			return $arr[$keyCol];
		} // Primary key in arr
		$pairs = $this->formatPairs($table, $arr, $skipNulls);
		if (!$pairs) return false;
		$keyVal = $this->retrieveKey($table, $pairs, $keyCol, $minMax);
		return $keyVal;
	}

	// Inserts a table row with information from arr, or updates if PK included
	// Ignores data with no corresponding column (unlike using doUpdate/doInsert directly)
	// Returns primary key
	function writeArray($table, $arr, $skipNulls = false)
	{
		$keyCol = $this->getKeyCol($table);
		$keyVal = (isset($arr[$keyCol])) ? (int)$arr[$keyCol] : 0;
		// Build up an array of formatted pairs
		$pairs = $this->formatPairs($table, $arr, $skipNulls);
		return ($keyVal == 0) ? $this->doInsert($table, $pairs) : $this->doUpdate($table, $pairs, $keyVal, $keyCol);
	}

	// Returns true if two arrays are regarded as the same record. If matchFields / ignoreFields are empty all fields in $a1 are matched
	function compare($a1, $a2, $matchFields = false, $ignoreFields = false)
	{
		if (!($matchFields)) $matchFields = crushOut($a1, -1);
		$matchFields = iExplode($matchFields);
		if ($ignoreFields) {
			foreach (explode(',', $ignoreFields) as $f) {
				unset($matchFields[$f]);
			}
		}
		$colMissing = false;
		$where = "";
		$and = "";
		foreach ($matchFields as $f) {
			// This continue persists until it does NOT match (returning false)
			// If neither exist that's a match
			if (isset($a1[$f]) || isset($a2[$f])) {
				// If either exist and the other does not, return false
				if (!isset($a1[$f]) || !isset($a2[$f])) return false;
				// Similarly, both null is fine
				if (notnull($a1[$f]) || notnull($a2[$f])) {
					// ... but one null and one not is returned false
					if (isnull($a1[$f]) || isnull($a2[$f])) return false;
					// Check the values
					if ($a1[$f] != $a2[$f]) return false;
				}
			}
			// If we fall out here we've matched so far...
		}
		return true;
	}

	// Retrieve key e.g. for the row we just inserted
	function retrieveKey($table, $pairs, $keyCol = false, $minMax = 'MAX')
	{
		if (!$keyCol) {
			$keyCol = $this->getKeyCol($table);
		}
		// if (isset($pairs[$keyCol])) return $pairs[$keyCol];
		$comma = "";
		$and = "";
		$data = [];
		$sql = "SELECT " . $minMax . "(" . $keyCol . ") FROM " . $table . " WHERE ";
		foreach ($pairs as $col => $val) {
			$colDef = $this->getColFromDD($table, $col);
			if ($col != $keyCol && $val !== false && $val !== null) {
				// Don't include null strings, as these may have defaults set (particularly dates, which will be on the db as 0000-00-00 00:00:00)
				if ($val === 'NULL') {
					$sql .= $and . $col . " IS NULL";
				} else {
					$sql .= $and . $col . "=" . $this->formatForColDef($val, $colDef, true);
					$data[$col] = $val;
				}
				$and = " AND ";
			}
		}
		return $this->getOne($sql);
	}

	// Raw SQL command execute
	function execute($sql, $data = false, $justPushOnRegardless = false)
	{
		$id = $res = false;
		$ts = microtime(true);
		$command = substr($sql, 0, 6);
		// Prepared stmt
		$this->openPDO();
		if ($data) {
			$stmt = $this->prepare($sql);
			if (!$stmt) {
				trace("DB.execute prepare returned no stmt for [" . $sql . "]");
				$ret = false;
				return $ret;
			}
			try {
				$id = $stmt->execute($data);
			} catch (Exception $e) {
				$msg = "DB.execute PDO prep.stmt err: " . $e->getMessage();
				error_log($msg);
				trace($msg);
				$this->closePDO();
				$ret = false;
				return $ret;
			}
		} else {
			// Standard execute
			try {
				$res = $this->pdo->query($sql);
			} catch (PDOException $e) {
				$msg = "DB.execute: PDO failed with " . $e->getMessage() . " SQL=[" . $sql . "]";
				error_log($msg);
				trace($msg);
				if (!$justPushOnRegardless) {
					$this->close($msg);
				}
				$ret = false;
				return $ret;
			}
		}
		if ($command == "INSERT") {
			$id = $this->pdo->lastInsertId();
		} else if ($res) {
			$id = $res;
		}
		$this->closePDO();
		if ($this->logQueries) {
			$this->log('execute', $sql, 0, $ts);
		}
		return $id;
	}

	// CLONING ROUTINES
	// ----------------

	// fixedOverride of -1 causes lookup of a previously created substitute key. Pass a fixedOverride to always blast same value
	function overrideVal($colName, $val, $fixedOverride = false)
	{
		// Look for a replacement key... $val represents the old foreign key
		if ($fixedOverride !== false && $fixedOverride != -1) return $fixedOverride;
		if ($val === 0) return 0;
		$newKeyVal = $this->getClonedKey($colName, $val);
		if ($newKeyVal !== false) return $newKeyVal;
		return $val; // Note, foreign key is left the same if there is no substitute (often these keys are globally allowed, such as core subjectIDs)
	}

	// If a row has previously been cloned, this returns the replacement value
	function getClonedKey($keyCol, $oldKeyVal)
	{
		if (!isset($this->subArr[$keyCol])) return false;
		return (isset($this->subArr[$keyCol][$oldKeyVal])) ? $this->subArr[$keyCol][$oldKeyVal] : false;
	}

	// Updates the foreign keys on a table with new keys following a clone operation, particularly useful for circular references
	// e.g. if u clone pupils, then pupilPhotos, but pupils ITSELF has a pupilPhotoID on it, you need to call updateClonedKeys('pupils','schoolID=1',array("pupilPhotoID"=>-1));
	function updateClonedKeys($table, $overrides, $newWhere = "1=1")
	{
		$keyCol = $this->getKeyCol($table);
		foreach ($this->getAll("SELECT * FROM " . $table . " WHERE " . $newWhere) as $row) {
			$pairs = [];
			foreach ($overrides as $fkCol => $override) {
				if (isset($row[$fkCol])) {
					$pairs[$fkCol] = $this->overrideVal($fkCol, $row[$fkCol], $override);
				}
			}
			if (sizeOf($pairs) > 0) {
				$this->doUpdate($table, $pairs, $row[$keyCol], $keyCol); // Update all overrides on this row at once
			}
		}
	}

	// Clones a row on table, returning the new primary key
	function cloneRow($table, $pkOrRow, $overrides = null, $rememberKeys = true)
	{
		$keyCol = $this->getKeyCol($table);
		$oldPK = (is_array($pkOrRow)) ? $pkOrRow[$keyCol] : $pkOrRow;
		// Have we already cloned this key?
		$newPK = $this->getClonedKey($keyCol, $oldPK);
		if ($newPK) return $newPK;
		$src = (is_array($pkOrRow)) ? $pkOrRow : $this->getRow("SELECT * FROM " . $table . " WHERE " . $keyCol . "=" . $pkOrRow, 1);
		if (!$src) {
			trace("Cannot clone " . $table . ":" . $keyCol . "=" . $pkOrRow . " - row does not exist on " . $this->dbName);
			die;
			return 0;
		}
		$dest = [];
		foreach ($src as $colName => $val) {
			// Should the value of this col be substituted?
			if (isset($overrides[$colName])) {
				$dest[$colName] = $this->overrideVal($colName, $val, $overrides[$colName]);
			} else {
				$dest[$colName] = $val; // Straight copy
			}
		}
		$dest[$keyCol] = 0; // Force write[] to do an insert
		$newPK = $this->writeArray($table, $dest);
		if ($rememberKeys) {
			$this->rememberKey($keyCol, $oldPK, $newPK);
		}
		// Include a "clonedFromID" column on your table to automatically record original
		$cloneCol = 'clonedFrom' . strToUpper(substr($keyCol, 0, 1)) . substr($keyCol, 1, strlen($keyCol) - 1);
		if (isset($src[$cloneCol])) $this->doUpdate($table, [$cloneCol => $oldPK], $newPK);
		return $newPK;
	}

	function rememberKey($keyCol, $oldPK, $newPK)
	{
		// ->subArr contains the new mappings for old foreign keys (i.e. from previous calls to cloneRows on different tables)
		if (!isset($this->subArr[$keyCol])) $this->subArr[$keyCol] = [];
		$this->subArr[$keyCol][$oldPK] = $newPK;
		// Also store in DB log?
		if ($this->rememberKeysInTable) {
			$this->doInsert($this->rememberKeysInTable, ['keyCol' => $keyCol, 'oldPK' => $oldPK, 'newPK' => $newPK]);
		}
	}

	// Like cloneRow, but takes multiple rows (using the given where clause) and clones them all, returning an array of newKey=>oldKey mappings
	// 'overrides' allows values to be tweaked, e.g. ["status"=>"NEW"]
	// or setting to 'true' for a foreign key e.g. ["subjectID"=>true] causes the value to be substituted from this->subArr, probably remembered from an earlier call to cloneRows()
	// e.g. cloneRows("results","pupilID IN (SELECT pupilID FROM pupils WHERE schoolID=1)",["schoolID"=>1,"subjectID"=>true,"testID"=>true]);
	// Set rememberKeys=false for leaf nodes to save memory (the leaves generally being the largest consumers...)
	function cloneRows($table, $oldWhere, $overrides = false, $rememberKeys = true, $trace = false)
	{
		$keyCol = $this->getKeyCol($table);
		if ($rememberKeys && !isset($this->subArr[$keyCol])) {
			$this->subArr[$keyCol] = []; // Create the sub array, even if it transpires no keys are found, so at least we know this table has been processed
		}
		$count = 0;
		$oldToNewPKs = [];
		$sql = "SELECT * FROM " . $table . " WHERE " . $oldWhere;
		foreach ($this->getAll($sql) as $row) {
			$oldToNewPKs[$row[$keyCol]] = $this->cloneRow($table, $row, $overrides, $rememberKeys); // Remember the keys from this pass for ourselves, so we can update parents if necessary
			$count++;
		}
		// Automatically update self-referential cols of format parentKeyID e.g. parentPageID for keyCol pageID
		$parentCol = "parent" . initCap($keyCol, false);
		$parent = $this->getColFromDD($table, $parentCol);
		if ($parent) {
			foreach ($this->getAll($sql) as $row) { // Re-loop over OLD rows
				$oldRowID = $row[$keyCol];
				$oldParentID = $row[$parentCol];
				if ($oldParentID > 0) {
					$newRowID = (isset($oldToNewPKs[$oldRowID])) ? $oldToNewPKs[$oldRowID] : 0;
					$newParentID = (isset($oldToNewPKs[$oldParentID])) ? $oldToNewPKs[$oldParentID] : 0;
					if ($newRowID && $newParentID) {
						$this->execute("UPDATE " . $table . " SET " . $parentCol . "=" . $newParentID . " WHERE " . $keyCol . "=" . $newRowID);
					}
				}
			}
		}
		if ($trace) {
			trace($count . " rows cloned." . (($rememberKeys) ? " Remembered " . safeCount($this->subArr[$keyCol]) . " " . $keyCol . "s" : ""));
		}
		return $oldToNewPKs;
	}

	// Update ALL tables featuring the key given to a new value. CAN BE SLOW if foreign key is not indexed on all tables that feature it
	function swapKey($colName, $originalVal, $newVal)
	{
		$count = 0;
		if (!$colName || !$originalVal || !$newVal) return false;
		foreach ($this->getTablesFromDD() as $table) {
			$col = $this->getColFromDD($table['TABLE_NAME'], $colName);
			if ($col && $col['COLUMN_KEY'] != 'PRI') { // Don't attempt to update primary keys!
				// Do the switch...
				$this->execute("UPDATE " . $table['TABLE_NAME'] . " SET " . $colName . "=" . $newVal . " WHERE " . $colName . "=" . $originalVal);
				$count++;
			}
		}
		return $count;
	}

	// ------------------
	// ORDERING FUNCTIONS
	// ------------------
	function getOrderingOptions($table, $CWC, $curOrdering = false)
	{
		$options = [];
		foreach ($this->getAll(where("SELECT * FROM " . $table . " ORDER BY ordering", $CWC)) as $r1) {
			$options[$r1['ordering']] = ((!$curOrdering || $curOrdering > $r1['ordering']) ? "before" : (($curOrdering < $r1['ordering']) ? "after" : "&rarr; ")) . " " . getIfSet($r1, 'title', ' at ' . $r1['ordering']);
		}
		return $options;
	}

	// Reorder the 'ordering' column of a table, creating a space at leaveSpaceAt so that a new row can be slotted in (ignoreID=0) or an existing row re-ordered (ignoreID=id of existing row)
	function reOrdering($table, $CWC, $leaveSpaceAt = 0, $ignoreID = 0, $alsoUpdate = false)
	{
		$keyCol = $this->getKeyCol($table);
		$curOrdering = 0; // Loop over _all_ rows (in current order)
		foreach ($this->getAll(where("SELECT " . $keyCol . ",ordering FROM " . $table . " ORDER BY ordering", $CWC)) as $r1) {
			if (++$curOrdering == $leaveSpaceAt) $curOrdering++;
			if ($r1["ordering"] != $curOrdering) $this->execute("UPDATE " . $table . " SET ordering=" . $curOrdering . " WHERE " . $keyCol . "=" . $r1[$keyCol]);
		}
		if ($alsoUpdate) $this->execute("UPDATE " . $table . " SET ordering=" . $leaveSpaceAt . " WHERE " . $keyCol . "=" . $ignoreID);
	}
/*
  // Alternative reordering function from essentials.php
  // Moves the ordering of a row in any table with an ORDERING column
  // whichWay of -1 moves it up one (closer to 0), 1 moves it down one (closer to maxOrdering)
  function moveOrdering($table,$keyCol,$keyVal,$whichWay,$CWC) {
    $curOrdering=$this->DB->GetOne("SELECT ordering FROM ".$table." WHERE ".$keyCol."=".$keyVal);
    $maxOrdering=$this->DB->GetOne(where("SELECT MAX(ordering) FROM ".$table,$CWC));
    if ($whichWay==1) {
      $newOrdering=($curOrdering==$maxOrdering)?1:$curOrdering+1;
    } else {
      $newOrdering=($curOrdering==1)?$maxOrdering:$curOrdering-1;
    }
    // Is this new ordering taken?
    $this->DB->execute(where("UPDATE ".$table." SET ordering=".$curOrdering." WHERE ordering=".$newOrdering,$CWC));
    $this->DB->execute("UPDATE ".$table." SET ordering=".$newOrdering." WHERE ".$keyCol."=".$keyVal);
}
*/

	function fixOrdering($table, $CWC)
	{
		return $this->reOrdering($table, $CWC);
	}

	// -------------------------
	// DATA DICTIONARY FUNCTIONS
	// -------------------------

	function getTablesFromDD($forceCacheReset = 0)
	{
		if ($forceCacheReset && $this->tables) {
			$this->tables = $this->columns = false;
		}
		if ($this->tables) return $this->tables;
		// Temporarily connect to the information_schema DB
		$this->openMysqli($this->ddSrc);
		// Get the tables (first session to encounter this does the work for everyone)
		$this->tables = $this->getAll("SELECT TABLE_NAME,ENGINE,TABLE_ROWS,AUTO_INCREMENT,DATA_LENGTH FROM TABLES WHERE TABLE_SCHEMA='" . $this->dbName . "' ORDER BY TABLE_NAME", 1);
		$this->stats['numGetsDD']++;
		// This is essential, so also alert the user (probably a developer if things are this un-setup)
		if (!$this->tables) {
			echo "<p>DB.getTablesFromDD: Fatal error - no access to information_schema</p>";
			$this->closeMysqli(1);
			return false;
		}
		if ($this->ddSrc == "information_schema" || !$this->persistent) $this->closeMysqli(1);
		return $this->tables;
	}

	// Starting at a leaf, returns CSV string of parent keys down to root
	function descendTree($table, $keyVal, $keyCol = false, $CWC = false)
	{
		$keyCol = ($keyCol) ? $keyCol : $this->getKeyCol($table);
		$parentID = $this->GetOne(where("SELECT parent" . initCap($keyCol, false) . " FROM " . $table . " WHERE " . $keyCol . "=" . $keyVal, $CWC));
		if (!$parentID || $parentID == $keyVal) return $keyVal;
		if ($parentID == 1) return "1," . $keyVal;
		return $this->descendTree($table, $parentID, $keyCol) . "," . $keyVal;
	}

	function getTree($sql, $keyCol, $parentKeyCol, $inflate = false, $startAtKeyID = false)
	{
    // Parse 0 - arrange data by key
		$this->dataSet = $this->GetArr($sql, $keyCol);
		if (!$this->dataSet) return false;
		$this->dataSet[0] = array($keyCol => 0, $parentKeyCol => false, 'children' => ""); // Add root
		// Parse 1 - allocate each row to it's parent
		foreach ($this->dataSet as $i => $d) {
			if ($i !== 0) {
				$pi = $d[$parentKeyCol];
				// Ensure parent has a 'children' section
				if (!isset($this->dataSet[$pi]['children'])) $this->dataSet[$pi]['children'] = "";
				$this->dataSet[$pi]['children'] = addTo($this->dataSet[$pi]['children'], $i);
			}
		}
		// Parse 2 - build the tree
		$this->safety = 0;
		$indicies = $this->extractTree();
		if (!$indicies) {
			trace("WARNING: Tree has no parents! " . $sql);
			return false;
		}
		// Parse 3 - re-order the dataset
		$r = [];
		foreach (explode(',', $indicies) as $i) {
			array_push($r, $this->dataSet[$i]);
		}
		// Optional Parse 4 - inflate into nested array (will also strip to branch if startAtKeyID given)
		if ($inflate) return $this->inflateFlatTree($r, $keyCol, $parentKeyCol, $startAtKeyID);
		return $r;
	}

	// Internal helper function for GetTree() that returns CSV of recursively ordered keys
	private function extractTree($i = 0, $parents = false)
	{
		if ($this->safety++ >= 9999) {
			echo "<h2>WARNING: extractTree exited on infinite(?) loop</h2>";
			return false;
		}
		$this->dataSet[$i]['descendants'] = "";
		$this->dataSet[$i]['parents'] = $parents;
		$this->dataSet[$i]['depth'] = ($parents) ? sizeOf(explode(",", $parents)) : 0;
		if (!isset($this->dataSet[$i]) || !isset($this->dataSet[$i]['children'])) return false;
		$parents = addTo($parents, $i); // This node is a parent
		$r = "";
		foreach (explode(',', $this->dataSet[$i]['children']) as $childID) {
			$r = addTo($r, $childID);
			$indicies = $this->extractTree($childID, $parents);
			if ($indicies) $r = addTo($r, $indicies);
		}
		$this->dataSet[$i]['descendants'] = $r;
		return $r;
	}

	// Internal helper function for GetTree()
	private function inflateFlatTree($flatTree, $keyCol, $parentKeyCol, $parentID = false)
	{
		$t = [];
		// Loop over entire tree at each level to determine which leaves belong with this parentID !! Surely there is an algorithmically more efficient way of achieving this?
		foreach ($flatTree as $i => $node) { // Add all of those with this same parent
			if ($parentID === false) $parentID = $node[$parentKeyCol];
			if ($node[$parentKeyCol] == $parentID) {
				$node['childNodes'] = $this->inflateFlatTree($flatTree, $keyCol, $parentKeyCol, $node[$keyCol]);
				array_push($t, $node);
			}
		}
		return $t;
	}

	function loadColumns($table, $suppressComplaints = true)
	{
		if (isset($this->columns[$table])) return $this->columns[$table];
		// Load from information_schema (the first call will save this for all others)
		$sql = "SELECT COLUMN_NAME,COLUMN_DEFAULT,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,COLUMN_TYPE,COLUMN_KEY FROM COLUMNS WHERE TABLE_NAME='" . $table . "' AND TABLE_SCHEMA='" . $this->dbName . "' ORDER BY ordinal_position";

		$this->openMysqli($this->ddSrc);
		$res = $this->mysqli->query($sql);
		if (!$res || $res->num_rows < 1) {
			if (!$res) echo "<p>DB.loadColumns for " . $this->dbName . "." . $table . ": Fatal error - no access to " . $this->ddSrc . ".COLUMNS table:" . $this->mysqli->error . " on</p>";
			if ($res->num_rows < 1 && !$suppressComplaints) trace("DB.loadColumns:" . $table . " " . $this->ddSrc . ".COLUMNS is empty");
			if ($this->ddSrc == "information_schema" || !$this->persistent) $this->closeMysqli(1);
			return false;
		}
		$this->columns = [];
    $this->columns[$table] = ['rawCols' => [], 'iCols' => [], 'pk' => []]; // Create entry for table whatever, so that we don't repeatedly attempt this if not found
		$this->stats['numGetsDD']++;
		$count = $res->num_rows;
		$result = $res->fetch_all(MYSQLI_ASSOC);

		$res->free();
		if ($this->ddSrc == "information_schema" || !$this->persistent) $this->closeMysqli(1);
		if ($count == 0) return false;
		$this->columns[$table]['rawCols'] = $result;
		foreach ($result as $col) {
			$this->columns[$table]['iCols'][$col['COLUMN_NAME']] = $col;
			if ($col['COLUMN_KEY'] == 'PRI') $this->columns[$table]['pk'] = $col['COLUMN_NAME']; // Cache the PK separately
		}
		return $this->columns[$table];
	}

	function tableExists($table)
	{
		if (!isset($this->columns[$table])) {
			$this->loadColumns($table, true);
		}
		return sizeOf($this->columns[$table]['rawCols']);
	}

	function columnExists($table, $colName)
	{
		return ($this->getColFromDD($table, $colName)) ? true : false;
	}

	function getColsFromDD($table)
	{
		// Results may be already cached
		if (!isset($this->columns[$table])) $this->loadColumns($table);
		return $this->columns[$table]['rawCols'];
	}

	function getKeyCol($table)
	{
		if (!isset($this->columns[$table])) $this->loadColumns($table);
		if (!$this->columns[$table]) return false;
		return $this->columns[$table]['pk'];
	}

	function getForeignKeys($table)
	{
		$keys = [];
		if (!isset($this->columns[$table])) $this->loadColumns($table);
		if (!$this->columns[$table]) {
			e('WARNING: getForeignKeys: table ' . $table . ' does not exist');
			return false;
		}
		foreach ($this->columns[$table]['rawCols'] as $col) {
			$len = strlen($col['COLUMN_NAME']);
			if (substr($col['COLUMN_NAME'], $len - 2, 2) == "ID") {
				$foreignTable = $this->getTableForPK($col['COLUMN_NAME']);
				if ($foreignTable) {
					$keys[$col['COLUMN_NAME']] = $foreignTable;
				} else {
					// Some foreign keys do not share their table name, e.g. createdByUserID
				}
			}
		}
		return $keys;
	}

	function getTableForPK($keyCol)
	{
		$table = substr($keyCol, 0, strlen($keyCol) - 2);
		$plural = $table . "s";
		if ($this->tableExists($plural) && $this->columnExists($plural, $keyCol)) {
			return $plural;
		}
		// Non-pluralised
		if ($this->tableExists($table) && $this->columnExists($table, $keyCol)) {
			return $table;
		}
		// e.g. addressID -> addresses
		$plural = $table . "es";
		if ($this->tableExists($plural) && $this->columnExists($plural, $keyCol)) {
			return $plural;
		}
		// e.g. behaviourCategoryID -> behaviourCategories
		if (strlen($keyCol) - strrpos($keyCol, "yID") == 3) {
			$table = substr($keyCol, 0, strlen($keyCol) - 3);
			$plural = $table . "ies";
			if ($this->tableExists($plural) && $this->columnExists($plural, $keyCol)) {
				return $plural;
			}
		}
		return false; // Full DD hunt?
	}

	// Return 1 column definition from the Data Dictionary
	function getColFromDD($tables, $colName, $safety = 0)
	{
		if ($safety > 9) return false;
		if (is_array($tables)) {
			foreach ($tables as $table) {
				$col = $this->getColFromDD($table, $colName, ++$safety);
				if ($col) return $col;
			}
			die('no ' . $colName . " in " . sTest($tables));
			return false;
		} else {
			$table = $tables;
			if (!isset($this->columns[$table])) $this->loadColumns($table);
			if (!isset($this->columns[$table]['iCols'][$colName])) return false;
			$col = $this->columns[$table]['iCols'][$colName];
			$col['table'] = $table;
			return $col;
		}
	}

	function getBlankRow($table, $includePK = false, $defaults = false)
	{
		$res = [];
		foreach ($this->getColsFromDD($table) as $col) {
			if ($includePK || $col['COLUMN_KEY'] != 'PRI') {
				$var = $col['COLUMN_NAME'];
				$res[$var] = (isset($defaults[$var])) ? $defaults[$var] : null;
			}
		}
		return $res;
	}

	// Return slow queries, worst first
	function getLog()
	{
		$this->log = superSort($this->log, 'ms', true);
		return $this->log;
	}
	// Return most frequently executed queries, most costly first
	function getLogFrequent()
	{
		$frequent = [];
		foreach ($this->log as $ms => $info) {
			$sql = substr($info['sql'], 0, strpos($info['sql'], 'WHERE') + 5) . " ...";
			if (!isset($frequent[$sql])) {
				$frequent[$sql] = ['sql' => $sql, 'count' => 1, 'numRows' => $info['numRows'], 'ms' => $info['ms'], 'avg' => $info['ms'], 'example' => $info['sql']];
			} else {
				$frequent[$sql]['count']++;
				$frequent[$sql]['numRows'] += $info['numRows'];
				$frequent[$sql]['ms'] += $info['ms'];
				if (rand(1, 9) == 9) $frequent[$sql]['example'] = $info['sql'];
			}
		}
		// Re-index on count / ms
		$log = [];
		foreach ($frequent as $sql => $info) {
			$info['avg'] += round($info['ms'] / $info['count'], 2);
			$log[$info['count'] . " @ " . $info['ms'] . "ms"] = $info;
		}
		$log = superSort($log, 'ms', true);
		return $log;
	}

	// Remove temporary backup tables beginning tmp%
	function removeTmpTables()
	{
		e("Removing tmp tables...");
		$sql = "
      SELECT *
      FROM information_schema.tables
      WHERE TABLE_SCHEMA='" . $this->dbName . "'
      AND TABLE_NAME LIKE 'tmp%'
    ";
		$count = 0;
		$tables = $this->getAll($sql, false, 1);
		if ($tables) {
			foreach ($tables as $table) {
				$this->execute("DROP TABLE " . $table['TABLE_NAME']);
				e('Dropped table ' . $table['TABLE_NAME']);
			}
			e("Dropped " . $count . " tmp tables");
			$count++;
		}
		return $count;
	}
}
