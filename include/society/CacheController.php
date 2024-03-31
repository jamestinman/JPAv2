<?php

namespace Society;
// Cache: Society's simplified memcache connection class
use Memcached;

require_once(dirname(__FILE__) . '/essentials.php');

class CacheController
{

  public $logQueries = -2; // Switch this on to enable performance debugging -2=log all (but don't display) -1=log all, 0=log none, X=log X most expensive
  public $useCache = -1; // -1=automatically choose (dependent on what is available on this system), 0=no caching, 1=$_SESSION (disk), 2=memcache (RAM), 3=memcached (updated memcache)
  public $mCache = false; // memcache connection (if memcache is available, if not $_SESSION will be used)
  protected $serverName;
  protected $timeOut = 39600;
  public $log = [];
  public $stats = ['cacheGets' => 0, 'cacheSets' => 0, 'cacheDels' => 0, 'rowsRetrievedCache' => 0, 'cacheMS' => 0];
  public $memcacheServers = [];

  function __construct($serverName = false, $memcacheServers = false, $useCache = -1)
  {
    $this->serverName = $serverName;
    $this->useCache = $useCache;
    if ($memcacheServers) {
      if (!is_array($memcacheServers)) {
        $memcacheServers = [$memcacheServers => $memcacheServers];
      }

      $this->memcacheServers = $memcacheServers;
    }
  }

  function openMemcache()
  {
    // Start a connection to memcache (if available)
    // NB: always persistent
    if (!$this->useCache) {
      $false = false;
      return $false;
    }
    // No caching
    if ($this->mCache) {
      return true;
    }
    // Already connected
    if ($this->useCache == -1 || $this->useCache >= 2) {
      if (class_exists('Memcached') && ($this->useCache == -1 || $this->useCache == 3)) {
        // "Memcached" is newer and faster than PHP "Memcache" so if available use it
        $this->mCache = new Memcached();

        if (Memcached::HAVE_IGBINARY) { // it exists, let's enjoy it
          $this->mCache->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY); // faster serialisation/deserialisation
        }

        $this->mCache->setOption(Memcached::OPT_NO_BLOCK, false); // trying this to stop race conditions
        $this->mCache->setOption(Memcached::OPT_COMPRESSION, false); // faster
        $this->mCache->setOption(Memcached::OPT_BINARY_PROTOCOL, true); // allows using extra memcache options such as http://php.net/manual/en/memcached.append.php

        // Load multiple memcache servers
        foreach ($this->memcacheServers as $name => $ip) {
          $this->mCache->addServer($ip, 11211) or die("Could not connect to memcached at " . $ip);
        }
        $this->useCache = 3;
      } else if (class_exists('Memcache')) {
        $this->mCache = new Memcache();

        $this->useCache = 2;
        try {
          // Memcache is being a total dick with errors when memcached is not running,
          // so we suppress errors and throw an exception when it can't connect
          $firstServer = getFirstKey($this->memcacheServers);
          $ip = $this->memcacheServers[$firstServer];
          if (!@$this->mCache->connect($ip, 11211)) {
            throw new Exception("Could not connect via Memcache on " . $ip . ":11211");
          }

        } catch (Exception $e) {
          trace("Memcache connection exception " . $e->getMessage() . " :(");
          $this->mCache = false;
        }
      }
      if (!$this->mCache) {
        // Memcache not connecting :(
        $this->mCache = false;
        $this->useCache = 1; // Downgrade to SESSION caching
      }
    }
    if ($this->useCache == -1 || $this->useCache == 1) {
      if (isset($_SESSION)) {
        $this->useCache = 1;
        if (!isset($_SESSION['sCache'])) {
          $_SESSION['sCache'] = [];
        }
        // Instantiate session data store instead of memcache
      } else {
        $this->useCache = 0;
      }
    }
  }

  function getUseCache()
  {
    if (!$this->mCache) {
      $this->openMemcache();
    }

    return $this->useCache;
  }

  function closeMemcache($force = 0)
  {
    if ($this->mCache && $this->useCache == 3) {
      $this->mCache->quit();
    }

    if ($this->mCache && $this->useCache == 2) {
      $this->mCache->close();
    }

    $this->mCache = false;
  }

  function get($var)
  {
    if (!$var) {
      $false = false;
      return $false;
    }
    $ts = microtime(true);
    if (!$this->mCache) $this->openMemcache();

    $var = $this->cleanVar($var);
    if ($this->useCache == 3) {
      try {
        $data = $this->mCache->get($var);
        $rc = $this->mCache->getResultCode();
        if (!$rc && $rc !== 0 && $rc !== 16) { // 0=SUCCESS, 16=NOT FOUND (valid)
          $msg = $this->mCache->getResultMessage();
          trace("Memcache get error [" . $rc . ":" . $msg . "] " . $var, 1);
          $false = false;
          return $false;
        }
      } catch (Exception $e) {
        trace("Memcache get exception " . $e->getMessage() . " :(");
        $this->mCache = false;
      }
    } else if ($this->useCache == 2) {
      $data = $this->mCache->get($var);
    } else {
      if (isset($_SESSION['sCache'])) {
        $data = (isset($_SESSION['sCache'][$var])) ? $_SESSION['sCache'][$var] : false;
      } else {
        $data = false;
      }
    }
    // NB: count($data,1) also counts sub-arrays (but presumably is much slower)
    if ($this->logQueries) {
      $this->log('get', $var, safeCount($data), $ts);
    }
    return $data;
  }

  function set($var, $val, $dictionaryName = false)
  {
    if (!$var) {
      $false = false;
      return $false;
    }

    $ts = microtime(true);
    $expiration = 60 * 60 * 23; // 23 hour default purge, so it doesn't occur at same time each day

    if (!$this->mCache) {
      $this->openMemcache();
    }

    $var = $this->cleanVar($var);

    if ($this->useCache == 3) {
      $ok = $this->mCache->set($var, $val, $expiration);
      if (!$ok) {
        trace("Memcache set(" . $var . ") error [" . $this->mCache->getResultCode() . ":" . $this->mCache->getResultMessage() . "] ", 1);
        if ($this->mCache->getResultCode() == 37) { // Item too big
          trace($var . " is TOO BIG at " . round(strlen(json_encode($val)) / 1024 / 1024, 2) . "mb");
        }
        $false = false;
        return $false;
      }
    } else if ($this->useCache == 2) {
      $this->mCache->set($var, $val, MEMCACHE_COMPRESSED, $this->timeOut);
    } else if ($this->useCache == 1) {
      $_SESSION['sCache'][$var] = $val;
    }
    if ($dictionaryName && $this->useCache > 1) {
      $this->addToDictionary($dictionaryName, $var);
    }
    if ($this->logQueries) {
      $this->log('set', $var, safeCount($val), $ts);
    }
  }

  protected function valueTooBig($val)
  {
    if (is_array($val)) {
      $sizeOf = safeCount($val, 1);
      return ($sizeOf > 100);
    }
    $string = strval($val);
    return (strlen($string) > 500);
  }

  function delCached($var)
  {
    $ts = microtime(true);
    if (!$this->mCache) {
      $this->openMemcache();
    }

    $var = $this->cleanVar($var);
    if ($this->useCache == 3) {
      $ok = $this->mCache->delete($var);
      if (!$ok) {
        $rc = $this->mCache->getResultCode();
        if ($rc != 16) { // 16 is not found (which is fine)
          trace("Memcache delete error [" . $rc . ":" . $this->mCache->getResultMessage() . "] " . $var, 1);
          $false = false;
          return $false;
        }
      }
    } else if ($this->useCache == 2) {
      $this->mCache->delete($var);
    } else if ($this->useCache == 1 && isset($_SESSION['sCache'][$var])) {
      unset($_SESSION['sCache'][$var]);
    }
    if ($this->logQueries) {
      $this->log('delCached', $var, 1, $ts);
    }

  }

  function getDictionary($dictionaryName)
  {
    $dictionaryName = $this->cleanVar($dictionaryName);
    $dict = $this->get($dictionaryName);
    if (!$dict) {
      return [];
    }

    if (!is_array($dict)) {
      $dict = explode(",", $dict);
    }
    return $dict;
  }

  // MEMCACHE DICTIONARY (why is this not implemented as part of memcache?)
  // Dictionaries only exist to remember what's in memcache; they do not hold values
  function addToDictionary($dictionaryName, $var)
  {
    // Add DB name to dictionary and var...
    $dictionaryName = $this->cleanVar($dictionaryName);
    $var = $this->cleanVar($var);

    // Pull dictionaries into memory
    $dictionary = $this->getDictionary($dictionaryName);

    $matches = array_keys($dictionary, $var);
    if (safeCount($matches) > 0) {
      $false = false;
      return $false; // it's already there
    }

    array_push($dictionary, $var);
    $this->set($dictionaryName, $dictionary); // Save the dictionary if we have a new entry
  }

  function delFromDictionary($dictionaryName, $var)
  {
    // Add DB name to dictionary and var...
    $dictionaryName = $this->cleanVar($dictionaryName);
    $var = $this->cleanVar($var);
    $dictionary = $this->getDictionary($dictionaryName);
    if (!$dictionary) {
      $false = false;
      return $false;
    }

    $matches = array_keys($dictionary, $var);
    foreach ($matches as $key) {
      unset($dictionary[$key]);
    }
    $this->set($dictionaryName, $dictionary); // Save the dictionary if we have removed an entry
  }

  function delDictionary($dictionaryName)
  {
    $dictionaryName = $this->cleanVar($dictionaryName);
    $dictionary = $this->getDictionary($dictionaryName);
    if (!$dictionary) {
      $false = false;
      return $false;
    }
    $count = 0;
    foreach ($dictionary as $var) {
      $this->delCached($var);
      $count++;
    }
    // $this->delCached($dictionaryName); // Don't remove the dictionary itself, as memcache is flaky at flushing and once gone it cannot be reflushed
    return $count;
  }


  function testDictionary($dictionaryName)
  {
    $dictionaryName = $this->cleanVar($dictionaryName);
    $dictionary = $this->getDictionary($dictionaryName);
    if (!$dictionary) {
      $false = false;
      return $false;
    }
    $count = 0;
    foreach ($dictionary as $var) {
      echo "<p>Var=" . $var . ":</p>";
      test($this->get($var), 2);
      $count++;
    }
    // $this->delCached($dictionaryName); // Don't remove the dictionary itself, as memcache is flaky at flushing and once gone it cannot be reflushed
    return $count;
  }

  function flushCache()
  {
    $ts = microtime(true);
    if (!$this->useCache) {
      $false = false;
      return $false;
    }

    if ($this->useCache >= 2) {
      $this->mCache->flush();
    }

    if ($this->useCache == 1 && isset($_SESSION['sCache'])) {
      $_SESSION['sCache'] = [];
    }
    //if ($this->logQueries) $this->log('flushCache',$var,0,$ts);
  }

  function cleanVar($var, $mustMatch = false)
  {
    if ($this->serverName && strpos($var, $this->serverName . "-") === false) {
      $var = $this->serverName . "-" . $var;
    }
    $dirtyVar = $var;
    $var = cleanString($var, ['_', '-', ',', '+', ':', ';', '!', '=', '@', '#', '$', '%', '&', '*', '.']);
    if ($mustMatch && $var != $dirtyVar) {
      $false = false;
      return $false;
    }

    if (strlen($var) > 250) {
      $var = hashData($var);
    }

    return $var;
  }

  function log($funcName, $var, $numRows, $tsStart)
  {
    if (!$this->logQueries) {
      $false = false;
      return $false;
    }

    $ms = round((microtime(true) - $tsStart) * 1000, 3);
    $this->stats[$funcName] = (isset($this->stats[$funcName])) ? $this->stats[$funcName] + 1 : 1;
    if (in($funcName, "get")) {
      $this->stats['cacheGets']++;
      $this->stats['rowsRetrievedCache'] += $numRows;
    }
    if (in($funcName, "set")) {
      $this->stats['cacheSets']++;
    }

    if (in($funcName, "set")) {
      $this->stats['cacheDels']++;
    }

    $this->stats['cacheMS'] += $ms;
    if ($this->logQueries < 0 || $ms > $this->stats['minMS']) {
      // Add me to to the mostExpensive list
      $this->log[$ms . "ms"] = [
        'funcName' => $funcName,
        'var' => $var,
        'ms' => $ms,
      ];
    }
  }

  // Return slow retrievals, worst first
  function getLog()
  {
    $this->log = superSort($this->log, 'ms', true);
    return $this->log;
  }

  // Convert a var name into something more general for logging e.g. KCDEV-pB1042344results => KCDEV-pB[pupilID]results
  function getGenericVar($var)
  {
    $pupilID = locate('p' . $_SESSION['shard'], $var);
    if ($pupilID) $var = str_replace($pupilID, '[pupilID]', $var);
    $schoolID = locate('s' . $_SESSION['shard'], $var);
    if ($schoolID) $var = str_replace($schoolID, '[schoolID]', $var);
    $clusterID = locate('c' . $_SESSION['shard'], $var);
    if ($clusterID) $var = str_replace($clusterID, '[clusterID]', $var);
    return $var;
  }

  // Return most frequently requested, most costly first
  function getLogFrequent()
  {
    $frequent = [];
    foreach ($this->log as $ms => $info) {
      // Make var names more generic so we can see e.g. all those targeting specific pupils in one block
      $example = $info['funcName'] . "(" . $info['var'] . ")";
      $var = $this->getGenericVar($info['var']);
      $i = $info['funcName'] . "(" . $var . ")";
      if (!isset($frequent[$i])) {
        $frequent[$i] = ['funcName' => $info['funcName'], 'var' => $var, 'count' => 1, 'ms' => $info['ms'], 'avg' => $info['ms'], 'example' => $example];
      } else {
        $frequent[$i]['count']++;
        $frequent[$i]['ms'] += $info['ms'];
        if (rand(1, 9) == 9) $frequent[$i]['example'] = $var;
      }
    }
    // Re-index on count / ms
    $log = [];
    foreach ($frequent as $i => $info) {
      $info['avg'] += round($info['ms'] / $info['count'], 2);
      $log[$info['count'] . " @ " . $info['ms'] . "ms"] = $info;
    }
    $log = superSort($log, 'ms', true);
    return $log;
  }

  // Lists - comma-separated lists of values
  function getList($list)
  {
    return $this->get("L" . $list);
  }

  // !! Re-implement with Redis?
  function addToList($list, $what)
  {
    $vals = $this->get("L" . $list);
    $newVals = addTo($vals, $what, ",", true);
    if ($newVals == $vals) {
      return 0;
    }

    $this->set("L" . $list, $newVals);
    return 1;
  }

  function removeFromList($list, $what)
  {
    $vals = $this->get("L" . $list);
    $newVals = removeFrom($vals, $what);
    if ($newVals == $vals) {
      return 0;
    }

    $this->set("L" . $list, $newVals);
    return 1;
  }

}
