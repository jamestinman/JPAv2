<?php
date_default_timezone_set("Europe/London");

// 1. GENERAL HANDY FUNCTIONS
// --------------------------

// PHP's null equivalence is f'ed up... so here's a sensible evaluation function
// in normal PHP ""==0=="0"==null==false but we want 0's to be considered as not null
/*
  a note from Ben Thompson, because I find this incredibly confusing...
  this function returns TRUE (ie not null) for 0 or '0',
  but false (ie actually NULL?) for any other falsey value, including false, [], 'NULL' etc.
*/
function notnull($s=null) {
	if ($s===0 || $s==='0') return 1; // has value, albeit that value is zero
  if (empty($s)) return 0; // definitely empty
	if (is_bool($s) && (int)$s==1) return 1;
  if ($s===false) return 0;
  if ($s=="" || $s==" " || $s=="  " || $s=="0000-00-00" || $s=="\n" || $s=="NULL" || $s=='false') return 0;
	return true;
}
function isnull($s) { return !notnull($s); }
function isNum($s) { return ($s!==false && ($s===0 || is_numeric($s))); }
function isOdd($inNum) { return $inNum&1; }
function charIsNum($ch) { return ($ch===(string)((int)$ch)); }
// Round f to the nearest odd number (handy for pointScores without in-between levels)
// function roundOdd($f) { $r=round($f); if (isOdd($r)) return $r; return ($f>$r)?$r+1:$r-1; }

function castAsNum($s) {
	$type=gettype($s);
	if ($type=="integer") return (int)$s;
	if ($type=="double") return $s;
	if ($type=="boolean") return ($s)?1:0;
	$s=cleanNum($s);
	return (strpos($s,".")!==false)?(float)$s:(int)$s;
}
// Strips out all non-numerically valid characters
function toInt($s) { return round(cleanNum($s)); }
function cleanNum($s) { return preg_replace("/[^0-9\.\-]/", "", $s ); }
// Strips out all non-alpha-numeric characters. $allowThese is an array of expections, e.g. [" ","@"];
function cleanString($s,$allowThese=[" "]) {
  $regexp="^a-zA-Z0-9"; // \s represents the space character
  if (is_array($allowThese)) foreach ($allowThese as $c) { $regexp.=($c==" ")?"\s":(($c=='.' || $c=='-' || $c=='+' || $c=="\\")?"\\".$c:$c); }
  // Note: mb_ereg does not use the / / of preg_replace
  $clean=mb_ereg_replace("[".$regexp."]", "", $s );
  return $clean;
}
// Allow common international characters
function cleanName($dirty) {
  return cleanString($dirty,explode(",","-,è,é,à,â,ä,ê,ë,î,ï,ô,œ,ù,û,ü,ÿ,À,Â,Ä,È,É,Ê,Ë,Î,Ï,Ô,Œ,Ù,Û,Ü,Ÿ,Ç,ç")); // Also allow French characters
}

function myCeil($n) { // Fix for PHP ceil function's floating point bug (see http://stackoverflow.com/questions/8270533/php-ceil-function-strange-behavior )
  return ceil(((int)$n*10000)/10000);
}

// REDIRECT ROUTINES
function getRedirUrl($url=false) {
	// if (!$url && isset($_SESSION['redirPage'])) $url=$_SESSION['redirPage'];
	if (!$url && isset($_SERVER['HTTP_REFERER'])) $url=$_SERVER['HTTP_REFERER'];
	$myUrl=getPageUrl();
	if ($url==$myUrl || (strlen($myUrl)>1 && strpos($url,$myUrl)!==false)) $url=false; // Don't get into a redirect loop!
	return $url;
}

// Redirect to a given URL. Set-up SESSION['redirPage'] prior to calling to automatically get back to where you were
function redir($url=false,$tmpLegacyParam=false,$redirectType=false,$force=false) {
	if (!$force) $url=getRedirUrl($url);
  if (isCli()) {
    die("Redirect triggered in CLI mode (wanted to go to ".$url.")");
  }
	if (headers_sent()) {
    echo "<script>document.location.href='".$url."';</script>";
	} else {
		headerRedir($url,$redirectType);
	}
	exit;
}
function reload() { echo "<script>document.location.reload();</script>"; die; } function closePopUp($urlOrRefresh=false) {
  echo "<script>";
  echo "if (typeof PA!== 'undefined') { PA.closePopUp(); }";
  if ($urlOrRefresh===1 || $urlOrRefresh===true) {
    echo "document.location.reload()";
  } else {
    echo "\ndocument.location.href=".fss($urlOrRefresh)."; console.log('redir')";
  }
  echo "</script>"; // Kills pop-up
}
function popRedir($url=false,$closePopUp=false) {
	$url=getRedirUrl($url);
	if ($closePopUp) {
	  closePopUp($url);
	} else {
        $url .= ((strpos($url, '?') === false) ? "?" : "&") . "displayMode=POPUP";
		headerRedir($url); // Keeps pop-up open
	}
	exit;
}
function headerRedir($url,$redirectType=false) {
	if ($redirectType==301) header('HTTP/1.1 301 Moved Permanently');
	if ($redirectType==303) header("HTTP/1.1 303 See Other");
	header("Location: $url");
	exit;
}
function jsonHeader() {
	header('Content-Type: application/json; charset=utf-8');
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header('Access-Control-Allow-Origin: http://localhost');
	header('Access-Control-Allow-Credentials: true');
	header("Pragma: no-cache");
}
function addReturnInfo($r) {
  global $env;
  $r['sessionID']=session_id();
  $r['instance']=((isset($env['instance']))?$env['instance']:false);
  return $r;
}

// TRACE & STATUS
// echo but with a newline for CLI (suppressed for api calls)
function e($m,$showTimings=0,$force=0) {
  if (!$m) return false;
  if (is_array($m)) $m=sTest($m);
  if (!allowTrace($force)) return false;
  if (isCli()) {
    echo (zapTags($m)."\n");
  } else {
    debug($m,'trace',$showTimings);
  }
}
function allowTrace($force=0) {
  global $env;
  $allowDebug = 1;
  if (isset($env['ajax']) && $env['ajax'] && !$force) return false;
  return ($force || isCli() || $allowDebug)?1:0;
}

// Same as e() but used to differentiate temporary debug messages
function trace($s,$ts=false,$force=false) { e($s,$ts,$force); }

function stackTrace($m=false,$force=2) {
  trace($m,1,$force);
  test(debug_backtrace(),$force);
}
function jsTrace($m,$final=0) {
  if (!isset($_SERVER["HTTP_HOST"])) {
    /* CLI */ return false; // echo zapTags($m)."\n";
  } else {
    if (ob_get_level() == 0) ob_start();
    if (!$final) {
      echo "<script>PA.loading('".escSQL($m)."');</script>";
    } else {
      echo "<script>PA.loaded('".escSQL($m)."');</script>";
    }
    ob_flush();
    flush();
    if ($final) ob_end_flush();
  }
}

function aaComment(string $string) {
    if (!allowTrace()) return false;
    return "<div class='trace'> {$string} </div>";
}

$startTime=microtime(true); $lastTime=$startTime;
function getTiming($m=false) {
	global $startTime, $lastTime;
	$nowTime=microtime(true);
	$m=" [".round($nowTime-$startTime,3)." secs (".round($nowTime-$lastTime,3).")] ".$m;
	$lastTime=$nowTime;
	return $m;
}
function debug($m,$class='debug',$showTimings=false) {
	if ($showTimings) $m=getTiming($m);
	$d=new DateTime();
	$m=$d->format("H:i:s").": ".$m;
	// if logger exists, use that
	global $errorLogger;
	if ($errorLogger) {
		$errorLogger->logComment($m);
		return false;
	}
	if (!isset($_SERVER["HTTP_HOST"])) { echo $m."\n"; return true; }
	echo "<p class='".$class."'>".strToUpper($class)."@".$m."</p>";
	// ob_flush();
}

$tracePaddingSent=0;
function killTracePadding() {
  global $tracePaddingSent;
  $tracePaddingSent=1;
}

// Test any php object (for debugging) e.g. test($crazyArray);
// outputType 0: echo HTML
// outputType 1: echo text (best for command line)
// outputType 2: force HTML
// outputType 3: return as string (no echo)
// outputType 4: write to log file
function test($obj,$outputType = 0) {
  $c="";
  if (isCli()) $outputType = 1;
	if ($outputType && $outputType!=2) {
		$c=var_export($obj,true); $c.="\n";
	} else if (gettype($obj)=="object") {
    ob_start();
    var_dump($obj);
    $c = ob_get_clean();
  } else {
    if ($obj===false) $c.="<i>false</i>";
    if ($obj===null) $c.="<i>null</i>";
    if (is_array($obj)) $c.=testArray($obj); else if (is_string($obj)) $c.=$obj; else var_export($obj,true);
  }
  if ($c) {
    if (!$outputType) {
      $c="<div style='text-align:left; font-family:Courier;'>".$c."</div>";
    } else if ($outputType == 3) {
      return $c;
    } else if ($outputType == 4) {
      logToFile($c);
    } else {
      echo $c;
    }
  }
}
function testArr($a,$cram=false) { echo testArray($a,$cram); }
function testArray($a,$cram=false,$depth=0) {
  if ($depth++>99) return "OUT OF RECURSION:".$depth;
  if (!is_array($a)) return "<i>Not an array! Is [".sTest($a)."]</i>";
  if (safeCount($a)==0) return "<i>Array is empty</i>";
  if (getIp()!="CLI") {
    $c="<table style='border: 1px solid red;".(($cram)?"font-size:0.8em;":"")."'>".(($cram)?"<tr>":"");
    foreach ($a as $key=>$d) {
      $c.=(($cram)?"":"<tr>")."<td valign='top'><b>".$key."</b>:</td><td valign='top'>".((is_array($d))?testArray($d,$cram,$depth):((is_object($d))?((is_callable(array($d,"toString")))?$d->toString():"[object]"):escCode($d)))."</td>".(($cram)?"":"</tr>")."\n";
    }
    $c.="</table>";
  } else {
    // CLI version
    $c="";
    foreach ($a as $key=>$d) { $c.="".$key.": ".((is_array($d))?testArray($d,$cram,$depth):((is_object($d))?((is_callable(array($d,"toString")))?$d->toString():"[object]"):$d))."\n"; }
  }
  return $c;
}
// stringTest - like test, but returns a string with the contents of the variable/array/object as a human readable string
function sTest($obj) { return var_export($obj,true); }

// Takes a 2 dimensional array (e.g. from a SELECT statement) and formats as ascii table
function cliTable($arr,$flat=0) {
  if ($flat) { // Convert flat arrays to 2D
    $oldArr=$arr;
    $arr=[];
    foreach ($oldArr as $var=>$val) {
      array_push($arr,['var'=>$var,'val'=>$val]);
    }
  }
  $cols=[];
  // Determine size of each field
  foreach ($arr as $i=>$row) {
    if (!$flat && !is_array($row)) return cliTable($arr,1); // Needs the flat key=>value pair version
    foreach ($row as $key=>$val) {
      $cols[$key]=(isset($cols[$key]) && $cols[$key]>strlen($val))?$cols[$key]:strlen($val);
    }
  }
  // Header
  $totalWidth=0;
  $hr=""; // e.g. +-----------------------+--------------+----------+
  $th=""; // e.g. | table_name            | table_schema | sizeInMB |
  foreach ($cols as $col=>$size) {
    $hr.="+".repeat('-',$size+2);
    $th.="| ".rpad($col,$size,' ')." ";
    $totalWidth+=$size+3;
  }
  $hr.="+\n";
  $th.="|\n";
  // Body
  $c="";
  foreach ($arr as $i=>$row) {
    foreach ($cols as $col=>$size) {
      $val=$row[$col];
      if (isNum($val)) {
        $c.="| ".lpad($val,$size,' ')." ";
      } else {
        $c.="| ".rpad($val,$size,' ')." ";
      }
    }
    $c.="|\n";
  }
  if ($flat && $flat==1) return $hr.$c.$hr;
  if ($flat) $th="| ".rpad($flat,$totalWidth,' ')."|\n";
  return "\n".$hr.$th.$hr.$c.$hr;
}

$statusDivExists=0;
// Update the status div (if included on a page) or the avalonJob (if _SESSION['avalonJobID'] exists)
function showStatus($s=false,$append=false,$killBar=false) {
	global $statusDivExists, $hasBar,$ssBar,$ssAnimFrame;
  $s=($s)?$s:((isset($_SESSION['sMsg']))?$_SESSION['sMsg']:"");
  if (!$s || $s==" ") return false;
  $_SESSION['sMsg']=$s;
  if ($hasBar && !$killBar) {
    // ssAnim spins to the next frame of it's animation on each showStatus call
    if (isCli()) {
      $ssAnim=["-","\\","|","/"]; // CLI Anim
    } else {
      $ssAnim=["&#9625;","&#9627;","&#9628;","&#9631;"];
    }
    $ssAnimFrame=($ssAnimFrame<safeCount($ssAnim)-1)?$ssAnimFrame+1:0;
    $curAnim=(isCli())?$ssAnim[$ssAnimFrame]:"<img height=16 src='/img/loader.gif' />";
    $bs=str_replace("A",$curAnim,$ssBar)." ".$s; // 'Animate' the current block
  } else {
    $bs=$s;
  }
  if (!$bs) return false;
  if (!isCli()) {
		if (!$statusDivExists) {
		  echo "<div id='kSiteStatus' class='statusDiv'></div>"; // Note: inline style as CSS may not yet be loaded
  		$statusDivExists=1;
  	}
    echo "<script>document.getElementById('kSiteStatus').innerHTML".(($append)?"+":"")."='<p>".escSQL($bs)."</p>';</script>\n";
  } else {
		eLog($s);
  }
}


//sort of a combination of test() and var_dump() that can take multiple inputs.
function view(...$arguments) {
    global $env;
    if (!isset($_SESSION['devTeam']) || !$_SESSION['devTeam']) return false;
    if (isCli()) return false;
    foreach ($arguments as $a) {
        echo "<div style='text-align:left; font-family:Courier; color:gray'>";
        $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0];
        if (in_array(gettype($a), ['boolean', 'NULL'])) {
             $colour = 'blue';
        } else if (in_array(gettype($a), ['int', 'integer', 'double', 'float'])) {
            $colour = 'green';
        } else {
            $colour = 'black';
        }
        echo $debug['line'] . $debug['file'] . " : <br> <div style='color:{$colour}'><i> (".gettype($a).") </i> ";
        if (is_array($a)) {
            echo viewArray($a);
        } else {
            var_export($a, false);
        }
        echo "</div></div>";
    }
}
// as above but prints to the console instead of page
function consoleView(...$arguments) {
    global $env;
    if (!isset($_SESSION['devTeam']) || getIfSet($env, 'ajax') || !$_SESSION['devTeam']) return false;
    if (isCli()) return false;
    $outputs = [];
    $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    foreach ($arguments as $a) {
        $outputs[] = $debug[0]['file']. " ".$debug[0]['line']." (".gettype($a).") :";
        $outputs[] = $a;
    }
    register_shutdown_function('viewInConsole', false, ...$outputs);
}

// print a stack trace on screen; if first argument, it prints to console; if a second, values inputted to all the functions are included
// this doesn't work if ajaxing, and dataType is json.
function viewStack($js=false, $includeArgs=false) {
    global $env;
    $c = "<div style='border:1px solid red; display:inline-block'><p>";
    $closers = "</div>";
    $stack = $includeArgs? debug_backtrace() : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $console = [];
    for ($l = safeCount($stack)-1; $l >= 0;  $l--) { //leave out 0 as it's this function right here.
        $function = (!empty($stack[$l]['class']))? $stack[$l]['class'] : ((!empty($stack['object']))? $stack['object'] : "");
        $function.= (!empty($stack[$l]['type']))? $stack[$l]['type']: "";
        if ($function == "") $function = "<i>(global) </i>";
        $args = "";
        if (!empty($stack[$l]['args'])) foreach($stack[$l]['args'] as $arg) $args.= (($args=="")? "" : ", ").json_encode($arg);
        $function.= $stack[$l]['function']."({$args});";
        $location = $stack[$l]['file']." : ".$stack[$l]['line'];
        if ($js) {
            array_push($console, ['function' => $function, 'file' => $stack[$l]['file'], 'line' => $stack[$l]['line']]);
        } else {
            $c.= "<div style='display:inline-block; border: 1px solid red; border-bottom:0px; border-right:0px; padding:3px 0px 0px 5px; margin-left:15px;'> <p style='padding:2px; padding-bottom:4px; margin:0px; font-family:Helvetica'>
                <code style='font-family:Courier'> <b>{$function}</b></code><br>{$location}";
            $closers.= "</div>";
        }
    }
    if ($js) {
        register_shutdown_function('viewInConsole', true, $console);
    } else {
        echo $c.$closers."<br>";
    }
}

// just utility used by view functions above.
function viewArray($arr, $limit = 0) {
    if ($limit > 10) return '';
    if (safeCount($arr) == 0) return "<i> Array is empty </i>";
    $c = "<table style='border: 1px solid red; font-size:0.8em; '>";
    foreach ($arr as $key=>$d) {
        $c.= "<tr> <td valign='top'><b>".$key."</b> : </td> <td valign='top'>";
        if (is_array($d)) {
            $c.= viewArray($d, $limit + 1);
        } else {
            if (is_object($d)) {
                if (is_callable(array($d, "toString"))) {
                    $c.= $d->toString();
                } else {
                    $c.= "[object]";
                }
            } else {
                $c.= var_export($d, true);
            }
        }
        $c.= "</td></tr>";
    }
    $c.="</table>";
    return $c;
}
// just utility used by view functions above.
function viewInConsole($traceTable, ...$outputs) {
    global $env;
    $console = "<script>";
    foreach ($outputs as $output) {
        $console.= "console.".($traceTable && (is_array($output))? "table" : "log")."(".json_encode($output).");";
    }
    $console.= "</script>";
    echo $console;
}

$ssAnimFrame=0; $whichBlock=1; $ssBar=""; $hasBar=false; $barTotal=-1; $barCurrent=0;
// Same as showStatus but includes a progress bar. barCurrent/barTotal controls %. barTotal=-1 that keep looping (if you don't know what the total will be)
function showProgress($s=false,$trace=false,$inBarCurrent=false,$inBarTotal=false) {
  global $whichBlock, $ssBar, $hasBar, $barTotal, $barCurrent;
  // Keep total/current in globals so that interim calls to show Progress can still display blocks
  if ($inBarTotal) $barTotal=$inBarTotal;
  if ($inBarCurrent) $barCurrent=$inBarCurrent;
  $s=($s)?$s:((isset($_SESSION['sMsg']))?$_SESSION['sMsg']:"");
  // bigBar shows percentage progress (if barCurrent/barTotal given) or every 100 ssAnims
  $whichBlock=($barTotal>0)?(($barCurrent/$barTotal)*10):(($whichBlock<10)?$whichBlock+0.01:0);
  $ssBar="[";
  for ($n=1; $n<=floor($whichBlock); $n++) $ssBar.=(!isset($_SERVER["HTTP_HOST"]))?"*":"&#9724;";
  $ssBar.="A";
  while (++$n<=10) $ssBar.="_";
  $ssBar.=" ".(($barTotal>0)?round(($barCurrent/$barTotal)*100)."%":"")."]";
  $killBar=($barTotal==0);
  $hasBar=(!$killBar);
  return showStatus($s,$trace,$killBar);
}
function clearStatus() { showStatus(" ",false,true); }

// Digests & expands/explodes a string and displays as HTML table of ascii characters (for debug)
function digestString($s, $showAsciiCodes=true) {
	if (is_array($s)) { echo "<i>disectString(Array) cannot be performed</i>"; return false; }
	if (isnull($s)) { echo "<i>Empty string</i>"; return false; }
  $arr=preg_split('//u', $s, null, PREG_SPLIT_NO_EMPTY);
	$r="<table><tr>";
	for ($i=0; $i<mb_strlen($s); $i++) { $r.="<td>".$i."</td>"; }
	$r.="</tr><tr>";
	for ($i=0; $i<mb_strlen($s); $i++) {
		// $c=mb_substr($s,$i,1);
		$c=$arr[$i];
		if ($c=="\n") $c="<b>\\n</b>";
		if ($c=="\r") $c="<b>\\r</b>";
		if ($c=="\t") $c="<b>\\t</b>";
		$r.="<td>".$c."</td>";
	}
	if ($showAsciiCodes) {
		$r.="</tr><tr>";
		for ($i=0; $i<mb_strlen($s); $i++) {
  		$c=$arr[$i];
			$r.="<td style='font-family:courier;font-size:0.8em;border:1px solid grey'>";
			if (mb_strlen($c)>1) {
        // Multi-byte characters (e.g. arabic, russian) have mb_strlen==2
        for ($j=0; $j<mb_strlen($c); $j++) {
          $r.="<span style='padding:3px; margin: 1px; background-color:#ccc'>".ord(mb_substr($c,0,$j))."</span>";
        }
      } else {
        $r.=ord($c);
      }
			$r.="</td>";
		}
	}
	$r.="</tr></table>";
	echo $r;
}

function compareStrings($a,$b, $showAsciiCodes=false) {
  $arr=[];
  $arr['A'] = preg_split('//u', $a, null, PREG_SPLIT_NO_EMPTY);
  $arr['B'] = preg_split('//u', $b, null, PREG_SPLIT_NO_EMPTY);
  $maxStrlen=max(mb_strlen($a),mb_strlen($b));
	$r="<table><tr><th>#</th>";
	for ($i=0; $i<$maxStrlen; $i++) { $r.="<td>".$i."</td>"; }
	$r.="</tr>";
  foreach ($arr as $aOrB=>$ar) {
    $r.="<tr><th>".$aOrB."</th>";
  	for ($i=0; $i<$maxStrlen; $i++) {
  		// $c=mb_substr($s,$i,1);
      if (isset($ar[$i])) {
  		  $c=$ar[$i];
    		if ($c=="\n") $c="<b>\\n</b>";
    		if ($c=="\r") $c="<b>\\r</b>";
    		if ($c=="\t") $c="<b>\\t</b>";
    		$r.="<td>".$c."</td>";
      } else {
        $r.="<th>&nbsp;</th>";
      }
  	}
    $r.="</tr>";
	  if ($showAsciiCodes) {
  		$r.="<tr>";
      for ($i=0; $i<$maxStrlen; $i++) {
        if (isset($ar[$i])) {
      		$c=$ar[$i];
    			$r.="<td style='font-family:courier;font-size:0.8em;border:1px solid grey'>";
    			if (mb_strlen($c)>1) {
            // Multi-byte characters (e.g. arabic, russian) have mb_strlen==2
            for ($j=0; $j<mb_strlen($c); $j++) {
              $r.="<span style='padding:3px; margin: 1px; background-color:#ccc'>".ord(mb_substr($c,0,$j))."</span>";
            }
          } else {
            $r.=ord($c);
          }
    			$r.="</td>";
        } else {
          $r.="<th>&nbsp;</th>";
        }
      }
      $r.="</tr>";
		}
	}
  // Results row
  $r.="<tr>";
  for ($i=0; $i<$maxStrlen; $i++) {
    // $c=mb_substr($s,$i,1);
    if (isset($arr['A'][$i]) && isset($arr['B'][$i])) {
      if ($arr['A']==$arr['B']) {
        $r.="<td>=</td>";
      } else {
        $r.="<td style='background-color:red'>X</td>";
      }
    } else {
      $r.="<th>&nbsp;</th>";
    }
  }
  $r.="</tr>";
	$r.="</table>";
	echo $r;
}

function testMail( $toAddr, $subj, $messageBody, $headers ) {
	echo "
	<div style='border:2px solid black;'>
		<p><b>testMail() called with:</b></p>
		<p>To: ".$toAddr."<br />Subject: ".$subj."</p>
		<p>HEADERS:<br />".$headers."</p>
		<p>BODY:<br />".$messageBody."</p>
	</div>
	";
	return true; // Test mails always succeed!
}

// Issue a fatal error
function error($err) { echo (isCli())?"FATAL:".$err."\n":"<p style='clear:both;color:#FF0000;'>FATAL: ".$err."</p>"; die; }

// Returns "OK" or an error message
function validateEmail($email) {
  if (strpos($email,'@')==NULL) return "must contain an @ sign ";
  if (substr_count($email,'@')>1) return "cannot contain multiple @ signs"; // stops multiple @ signs messing things up
  if (substr_count($email,'..')>0) return "cannot doubled fullstops"; // stops doubled full stops messing things
  if (!ctype_alpha(substr($email, -1))) return "last character must be letter"; // stops trailing fullstops causing issues
  if (strpos($email,'.')==NULL) return "must contain at least one '.'";
  // Shortest address is a@b.tv
  if (strlen($email)<6) return "Email address is too short!";
  for ($at=0; $at<strlen($email); $at++) {
    $c=strtoupper($email[$at]);
    // Could this char be part of an e-mail?
    if (!((ord($c)>44 and ord($c)<58) or (ord($c)>64 and ord($c)<91) or ($c=='_') or ($c=='@'))) return "Invalid character '".$c."'";
  }
  return "OK";
}

// ! also converts character codes into their real characters?
function instr($s,$what) { return (strpos(strtolower($s),strtolower($what))!==false); }
/* function asArray($csvLine) {} // USE iExplode(); instead */

// Is string in a CSV line? Substantially faster than previous version
function in($needle,$haystack) {
  if (isnull($needle)) return false;
  if (is_array($haystack)) return in_array($needle,$haystack,true);
  if ($needle===$haystack) return true;
  if (is_array($needle)) {
    trace("Needle is an array!"); test($needle); return false;
  }
  $needle=(string)$needle;
  $haystack=(string)$haystack;
  if ($needle===$haystack) return true;
  if (strpos($haystack,$needle)===false) return false; // Does not appear whatsoever
  if (strpos($haystack,',')===false) return false; // Not a CSV
  // Needle exists as a string in the haystack, but we do not know if it exists as an item in it's own right, or as part of another key e.g. in("TM","YTM,HR") should return false
  // Look at start
  if (substr($haystack,0,strlen($needle)+1)==$needle.",") return true;
  // Look in middle
  if (strpos($haystack,",".$needle.",")!==false) return true;
  // Look for at end
  if (substr($haystack,strlen($haystack)-strlen($needle)-1,strlen($needle)+1)==",".$needle) return true;
  return false;
}
// See if the fields specified in matchFields(csv) are the same in both arrays
function arraysMatch($a1,$a2,$matchFields) {
	foreach (explode(',',$matchFields) as $field) {
    if (!isset($a1[$field]) && !isset($a2[$field])) {
      // Fine - neither have it
    } else {
      $d1 = getIfSet($a1,$field);
      $d2 = getIfSet($a2,$field);
      if (!$d1 && !$d2) {
        // Fine - both blank
      } else if ($d1!=$d2) {
        // This field does not match, so the whole thing is a stinker
        return false;
      }
	  }
	}
	return true;
}

// Collapse an array into a GET request string
function arrayToUrlParams($arr) {
  $s="";
  foreach ($arr as $var=>$val) {
    if (is_array($val)) {
      $s=addTo($s,arrayToUrlParams($val), "&");
    } else {
      $s=addTo($s, $var."=".$val, "&");
    }
  }
  return $s;
}

function endsWith($s,$end) { return ((strrpos($s,$end)+strlen($end))==strlen($s))?1:0; }
// Return first key from an associative array
function getFirstKey($arr,$returnVar=0,$n=1) {
  if (!is_array($arr) || sizeOf($arr)<1) return false;
  $i=0;
  foreach ($arr as $k=>$v) {
    $i++;
    if ($i>=$n) {
      if (!$returnVar) return $k;
      return (isset($v[$returnVar]))?$v[$returnVar]:$v;
    }
  }
  return false;
}
function getFirst($arr) { if (!is_array($arr)) $arr=explode(',',$arr); if (sizeOf($arr)<1) return false; foreach ($arr as $k=>$v) { return $v; } }
function getFirstX($arr,$x) { if ($x===0) return $arr; if (!is_array($arr) || sizeOf($arr)<=$x) return $arr; $res=[]; $i=0; foreach ($arr as $k=>$v) { $res[$k]=$v; if (++$i>=$x) return $res; } return $res; }
// is the passed array two dimensional (well, is it's first value not an array at least)
function isTwoDimensional($arr) { if (!is_array($arr) || sizeOf($arr)<1) return false; foreach ($arr as $i=>$v) { return (is_array($v))?0:1; }}
// does this look like a JSON string?
function isJson($s) { return ($s && strlen($s)>0 && substr($s,0,1)=='{')?1:0; }
// Add quotes to a comma delimited list (or array, now) e.g. "one,two,three" => "'one','two','three'"
function addQuotes($s,$quot="'") { $r=""; $comma=""; if (!is_array($s)) $s=explode(',',$s); foreach ($s as $item) { $r.=$comma.fss($item,-1,false,$quot); $comma=","; } return $r; }
// Add hashes to a comma delimited list e.g. "one,two,three" => "#one,#two,#three"
function addHashes($s,$hash="#") { $r=false; $comma=""; if (!is_array($s)) $s=explode(',',$s); foreach ($s as $item) { $r=(($r)?$r." ":"").$hash.$item; } return $r; }
// Convert '#' lists into CSV e.g. "#one #two #three" => "one,two,three"
function removeHashes($s) {
  $r = str_replace("#",",",str_replace(" #",",",$s));
  // The first hash is now a surplus comma!
  if ($s && $r[0] == ',') $r = substr($r,1,strlen($r)-1);
  return $r;
}
// Crush one column of an array of records into comma-separated format
// col=-1 returns the collapsed index (keyCollapse())
function crushOut($a,$col=-1,$outputAsArray=false) {
	if (!$a) return false;
	if (!is_array($a)) return $a;
	$res=[];
	if ($col==-1) {
	  if ($outputAsArray) return array_keys($a);
    return implode(',',array_keys($a)); // NB: assume keys do not include apostrophies!
	}
  if ($outputAsArray) return array_column($a,$col);
	return implode(',',array_column($a,$col));
}

// (i)ndexed Explode - like explode() but produces an array where the key is the data, handy for lookups
// e.g. $ids=iExplode("1,2,12,45"); if (isset($ids[45])) { }
function iExplode($s,$delimiter=",") {
  $arr = explode($delimiter,$s);
	return array_combine($arr,$arr);
}

function pickOne($options) { return $options[rand(0,safeCount($options)-1)]; }

// Capitalise the first letter of a string
function initCap($s=false,$lowerTheRest=true) { if ($s && !is_object($s) && !is_array($s) && strlen($s)>0) { $theRest=substr($s,1,strlen($s)); return strtoupper(substr($s,0,1)).(($lowerTheRest)?strToLower($theRest):$theRest); } }

// Turn a sentence into a single camel-case string
function camel($s) {
	$out="";
	$s=cleanString($s);
	for($n=0;$n<strlen($s);$n++) { $out.=($n>0 && $s[$n-1]==" ")?strToUpper($s[$n]):(($s[$n]==" ")?"":strToLower($s[$n])); }
	return $out;
}

function unCamel($s) {
	$skipIt=false;
	if (strToUpper($s)==$s) return $s;
	$out="";
	if (strrpos($s,"ID")==strlen($s)-2) $s=substr($s,0,strlen($s)-2);
	// Format name better as a title by adding a space where capitals denote a different word
	for($n=0;$n<strlen($s);$n++) {
		$c=$s[$n];
    if (strToUpper($c)==$c && isset($s[$n-1]) && strToUpper($s[$n-1])!=$s[$n-1]) {
      $out.=" ";
    }
    $out.=$c;
	}
	return ucFirst($out);
}
// URL functions
function processUrl($url,$stripDir=false,$stripExt=false,$stripGet=false,$stripName=false) {
	$pos=strrpos($url,"/");
	if ($stripDir && $pos!==false) $url=substr($url,$pos+1,strlen($url)-$pos-1);
	if ($stripName && $pos!==false) $url=substr($url,0,$pos+1);
	if ($stripGet && $pos=strrpos($url,"?")) $url=substr($url,0,$pos);
	if ($stripExt && $pos=strrpos($url,".")) $url=substr($url,0,$pos);
	return $url;
}

function getScriptName($stripDir=true,$stripExt=false) {
  $processedUrl=processUrl($_SERVER['SCRIPT_FILENAME'],$stripDir,$stripExt,true);
  // is this an ajax call? in which case the originating url is more useful
  if ($processedUrl=="api.php"){
    if (p('originUrl')) return processUrl(p('originUrl'),$stripDir,$stripExt,true);
  }
  return $processedUrl;
}
function getScriptDir() { return processUrl($_SERVER['SCRIPT_FILENAME'],false,false,false,true); }
function getPageName($stripDir=true,$stripExt=false) { return processUrl($_SERVER['REQUEST_URI'],$stripDir,$stripExt,true); }
function getPageURL() {
  global $argv;
  if (isCli()) return (isset($argv) && isset($argv[0]))?$argv[0]:"CommandLineInterface";
  return processUrl($_SERVER['REQUEST_URI'],false,false,true);
}
function getPageDir() { return processUrl($_SERVER['REQUEST_URI'],false,false,true,true); }
function getFinalDir($urlOrDir) {
  if ($pos2=strrpos($urlOrDir,"/")) {
    if ($pos1=strrpos($urlOrDir,"/",-1*(strlen($urlOrDir)-$pos2)-1)) {
     return substr($urlOrDir,$pos1,$pos2-$pos1+1);
    }
  }
  return false;
}
// A kind of getServer() / getUrl() - e.g. https://secure.pupilasset.com
function getDomain($incHttp=1) {
  $domain=$_SERVER['HTTP_HOST'];
  if ($incHttp) $domain=$_SERVER['REQUEST_SCHEME']."://".$domain;
  return $domain;
}
function isDirectory($pageUrl=false) { $pageUrl=($pageUrl)?$pageUrl:getPageURL(); return (substr($pageUrl,strlen($pageUrl)-1,1)=="/"); }
function getPageUri() { return getPageURL(); } // DEPRECATED
function h1() { $name=(isDirectory())?getFinalDir(getPageURL()):getPageName(true,true); return initCap(str_replace("/","",str_replace("-"," ",$name))); } // Generate title / h1 to match URL

// Return the current url adding on a given additional parameter (e.g. you=me) and preserving the remainder of the query string
// Can also include post parameters in the query string
// Usage : <a href='".getMe('language=ENG')."'>Keep existing page but switch language</a>
function getMe($add=false,$keepGet=true,$keepPost=false,$stripDir=false,$stripExt=false) {
	$url=processUrl(getPageURL(),$stripDir,$stripExt,true);
  if ($keepGet) $url=addToUrl($url,stickPairs($_GET,"&"));
	if ($keepPost) $url=addToUrl($url,stickPairs($_POST,"&")); // POST is converted to queryString & overwrites GET
	if ($add) $url=addToUrl($url,$add);
	return $url;
}
// Add a string to a URL, glueing with ? or & and overwriting matching params
// e.g. addToUrl($url,"hi=world")
function addToUrl($fullUrl=false,$add=false,$ignoreGet=false) {
	if (!$fullUrl) $fullUrl=getPageURL();
  if (!$add) return $fullUrl;
  $querystrpos=strpos($fullUrl,'?');
  $url=substr($fullUrl,0,($querystrpos>0)?$querystrpos:strlen($fullUrl));
  if ($ignoreGet) {
		$params=mergeOptions([],((is_array($add))?$add:getPairs($add,"&"))); // New adds overwrite existing params
  } else {
		$queryStr=($querystrpos>0)?substr($fullUrl,$querystrpos+1):"";
		$params=mergeOptions(getPairs($queryStr,"&"),((is_array($add))?$add:getPairs($add,"&"))); // New adds overwrite existing params
	}
  $url=$url."?".stickPairs($params,"&");
  return $url;
}
function addParam($url,$add) { return addToUrl($url,$add); } // Legacy

// Add items to comma separated strings
function addTo($s,$add=false,$delimiter=",",$unique=false) {
  if (!$add && $add!=='0') return $s;
  // Check for multi-entry
  if (strpos($add,$delimiter)!==false) {
    foreach (explode($delimiter, $add) as $a) {
      $s = addTo($s,$a,$delimiter,$unique);
    }
    return $s;
  }
  if ($unique && in($add,$s)) return $s; // already present
  $r=((strlen($s)>0)?$s.$delimiter:"").$add;
  return $r;
}

// Remove items from comma separated strings
function removeFrom($s,$remove,$delimiter=",") {
  if (empty($remove)) return $s;
  if ($remove===$s) return "";
  if (mb_strpos($s,$remove)===false) return $s; // Does not appear whatsoever
  if (!in($remove,$s)) return $s; // Not present
  // Surgically remove
  // Look at start
  if (mb_substr($s,0,mb_strlen($remove)+1)==$remove.",") $s=mb_substr($s,mb_strlen($remove)+1,mb_strlen($s));
  // Look in middle
  if (mb_strpos($s,",".$remove.",")!==false) return str_replace(",".$remove.",",",",$s);
  // Look for at end
  if (mb_substr($s,mb_strlen($s)-mb_strlen($remove)-1,mb_strlen($remove)+1)==",".$remove) $s=mb_substr($s,0,mb_strlen($s)-mb_strlen($remove)-1);
  return $s;
}

// Return only the items in both a and b
function unionCSV($a,$b) {
  $c="";
  foreach (explode(',',$a) as $i) {
    if (in($i,$b)) $c=addTo($c,$i,',',true);
  }
  return $c;
}
// Return count of entries that appear in a CSV list
function countCsv($a) {
  if (!$a) return 0;
  return substr_count($a, ',')+1;
}
// Sort a csv numerically
function sortCsv($s) {
  $arr=explode(',',$s);
  sort($arr,SORT_NUMERIC);
  return implode(',',$arr);
}

// function countCommas($s) {$i=0; for ($n=0;$n<strlen($s);$n++) { if ($s[$n]==',') $i++; } return $i;}
// Returns true if delivering to Internet Explorer X or lower
function ie($ver="6") {
  if ($ver<5) $ver=5;
  for ($i=$ver; $i>=5; $i--) {
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE '.$i.'.') !== FALSE) return true;
  }
  return false;
}
function ie6() {return ie();}
// Force the output buffer to flush and render to the browser immediately, even mid-page
function doFlush() {
  // check that buffer is actually set before flushing
  if (ob_get_length()){ @ob_flush(); @flush(); @ob_end_flush(); }
  @ob_start();
}

function toNum($in) {
	$s=cleanNum($in);
	if ($s==="") return false;
	$i=floatVal($s);
	return (intVal($i)==$i)?intVal($i):$i;
}

function isCli() { return (php_sapi_name()=="cli" || substr(getIfSet($_SERVER,'HTTP_USER_AGENT','curl'),0,4)=='curl')?1:0; }
function isApi() {
  return (substr($_SERVER['SCRIPT_NAME'],0,4)=='/api')?1:0;
}

// 2. POSTED VARIABLE HANDLING
// ---------------------------

// Commonly used "parameter get" that pulls from _POST, _GET, CLI command line or php://input (for json)
// Return value ===false if does not exist in any
// NB: DOES NOT sanitise user input - use p() instead in most cases
$_input = false; // Global containing php://input stream to avoid need to retrieve this > once
$_JSON = false;
function p($in,$default = false,$implodeArrays = true) {
  global $argv, $_input, $_JSON;
  $p=false;
  if (isset($_POST[$in])) {
    // POST variable from an HTML form
    $p=($_POST[$in] === 'false' ? false : $_POST[$in]);
    if (is_array($p) && $implodeArrays && isset($p[0]) && safeCount($p[0])==1) {
      $p = implode(",",$p);
    }
    return $p;
  } else if (isset($_GET[$in])) {
    // URL argument (url.php?var=val)
    $p = $_GET[$in];
    if (is_string($p)) {
      $p = rawurldecode($p);
    }
  } else if (isset($_JSON[$in])) {
    // JSON variable (e.g. via a Reach Native fetch() call)
    $p = $_JSON[$in];
  } else if (isCli()) {
    // Command line argument (php program.php var=val)
    $p = cliP($in);
  } else if (!$_input) {
    if (!$_input) {
      // Process the php://input stream the first time p() is called ONLY
      $_input = file_get_contents('php://input');
      // Does the input stream contain json?
      if ($_input) {
        $_JSON = json_decode($_input, true);
      } else {
        $_input = $_JSON = []; // stop these being created again
      }
      if (isset($_JSON[$in])) {
        $p = $_JSON[$in];
      }
    }    
  } else if (isset($_POST[$in.'[]']) && is_array($_POST[$in.'[]'])) {
    $p = implode(",",$_POST[$in.'[]']);
  }
  if ($p===false || $p==='false') return $default;
  return $p;
}

// like arrP but decodes JSON into array if that appears instead
// (React Native likes to do that, thanks pal)
function jsonArrP($in, $default=false) {
  $value = arrP($in, $default);
  if (is_array($value)) return $value;
  $unfoldedValue = json_decode($value,true);
  if ($unfoldedValue) return $unfoldedValue;
  return $default;
}

// Return p(arameter) from GET or POST
// Return value ===false if does not exist in either GET or POST
// NB1: pass in comma-separated list to get an array of variables back
// NB2: DOES NOT sanitise user input - use p() instead in most cases
function rawP($in,$implodeArrays=true) {
  // $p=getIfSet($_POST,$in,getIfSet($_GET,$in,false));
  $p=false;
  if (isset($_POST[$in])) {
    $p=$_POST[$in];
  } else if (isset($_GET[$in])) {
    $p=$_GET[$in];
  }
  if ($p===false || $p=='false') {
    // get multiple parameters at once?
    if (strpos($in,",")>0) { $r=[]; foreach (explode(",",$in) as $n) { $r[$n]=rawP($n,$implodeArrays); } return $r; }
    return false;
  }
  if (isset($_POST[$in.'[]']) && is_array($_POST[$in.'[]'])) return implode(",",$_POST[$in.'[]']);
  if (is_array($p) && $implodeArrays) return implode(",",$p);
  return nvl((is_string($p))?$p:$p,false);
}

// like p() but doesn't mess arrays up
function arrP($in,$default=false) {
  $ar=rawP($in,false);
  return ($ar)?$ar:$default;
}

// Command Line (CLI) version of p(). Use e.g. php myscript.php a=dosomething b=withdata
function cliP($in,$default=false) {
  global $argv;
  if (!is_array($argv)) return $default;
  foreach ($argv as $arg) { $ar=explode('=',$arg); if (isset($ar[1]) && $ar[0]==$in) return $ar[1]; }
  return $default;
}

// get a posted number, be it int or float.
function fp($in,$default=false) { $raw = p($in); $n=toNum($raw); return ($n===false)?$default:$n; }
// Get a posted number (like an ID) and ensure it is numeric
function np($in,$default=false) { $f = fp($in,$default); return ($f!==false)?$f:$default; }
// Get a posted comma separated number string (e.g. pupilIDs="1233,4213,1245") and ensure components are numeric
function cnp($in,$returnArr=0) { $cn=false; $raw=p($in); foreach ((is_array($raw))?$raw:explode(',',$raw) as $f) { $cn=(($cn)?$cn.",":"").toNum($f); } return $cn; }
// sp makes sure SQL does not break
function sp($in,$default=false) { return (p($in)!==false)?escSQL(p($in)):$default; }

// Simplifies common task of checking whether an array key exists, then getting it. Multiple key matches can be passed as an array to 'ia'
// NB: convenient but not as fast as isset
function getIfSet($arr,$ia,$default=false) {
  if (is_array($ia)) {
    foreach ($ia as $i) {
      if (isset($arr[$i])) return $arr[$i];
    }
  } else if (isset($arr[$ia])) {
    return $arr[$ia];
  } return $default;
}

// Get a posted variable - if not from a session variable - if not set it to a default value
function pickup($var,$default=false,$andSet=false,$numeric=0) {
	$existing=getMyCache($var);
	if ($existing) $default=$existing;
	$val=p($var,$default);
	if ($numeric) $val=toNum($val);
	if ($andSet && !$existing || $existing!=$val) setMyCache($var,$val);
	return $val;
}
function npickup($var,$default=false,$andSet=false) {
  return pickup($var,$default,$andSet,1);
}
function setPickup($var,$val) {
  setMyCache($var,$val);
}
function nvl($o1=null,$o2=null,$o3=null,$o4=null) { return (notnull($o1))?$o1:((notnull($o2))?$o2:((notnull($o3))?$o3:$o4)); }

// fs (Format SQL) allows for nulls (returning NULL)
function fs($val) { return (isNum($val))?fsn($val):fss($val); }
// fss (Format SQL String) adds quotes for strings, and escapes rogue values
function fss($val,$maxLen=-1,$like=false,$quot="'") {
  if ($val===0 || $val==='0') return "'0'";
  if (isnull($val)) return "NULL";
  $s=escSQL($val);
  $s=(($maxLen!=-1)?mb_substr($s,0,$maxLen):$s);
  if ($like) $s="%".$s."%";
  return $quot.$s.$quot;
}
// fsn (Format SQL Number) escapes rogue values
function fsn($val,$maxLen=-1) {
  if ($val===false || $val===0) return 0;
  $n=cleanNum($val);
  return (isnull("".$n))?"NULL":$n;
}
// equals pre-pends " IS " for null values, "=" otherwise to a value (generally returned from fss)
function equals($val) { return (isnull($val) || $val=="NULL")?" IS NULL":"=".$val; }

// Escape chars for SQL or CSV to guard against rogue characters and SQL Injection
function escSQL($s=null) {
  global $DB;
  startFunc();
  if ($s==="0" || $s===0) return "0";
  if (!$s || isnull($s)) return "";
	$s=escUTF($s);
  $s=(isset($DB) && $DB && $DB->mysqli)?$DB->mysqli->real_escape_string("".$s):escSlashes($s);
  endFunc();
  return $s;
}

function escUTF($d) {
  if (is_array($d)) {
    foreach ($d as $k => $v) {
      $u8=escUTF($v);
      if ($u8!==$d[$k]) {
        /*
        trace("DON'T MATCH - original:");
        analyseString($d[$k]);
        trace("UTF8:");
        analyseString($u8);
        test($d,2);
        */
      }
      $d[$k] = $u8;
    }
  } else if (is_string ($d)) {
    $text = $d;
    // 1) convert á ô => a o
    $d = preg_replace("/[áàâãªä]/u","a",$d);
    $d = preg_replace("/[ÁÀÂÃÄ]/u","A",$d);
    $d = preg_replace("/[ÍÌÎÏ]/u","I",$d);
    $d = preg_replace("/[íìîï]/u","i",$d);
    $d = preg_replace("/[éèêë]/u","e",$d);
    $d = preg_replace("/[ÉÈÊË]/u","E",$d);
    $d = preg_replace("/[óòôõºö]/u","o",$d);
    $d = preg_replace("/[ÓÒÔÕÖ]/u","O",$d);
    $d = preg_replace("/[úùûü]/u","u",$d);
    $d = preg_replace("/[ÚÙÛÜ]/u","U",$d);
    $d = preg_replace("/[’‘‹›‚]/u","'",$d);
    $d = preg_replace("/[“”«»„]/u",'"',$d);
    $d = str_replace("–","-",$d);
    $d = str_replace(" "," ",$d);
    $d = str_replace("ç","c",$d);
    $d = str_replace("Ç","C",$d);
    $d = str_replace("ñ","n",$d);
    $d = str_replace("Ñ","N",$d);

    //2) Translation CP1252. &ndash; => -
    $trans = get_html_translation_table(HTML_ENTITIES);
    $trans[chr(130)] = '&sbquo;';    // Single Low-9 Quotation Mark
    $trans[chr(131)] = '&fnof;';    // Latin Small Letter F With Hook
    $trans[chr(132)] = '&bdquo;';    // Double Low-9 Quotation Mark
    $trans[chr(133)] = '&hellip;';    // Horizontal Ellipsis
    $trans[chr(134)] = '&dagger;';    // Dagger
    $trans[chr(135)] = '&Dagger;';    // Double Dagger
    $trans[chr(136)] = '&circ;';    // Modifier Letter Circumflex Accent
    $trans[chr(137)] = '&permil;';    // Per Mille Sign
    $trans[chr(138)] = '&Scaron;';    // Latin Capital Letter S With Caron
    $trans[chr(139)] = '&lsaquo;';    // Single Left-Pointing Angle Quotation Mark
    $trans[chr(140)] = '&OElig;';    // Latin Capital Ligature OE
    $trans[chr(145)] = '&lsquo;';    // Left Single Quotation Mark
    $trans[chr(146)] = '&rsquo;';    // Right Single Quotation Mark
    $trans[chr(147)] = '&ldquo;';    // Left Double Quotation Mark
    $trans[chr(148)] = '&rdquo;';    // Right Double Quotation Mark
    $trans[chr(149)] = '&bull;';    // Bullet
    $trans[chr(150)] = '&ndash;';    // En Dash
    $trans[chr(151)] = '&mdash;';    // Em Dash
    $trans[chr(152)] = '&tilde;';    // Small Tilde
    $trans[chr(153)] = '&trade;';    // Trade Mark Sign
    $trans[chr(154)] = '&scaron;';    // Latin Small Letter S With Caron
    $trans[chr(155)] = '&rsaquo;';    // Single Right-Pointing Angle Quotation Mark
    $trans[chr(156)] = '&oelig;';    // Latin Small Ligature OE
    $trans[chr(159)] = '&Yuml;';    // Latin Capital Letter Y With Diaeresis
    $trans[chr(163)] = '&pound;';    // Latin Capital Letter Y With Diaeresis
    $trans['euro'] = '&euro;';    // euro currency symbol
    ksort($trans);

    foreach ($trans as $k => $v) {
        $d = str_replace($v, $k, $d);
    }

    // 3) remove <p>, <br/> ...
    $d = strip_tags($d);

    // 4) &amp; => & &quot; => '
    $d = html_entity_decode($d);

    // 5) remove Windows-1252 symbols like "TradeMark", "Euro"...
    // $d = preg_replace('/[^(\x20-\x7F)]*/','', $d);

    $targets=array('\r\n','\n','\r','\t');
    $results=array(" "," "," ","");
    $d = str_replace($targets,$results,$d);

    //XML compatible
    /*
    $d = str_replace("&", "and", $d);
    $d = str_replace("<", ".", $d);
    $d = str_replace(">", ".", $d);
    $d = str_replace("\\", "-", $d);
    $d = str_replace("/", "-", $d);
    */

  }
  return $d;
}

// Clean Windows-1250 string to UTF-8
// From http://php.net/manual/en/function.mb-convert-encoding.php
function winToUTF8($text) {
  // map based on:
  // http://konfiguracja.c0.pl/iso02vscp1250en.html
  // http://konfiguracja.c0.pl/webpl/index_en.html#examp
  // http://www.htmlentities.com/html/entities/
  $map = [
      chr(0x8A) => chr(0xA9),
      chr(0x8C) => chr(0xA6),
      chr(0x8D) => chr(0xAB),
      chr(0x8E) => chr(0xAE),
      chr(0x8F) => chr(0xAC),
      chr(0x9C) => chr(0xB6),
      chr(0x9D) => chr(0xBB),
      chr(0xA1) => chr(0xB7),
      chr(0xA5) => chr(0xA1),
      chr(0xBC) => chr(0xA5),
      chr(0x9F) => chr(0xBC),
      chr(0xB9) => chr(0xB1),
      chr(0x9A) => chr(0xB9),
      chr(0xBE) => chr(0xB5),
      chr(0x9E) => chr(0xBE),
      chr(0x80) => '&euro;',
      chr(0x82) => '&sbquo;',
      chr(0x84) => '&bdquo;',
      chr(0x85) => '&hellip;',
      chr(0x86) => '&dagger;',
      chr(0x87) => '&Dagger;',
      chr(0x89) => '&permil;',
      chr(0x8B) => '&lsaquo;',
      chr(0x91) => '&lsquo;',
      chr(0x92) => '&rsquo;',
      chr(0x93) => '&ldquo;',
      chr(0x94) => '&rdquo;',
      chr(0x95) => '&bull;',
      chr(0x96) => '&ndash;',
      chr(0x97) => '&mdash;',
      chr(0x99) => '&trade;',
      chr(0x9B) => '&rsquo;',
      chr(0xA6) => '&brvbar;',
      chr(0xA9) => '&copy;',
      chr(0xAB) => '&laquo;',
      chr(0xAE) => '&reg;',
      chr(0xB1) => '&plusmn;',
      chr(0xB5) => '&micro;',
      chr(0xB6) => '&para;',
      chr(0xB7) => '&middot;',
      chr(0xBB) => '&raquo;',
  ];
  return html_entity_decode(mb_convert_encoding(strtr($text, $map), 'UTF-8', 'ISO-8859-2'), ENT_QUOTES, 'UTF-8');
}

// Clean an array of data to remove dodgy characters before json encoding
// Warning! This is NOT time efficient; try json_encoding FIRST
function cleanArr($arr,$traceDirty=0,$safety=0) {
  $r=[];
  if (++$safety>99) return false; // avoid infinite recursion
  if ($traceDirty && $safety>0) {
    // Is this jsonable?
    $json=json_encode($arr);
    if (!$json) {
      trace("BADCHARACTERS in:");
      test($arr);
    }
  }
  foreach ($arr as $i=>$val) {
    if (is_array($val)) {
      $r[$i]=cleanArr($val,$traceDirty,$safety);
    } else {
      // Is this jsonable?
      $json=json_encode($val);
      if (!$json) {
        $r[$i]=winToUTF8($val);
      } else {
        $r[$i]=$val;
      }
    }
  }
  return $r;
}

function restoreYoutubeIframes($str) {
	$matches = array();
	preg_match_all('/<bad\s+.*?\s+src=(".*?").*?<\/bad>/', $str, $matches);

	for ($i = 0; $i < sizeof($matches[1]); $i++) {
		$iframeSrc = $matches[1][$i]; // the src target of the iframe
		$iframeTag = $matches[0][$i]; // the full iframe tag
		if (strpos($iframeSrc, 'youtube') !== false || strpos($iframeSrc, 'youtu.be') !== false) { // find youtube links in src
			$replacement = str_replace('<bad', '<iframe', $iframeTag); // replace <bad with <iframe in replacement string
			$replacement = str_replace('</bad', '</iframe', $replacement); // replace </bad with </iframe in replacement string
			$str = str_replace($iframeTag, $replacement, $str);
		}
	}

	return $str;
}

function quickReplace($old,$new,$s) {
	return (strpos($s, $old)===FALSE)?$s:str_replace($old, $new, $s);
}

// Pass in e.g. ("Tania Bird is Sad", ["Bird"=>"Leeds","Sad"=>"Happy"])
function multiReplace($s=false,$a=false,$caseSens=true) {
  startFunc();
  if (!$s || !$a || !is_array($a)) return $s;
  foreach ($a as $old=>$new) {
  	if ($caseSens) {
  		$s=quickReplace($old, $new, $s);
  	} else {
  		$s=str_ireplace($old,$new,$s);
  	}
  }
  endFunc();
  return $s;
}

// !Room for improvement here. If a user legitimately enters a \ it will be zapped!
// Should check for escapable characters following a \ and leave in place (e.g. \\) in other cases
function escSlashes($s) { return addslashes(stripslashes($s)); }
// When putting data into <input value=''> apostrophes again need to be escaped
function escQuotes($s) { return str_replace("'","&apos;",$s); }
function escDoubleQuotes($s) { return str_replace('"','&quot;',$s); }
function escCSV($s,$enclosure='"') { return multiReplace($s,["\\".$enclosure=>"@@TIN@@", $enclosure=>"\\".$enclosure, "@@TIN@@"=>"\\".$enclosure,"&nbsp;"=>" ","&larr;"=>"<-","&rarr;"=>"->","&#8805;"=>">=","&#9439"=>"(p)","&#10034;"=>"*","&apos;"=>"'"],htmlspecialchars_decode($s)); }
function escURL($s) { return multiReplace($s,array("'"=>"%27;"," "=>"%20", "#"=>"%23", ":"=>"%3A", "/"=>"%2F", "?"=>"%3F", "="=>"%3D", "&"=>"%26")); }
function escHTML($s) { return multiReplace(htmlspecialchars_decode($s), ["&"=>"&amp;",'"'=>'&quot;',"'"=>"&apos;","è"=>"&eacute;"]); }
function escAnd($s) { return multiReplace(htmlspecialchars_decode($s), ["&"=>"and",'"'=>'&quot;',"'"=>"&apos;","è"=>"&eacute;"]); }
function escCode($s) { return multiReplace($s,array("<"=>"&lt;",">"=>'&gt;')); }
function escJSON($s) { $s=multiReplace("\n", "", "\r", "", "\t", "", $s); return escHTML($s); }
function escFile($s) { $s=str_replace(" ", "_", $s); return $s; /* cleanString($s,array("-","_","/",".")); */ }
function escVar($s) { return cleanString(camel($s)); }
function escC($s) { return strtolower(cleanString($s)); } // Leaves just lowercase letters and numbers
// function escSMS($s) { $clean=cleanString($s,["\\","\/","&"," ","\\n","'",'"',"#","\$","£","+","?","."]); return multiReplace($clean, ["%"=>"%25", "/"=>"%2F", "&"=>"%26"," "=>"%20","\\n"=>"%20","'"=>"%27", "+"=>"%2B", "#"=>"%23", "\\\\"=>"%5C", "\\"=>"%5C"]); }
function buttonPressed($inBtnText="*", $strict=false) {
	if ($inBtnText=="*") return (p("submitBtn"))?true:false;
  if ($strict) return (p("submitBtn")==$inBtnText || p("submitbtn")==$inBtnText);
  return (strpos(strtoupper(p("submitbtn")),strtoupper($inBtnText))!==false || strpos(strtoupper(p("submitBtn")),strtoupper($inBtnText))!==false);
}
function esc($s,$badC="'") { $goodC="\\".$badC; return str_replace($badC,$goodC,$s); }

// 3. FORMATTING ROUTINES
// ----------------------

// Produce a json_encode() style string, but formatted with human readable indentation
function formattedJsonEncode($arr,$depth=0) {
	$tabSize=2; // Number spaces to pad indentation
	if ((!$arr || isnull($arr)) && $arr!==0) return 'false';
	if (!is_array($arr) && isNum($arr)) {
		return $arr;
	} else if (!is_array($arr)) {
		return '"'.esc($arr,'"').'"';
	}
	$j="{\n"; $i=0;
	$depth++;
	foreach ($arr as $var=>$val) {
		if ($i>0) $j.=",\n";
		$i++;
		$j.=repeat(" ",$depth*$tabSize).'"'.$var.'":'.formattedJsonEncode($val,$depth);
	}
	$depth--;
	$j.="\n".repeat(" ",$depth*$tabSize)."}";
	return $j;
}

// Turns a string into a number unique to that string (I'm sure a more mathematically elegant approach exists for this)
// function makeID($s) {$num='0'; for ($n=0;$n<strlen($s);$n++) { $c=ord(substr($s,$n,1)); $num.=$c; } return $num;}
// Alternatively use md5(uniqid());
function generateRandomCode($size) { $code=""; for ($n=0;$n<$size;$n++) { $asciiCode=(rand(1,2)==1)?rand(48,57):rand(65,90); $code.=chr($asciiCode); } return $code; }
// function formatAsLink($url) { return (strpos($url,'http')>-1)?$url:"http://".trim($url); }
// function doubleSpace($s) {$out=""; for ($n=0;$n<strlen($s);$n++) { $out.=$s[$n].(($n<strlen($s)-1)?"&nbsp;":""); } return $out;}
// function trueCase($s) {if (notnull($s)) {$str=strToLower($s); $str[0]=strToUpper($str[0]); return $str;} else {return false;}}
// function stripP($c) { if (substr($c,0,3)=="<p>" && substr($c,strlen($c)-4,4)=="</p>") return substr($c,3,strlen($c)-7); else return $c; }

function startsWith($hay,$needle) { return (substr($hay,0,strlen($needle))==$needle); }

// locate() extracts the numeric information from a posted var, e.g. locate("pupilID","fieldForpupilID12subjectID2") gives 12
// Returns false if not found. If you're expecting 0 to be valid, check the output of this using ===false to use that 0
function locate($subField,$fullField) {
	$subField=strToLower($subField);
	if (strpos(strToLower($fullField),$subField)===false) return false;
	$startPos=strpos(strToLower($fullField),$subField)+strlen($subField);
	$num="";
	$endPos=$startPos;
	if (!isset($fullField[$endPos]) || (!charIsNum($fullField[$endPos]) && $fullField[$endPos]!="-")) return false; // not a numeric value; subField probably matched part of another field
	while ($endPos<strlen($fullField) && (charIsNum($fullField[$endPos]) || $fullField[$endPos]=="-")) {
		$num.=$fullField[$endPos++];
	}
	return ($num=="")?true:(int)$num;
}


/* MULTIBYTE STRING FUNCTIONS */
// non-multibyte string functions are about 10 times quicker than mb_ functions, but ONLY work with standard single-byte ASCII strings
// if you can re-encode your string as ASCII without losing too much information, then do so!
function safe_strlen($string, $asciiSafe = false) {
    if (!$asciiSafe) return mb_strlen($string);
    return strlen($string);
}

function safe_substr($string, $start, $length=false, $asciiSafe = false) {
    if (!$asciiSafe) return mb_substr($string, $start, $length);
    return substr($string, $start, $length);
}

function safe_strpos($haystack, $needle, $offset = 0, $asciiSafe = false) {
    if (!$asciiSafe) return mb_strpos($haystack, $needle, $offset);
    return strpos($haystack, $needle, $offset);
}

function safe_trim($str, $asciiSafe = false) {
  if (!$asciiSafe) return preg_replace("/(^\s+)|(\s+$)/us", "", $str);
  return trim($str);
}

// mb_substr Delimiter, sectionNum is 0-based e.g. mb_substrd("list.filter.zone", 2) => "zone"
function mb_substrd($s, $sectionNum=0, $delimiter=".") { $bits=explode($delimiter,$s); return (isset($bits[$sectionNum]))?$bits[$sectionNum]:false; }

// trim() is perfectly fine in most cases
function trimSpace($s) {
  if (is_array($s)) return "";
	if (isnull($s)) return "";
	// Also kill LFs
	$s=str_replace(chr(10),"",$s);
	if (isnull($s)) return "";
	return trim($s);
	/*
	$a=-1;
	$b=strlen($s)+1;
	while ($s[++$a]==" ");
	while ($s[(--$b)-1]==" ");
	return substr($s,$a,$b-$a);
	*/
}

// Automatically chose which character to use for quotes by examining the first letter of a string
//$quick ONLY works on ASCII strings - re-encode your string first to make use of speed improvements
function getQuoteChr($s) {
    return (strlen($s)>0)? (($s[0]=="'")?"'":'"'):'"';
}
// Takes a csv list and puts commas around the entries

// Is $s at $pos inside or outside of a quoted section?
//$quick ONLY works on ASCII strings - re-encode your string first to make use of speed improvements
function insideQuote($s, $pos=false, $quoteChr=false, $quick = false) {
	$quoteChr=($quoteChr)?$quoteChr:getQuoteChr($s);
  if (isnull($s) || $pos===0 || strpos($s, $quoteChr)===false) return 0; // Quick exit
  $s=str_replace("\\\\".$quoteChr,$quoteChr,$s); // Save double escaped quotes from death
  $s=str_replace("\\".$quoteChr,"",$s); // Kill escaped quotes
  // Count the quotes up to pos in a string - an odd number tells you that you are inside an escaped section
  $numQuotes=substr_count($s,$quoteChr,0,(($pos>0)?$pos:strlen($s, $quick)));
}

// Hunt for the given string in array of strings, returning the array index
function getIndexFor($toFind,$arr,$allowFuzzy=true) {
	if (!is_array($arr)) return false;
	if (isset($arr[$toFind])) return $toFind;
	if (in_array($toFind,$arr)) return array_search($toFind, $arr);
  foreach ($arr as $i=>$title) {
    if (strToLower($toFind)==strToLower($i) || strToLower($toFind)==strToLower($title)) return $i;
    if ($allowFuzzy && strpos(strToLower($title),strToLower($toFind))!==false) return $i;
  }
  return false;
}

// search for needle(s) (CSV or array) inside string, returning the matching needle e.g. has("£49.99","$,£,&pound;,$euro") == "£"
function has($s,$needles) {
  $stack=(is_array($needles))?$needles:explode(",",$needles);
  foreach ($stack as $suspect) {
    if (strpos($s,$suspect)!==false) return $suspect;
  }
  return false;
}

// Chew sequentially to $delimiter, ignoring those that appear in quotes
// Shortened $s DOES NOT include the delimiter, nor does the discarded return string
//$quick ONLY works on ASCII strings - re-encode your string first to make use of speed improvements
function getNextStringBlock(&$s,$delimiter=",",$quoteChr=false, $quick = false) {
    if (strpos($s, $delimiter)===false) {
        $res=$s;
        $s="";
        return $res;
    } // Not found. Return whole string
    $safety=0; $pos=0; $res="";
    if (!$quoteChr) $quoteChr='"'; // only detect blocks inside double quotes (") which stops apostrophes triggering (e.g. James's boat)
    while (safe_strlen($s, $quick)>0 && $safety++<999) {
        $pos=safe_strpos($s, $delimiter, $pos, $quick);
        if ($pos===false) { $res.=$s; $s=""; return trim($res); }
        if (insideQuote($s,$pos,$quoteChr, $quick)) {
            $pos++; // This occurence of delimiter was enclosed in quotes, so move along to the next...
        } else {
            $res=safe_substr($s, 0, $pos, $quick); // The between-tag content
            $s=safe_substr($s, $pos+safe_strlen($delimiter, $quick), safe_strlen($s, $quick), $quick);
            return $res;
        }
    }
    return $res; // Shouldn't ever reach this(!)
}

// Determine which comes first in the given string s - a or b
function strClosest($s,$a,$b) {
  $posA=strpos($s,$a);
  $posB=strpos($s,$b);
  $closest=($posB===false || $posB==$posA || ($posA!==false && $posB>$posA))?$a:$b;
  return $closest;
}

// Removes all HTML or other mark-up from a block of text
// Empty killList/keepList nukes all tags
function zapTags($in, $ot="<", $ct=">", $killList=false, $keepList=false, $escapeContent=0, $cleanseAttr=1) {
    $allowAttributes="href,class,id,style,bgcolor,align,valign,colspan,rowspan,width,height";
    $text=false;
    $safety=0;
    $insideTag=false;
    $s=$in;
    if ($killList && !is_array($killList)) {
        $killList=iExplode($killList);
    }
    if ($keepList && !is_array($keepList)) {
        $keepList=iExplode($keepList);
    }

    while (mb_strlen($s)>0 && $safety++<9999999) {
        if ($insideTag) {
            $fullTag=getNextStringBlock($s, $ct);
            $shortTag=$fullTag;
            if (mb_substr($fullTag, 0, 1)=="/") {
                $shortTag=mb_substr($shortTag, 1, mb_strlen($shortTag));
            }
            if (mb_strpos($fullTag, " ")!==false) {
                $shortTag=mb_substr($shortTag, 0, mb_strpos($shortTag, " "));
            } // just the "a" from a href=...
            $kill=true; // Everything buys it by default
            if ($keepList) {
                $kill=!(isset($keepList[$shortTag]));
            } else if ($killList) {
                $kill=isset($killList[$shortTag]);
            }

            if (!$kill) { // Allow this tag through
                $tag=$shortTag;
                if ($cleanseAttr===1) {
                    // Only allow certain attributes - kills potentially malicious JS e.g. onClick=''
                    if ($shortTag=="a") {
                        $href=attr($fullTag, 'href');
                        if ($href) {
                            $tag=$tag." href=".$href;
                        }
                    } else if ($shortTag=="img") {
                        $src=attr($fullTag, 'src');
                        $width=toInt(attr($fullTag, 'width'));
                        $height=toInt(attr($fullTag, 'height'));
                        if ($src) {
                            $tag.=" src=".$src;
                        }
                        if ($width) {
                            $tag.=" width=".$width;
                        }
                        if ($height) {
                            $tag.=" height".$height;
                        }
                    } else if ($shortTag=="iframe") {
                        $src=attr($fullTag, 'src');
                        $width=toInt(attr($fullTag, 'width'));
                        $height=toInt(attr($fullTag, 'height'));
                        $allow=attr($fullTag, 'allow');
                        if ($src && mb_strpos($src, 'youtube')!==false) { // Only allow YouTube embeds
                            $tag=$tag." src=".$src;
                            if ($width) {
                                $tag.=" width=".$width;
                            }
                            if ($height) {
                                $tag.=" height=".$height;
                            }
                            if ($allow) {
                                $tag.=" allow=".$allow;
                            }
                        }
                    }
                    // Preserve classes / ids etc + all data-* for all tags
                    $attributes=getPairs($fullTag, " ");
                    foreach ($attributes as $var=>$val) {
                        if (in($var, $allowAttributes) || substr($var, 0, 5)=='data-') {
                            $tag.=" ".$var."=".$val;
                        }
                    }
                } else {
                    $tag = (mb_substr($fullTag, 0, 1) == "/") ? mb_substr($fullTag, 1) : $fullTag;
                }
                if (mb_strpos($tag, 'script')!==false) {
                    trace("script not allowed in tag");
                } else {
                    $close=(mb_substr($fullTag, 0, 1)=="/")?"/":"";
                    $text.=$ot.$close.$tag.$ct; // e.g. </h2>
                }
            } else {
                $text .= ' '; //replace disallowed tag with a space
            }
        } else {
            $block=getNextStringBlock($s, $ot);
            if ($escapeContent) {
                $block=htmlspecialchars($block, ENT_QUOTES, 'UTF-8');
            }
            $text = (($text)? $text : "") . $block;
        }
        $insideTag=!$insideTag;
    }
    return $text;
}

function attr($tag,$attr) {
  $attributes=getPairs($tag," ");
  return (isset($attributes[$attr]))?$attributes[$attr]:false;
}

// many horrible characters seem to get added to grid descriptions which break everything when sending via API
// this should get rid of most of those
function removeUnicodeHorribleness($input) {
	ini_set('mbstring.substitute_character', "none");
	return mb_convert_encoding($input, 'UTF-8', 'UTF-8');
	//return iconv('UTF-8', 'ASCII//IGNORE', $input); //'ASCII//TRANSLIT' would try and find approximate equiv. //IGNORE just drops bad chars
}

function htmlToTxt($html,$replaceWith="\n") {
	$html=str_replace("<br />",$replaceWith,$html);
	$html=str_replace("</tr>","</tr>".$replaceWith,$html);
	return zapTags($html);
}


// Snap a string at position of first $c, returning either [l]eft or [r]ight portion
//$quick ONLY works on ASCII strings - re-encode your string first to make use of speed improvements
function strL($s, $c, $quick = false) {
    if (strpos($s, $c)===false) return $s;
    return safe_substr($s, 0, safe_strpos($s, $c, 0, $quick), $quick);
}
function strR($s, $c, $quick = false) {
    if (strpos($s, $c)===false) return false;
    return safe_substr($s, safe_strpos($s, $c, 0, $quick)+1, safe_strlen($s, $quick), $quick);
}

function between($i,$a,$b) { return ($a<=$i && $i<=$b) || ($b<=$i && $i<=$a); }

// Deconstructs a string containing XML (or pure XML) into an array in a more forgiving way to simplexml_load_string()
// Coarsley pulls the tags out of a string and returns an array of tags with info about each
// Although a flat array, the tree structure is implied by the ordering of the tags, and could be rehydrated
//$quick ONLY works on ASCII strings - re-encode your string first to make use of speed improvements
function ripTags($s,$openC='<',$closeC='>',$alsoCollectContent=true, $quick = false) {
    if (!$s || strlen($s)<1) return [];
    if ($quick) $s = mb_convert_encoding($s, 'ASCII');
    $tags=[];
    $safety=0;
    $inside=false; // (mb_substr($s,0,mb_strlen($openC))==$openC)?1:0;
    $tmp="";
    $fullLen=safe_strlen($s, $quick);
    $pos=0;
    while (safe_strlen($s, $quick)>0 && $safety++<999999) {
        if ($inside) {
            if (startsWith($s,"!--")) { // Handle HTML comments
                $fullTag=getNextStringBlock($s, "-->", $quick);
                array_push($tags,["tag"=>'!--',"fullTag"=>$fullTag,"closingTag"=>1,"content"=>""]);
            } else {
                $fullTag=getNextStringBlock($s, $closeC, $quick); // without the open/closing delimiters
                $t=[
                    'fullTag'=>$fullTag,
                    'tag'=>safe_substr($fullTag, 0, (safe_strpos($fullTag, " ", 0, $quick)!==false)?safe_strpos($fullTag, " ", 0, $quick):safe_strlen($fullTag, $quick), $quick), // split off XML style parameters
                    'closingTag'=>($fullTag[0]=='/' || $fullTag[safe_strlen($fullTag, $quick)-1]=='/')?1:0,
                    'pos'=>$pos-1 // From the opening character (e.g. the '<' or '[')
                ];

                if (safe_strpos($t['tag'], ':', 0, $quick)!==false) { // Split off PAML commands
                    $t['cmd']=strL($t['tag'], ':', $quick);
                    $t['tag']=strR($t['tag'], ':', $quick);
                }
                if ($alsoCollectContent) $t['content']=$tmp;
                array_push($tags, $t);
            }
        } else {
            $tmp=getNextStringBlock($s, $openC, $quick); // Throw away the content
            $pos=$fullLen-safe_strlen($s, $quick);
        }
        $inside=!$inside;
    }
    if ($safety>=999999) trace("WARNING: ripTags ran out of road");
    return $tags;
}

// used by pattr in teacher app to remove inline styles that mess stuff up
function ripInlineStyle($input) {
	return preg_replace('/(<[^>]+) style=".*?"/i', '$1', $input);
}

// Tabulates an array of HTML strings
// e.g. lists of checkboxes/radio buttons etc. can get pretty unwieldly with lots of entries
// so pass tabulate(["<input type='checkbox' />","<input type='checkbox' />"]);
// ratio=1 produces a square, ratio=2 a double length vertical etc. or alternatively pass in numCols
function tabulate($items=null, $ratio=2, $layout='V', $numCols=false, $tableClass='plain') {
  if (!isset($items) || safeCount($items)==0) return "";
  $cols=($numCols)?$numCols:ceil(sqrt(safeCount($items)/$ratio));
  $width=floor(100/$cols);
  $rows=ceil(safeCount($items)/$cols);
  $r="<table class='".$tableClass."'>";
  $n=0;
  for ($row=1;$row<=$rows;$row++) {
    $r.="<tr>";
    for ($col=1;$col<=$cols;$col++) {
      // Pick n so that consecutive items appear in the same columns
      $p=($layout=='V')?(($col-1)*$rows)+$row-1:$n;
      $r.="<td valign='top' style='width:".$width."%; line-height:1.1em;'>".((isset($items[$p]))?$items[$p]:"&nbsp;")."</td>";
      $n++;
    }
    $r.="</tr>";
  }
	return $r."</table>";
}

function lpad($s,$numChars,$padChar) { return mb_substr(str_pad($s,$numChars,$padChar,STR_PAD_LEFT),0,$numChars); }
function rpad($s,$numChars,$padChar) { return mb_substr(str_pad($s,$numChars,$padChar,STR_PAD_RIGHT),0,$numChars); }
function repeat($s,$n) { $r=""; for($m=0;$m<$n;$m++) { $r.=$s; } return $r; } // Good for pre-padding e.g. repeat(" ",4);
// Removes the top and the tail of a string e.g. ['hi there']=>[hi there]
function topAndTail($s,$top,$tail=false) { $tail=($tail)?$tail:$top; return mb_substr($s,(($s[0]==$top)?1:0),mb_strlen($s)+(($s[mb_strlen($s)-1]==$tail)?-1:0)); }

// Collapses an HTML table into an array of rows, each being an array of cells, e.g. array( [], [])
function htmlToCSV($s, $delimiter=",", $enclosure='"') {
  $c=""; $tr=""; $td=""; // (t)able, (r)ow, (c)ell strings
  $arr=ripTags($s);
  $openTags=array('table'=>0,'tr'=>0,'td'=>0);
  $ignoring=false; // highest level tag being ignored - skips all until /tag is reached
  $inTable=$inRow=$inCell=0;
  $writing=false; $colspan=1;
  $rowCount=0;
  foreach ($arr as $tag) {
    if (!$tag['closingTag']) {
      // Opening Tag
      if (!isset($openTags[$tag['tag']])) $openTags[$tag['tag']]=0;
      $openTags[$tag['tag']]++;
      $attributes=getPairs($tag['fullTag']," ");
      if (!$ignoring && isset($attributes['class']) && has($attributes['class'],array('btn','notData'))) $ignoring=$tag['tag']; // Start ignoring content
      if (in($tag['tag'],"td,th")) {
        $writing=true;
        $colspan=(isset($attributes['colspan']))?topAndTail($attributes['colspan'],"'"):1;
      }
    } else {
      // Closing Tag
      $equivalentOpenTag=mb_substr($tag['tag'],1,mb_strlen($tag['tag']));
      if (isset($openTags[$equivalentOpenTag])) $openTags[$equivalentOpenTag]--;
      if ($writing && !$ignoring && notnull($tag['content'])) $td.=escCSV($tag['content']);
      if ($ignoring && $tag['tag']=="/".$ignoring) $ignoring=false; // Stop ignoring content
      if ($tag['tag']=="/tr") { $tr.="\n"; $c.=$tr; $tr=""; $rowCount++; } // End of line
      if (in($tag['tag'],"/td,/th")) { for ($i=0; $i<$colspan; $i++) { $tr.=((isnull($tr))?"":$delimiter).$enclosure.escCSV($td,$enclosure).$enclosure; } $td=""; $writing=false; } // End of td cell
      if ($tag['tag']=="/table") { $c.="\n\n"; /* let it roll through to another */ }
      if ($tag['tag']=="br") $td.=" ";
    }
  }
  return $c;
}

// Function cribs a blurb to produce a preview paragraph, with an optional link to the full thing
// more=1 gives js expand, more=2 gives js tooltip
function cribBlurb($blurb,$max,$more=0) {
	if (strlen($blurb)<=$max) return $blurb;
	// The trick here is to not count anything inside a tag, and not cut the content off inside a tag thus rendering the rest of the page screwed...
	$i=$max;
	$c=$raw="";
	$tags=ripTags($blurb);
	if ($tags) {
		foreach ($tags as $tag) {
			$content=$tag['content'];
			$raw.=$content;
			// Do we have space for all the new content?
			if (($i-strlen($content))>0) {
				$c.=$content;
				$i-=strlen($content);
			} else if ($i>0) { // We can fit _some_ content in...
				$content=substr($content,0,$i);
				$cutAt=strrpos($content," "); // rollback to a natural break
				$c.=substr($content,0,$cutAt);
				$i=0;
			}
			// Smash tags back in
			$c.="<".$tag['fullTag'].">";
		}
	} else { // Just text
		$c=substr($blurb,0,$max);
		$cutAt=strrpos($c," "); // rollback to a natural break
		$c=substr($c,0,$cutAt);
		$i=0;
	}
	if ($more===0) {
		if ($i===0) $c.="...&nbsp;";
		return $c; // We're done
	}	else if ($more==1) {
		$c.="<a href='#' onClick='$(this).parent().parent().find(\".blurb\").show(300); $(this).parent().hide(300); return false;'>...&nbsp;</a>";
	} else if ($more==2) {
		$c.="<span class='tt pointer' title='".escHTML($raw)."'>...&nbsp;</span>";
	}
	return "<span class='cribBlurb'><span class='cribbed'>".$c."</span><span class='blurb' style='display:none;'>".escHTML($raw)."</span></span>";
}

function matchOne($src,$chars) {
  foreach (explode(",",$chars) as $possible) { if (strpos($src,$possible)!==false) return $possible; }
  return false;
}
// Adds a prefix to a name, initCapping to retain camelCase e.g. addPrefix('startDate','meals') > mealsStartDate
function addPrefix($s,$pre=false) {
  if (!$pre) return $s;
  if (!$s) return false;
  return $pre.strToUpper(substr($s,0,1)).substr($s,1);
}

// 4. DATE FUNCTIONS
// -----------------
function getTime() { return date("H:i"); }
function sqlTime() { return date("H:i:s"); }
// UNIX timestamp
function timestamp() { return $_SERVER['REQUEST_TIME']; }
function sqlNow() { return date("Y-m-d"); }
function sqlNowTime() { return date("Y-m-d H:i:s"); }
function removeSecs($slashDate) { return substr($slashDate,0,strrpos($slashDate,":")); }
function guessYear($yr) {
	$yr=ltrim($yr,'0'); // remove extra 0s at start
	if (strlen($yr)==1) $yr="0".$yr;
	if (strlen($yr)==2) {
		if ($yr<=21) {
			return "20".$yr;
		} else {
			return "19".$yr;
		}
	}
	return $yr;
}
function guessMonth($month) {
  $months=array('jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12);
  if (isnum($month) && $month>0 && $month<=12) return $month;
  return (isset($months[strtolower(substr($month,0,3))]))?$months[strtolower(substr($month,0,3))]:false;
}

// Full list of format options http://uk.php.net/date. Or use convertDate()
  function formatDate($inSqlDate=null,$format="D jS M Y") {
    if (isnull($inSqlDate)) return ""; // $inSqlDate=sqlNow();
    return date($format,convertDate($inSqlDate,"U"));
  }

  function formatTime($inSqlDate=null,$format="H:i") {
    if (isnull($inSqlDate)) return "";
    $d = new DateTime($inSqlDate);
    return $d->format($format);
  }
  
function formatSeconds($seconds) {
  $c = "";
  $hours = intval(intval($seconds) / 3600);
  if($hours > 0) $c .= $hours." hours ";
  $minutes = bcmod((intval($seconds) / 60),60);
  if($hours > 0 || $minutes > 0) $c .= $minutes." minutes ";
  $seconds = bcmod(intval($seconds),60);
  $c .= $seconds." seconds";
  return $c;
}

// 4.1 DATE/TIME CONVERSION

// String represents a YYYY-MM-DD date
function isDate($s) {
  return (DateTime::createFromFormat('Y-m-d', $s)!==FALSE)?1:0;
}

// Used to be called valid_time
function isTime($s) {
  // Wh1T3h4Ck5's original function
  // return preg_match('/^(0?\d|1[0-2]):[0-5]\d\s(am|pm)$/i', $value);
  // James Leeds' function (because sometimes you get ms as well)
  return preg_match("/^([0-1][0-9]|[2][0-3]):([0-5][0-9])(:[0-9][0-9])?$/", $s);
}

function isToday($someSortOfDate) {
  return (convertDate($someSortOfDate, '_', false, true) == sqlNow())?1:0;
}

// is a date between two others?
function inDate($startDate, $endDate, $theDate=false) {
	$theDate = sqlNow();
	return ((!$startDate || $startDate <= $theDate) && (!$endDate || $endDate >= $theDate))? 1 : 0;
}

// e.g. secondsBetween('2007-09-01 04:10:58','2012-09-11 10:25:00')
function secondsBetween($dateTimeA,$dateTimeB) {
  $d1 = new DateTime($dateTimeA);
  $sinceStart = $d1->diff(new DateTime($dateTimeB));
  $mins = $sinceStart->days * 24 * 60;
  $mins += $sinceStart->h * 60;
  $mins += $sinceStart->i;
  $secs = ($mins*60)+$sinceStart->s;
  return $secs;
}

// Universal date conversion. The aspiration : you can chuck anything at this baby!
// Valid types: "-"=SQL, "/"=Slash (nice), " "=Space, "U"=Unix timestamp
// NOTE 1: srcType is worked out automatically
// NOTE 2: UNIX timestamps are limited to 1901 on Linux and 1970 on Windows! (I know, crazy!)
function convertDate($src,$outType=false,$srcType=false,$killTime=false) {
	if (!isset($src) || isnull($src) || $src==0) return null;
	// trace("Converting date ".$src." to ".$outType);
  $date=$time=false;
	$hr=0; $min=0; $sec=0;
	if (is_array($src)) {
    if (isset($src['date'])) {
      $src=$src['date'];
    } else {
      return false;
    }
  }
	$src=trim($src);
	// Split out time
	if (strpos($src,":")!==false && strpos($src," ")!==false) {
		$date=strL($src," ");
		$time=strR($src," ");
	} else {
		$date=$src;
	}
	if ($time=="00:00:00") $time=false;
	// Expand YYYYMMDD to YYYY-MM-DD
	if (strlen($date)==8 && isnum($date)) $date=substr($src,0,4)."-".substr($src,4,2)."-".substr($src,6,strlen($src));
	// Deconstruct the src...
	if (isnum($date) || $srcType=='U') {
		$srcType='U';
		$y=date("Y",$src);
		$m=date("m",$src);
		$d=date("d",$src);
		$hr=date("H",$src);
		$min=date("i",$src);
		$sec=date("s",$src);
		$time=$hr.":".$min.(($sec)?":".$sec:"");
	} else {
		$srcDelimiter=matchOne($date,"\\,/,., ,-");
		if (!$srcDelimiter) return false;
		$bits=explode($srcDelimiter,$date);
		if (in($srcDelimiter,"\\,/") || strlen($bits[0])==2 && strlen($bits[2])==4 || !isnum($bits[1])) {
			// Date is in DD/MM/[YY]YY order
			$d=$bits[0];
			$m=guessMonth($bits[1]);
			$y=guessYear($bits[2]);
		} else {
			// Date is in YY-MM-DD order
			$y=guessYear($bits[0]);
			$m=$bits[1];
			$d=$bits[2];
		}
	}

	// Reconstruct the out...
	$d=str_pad($d,2,"0",STR_PAD_LEFT);
	$m=str_pad($m,2,"0",STR_PAD_LEFT);
	switch ($outType) {
	case "K":
		$date=$y.$m.$d;
		break;
	case "U":
		return mktime($hr,$min,$sec,$m,$d,$y);
		break;
	case " ":
	case "/":
		$date=$d.$outType.$m.$outType.$y;
		break;
	case "-":
		$date=$y.$outType.$m.$outType.$d;
		break;
	default:
		$date=$d."/".$m."/".$y;
		break;
	}
	// trace("Done: converted date [".$src."] to Type:[".$outType."]=[".$date."]");
	return $date.(($time && !$killTime)?" ".$time:"");
}

function justDate($datetime,$tForTime=false) { // groupcall datetime comes in with a "T" rather than a space
	if ($tForTime) {
		if (strpos($datetime,"T")===false) return $datetime;
		return strL($datetime,"T");
	} else {
		if (strpos($datetime," ")===false) return $datetime;
		return strL($datetime," ");
	}

}
function justTime($datetime,$tForTime=false) {
	if ($tForTime) {
		if (strpos($datetime,"T")===false || strpos($datetime,"T")==strlen($datetime)) return null;
    $time=strR($datetime,"T");
		return ((strlen($time)>8)?substr($time,0,8):$time);
	} else {
		if (strpos($datetime," ")===false || strpos($datetime," ")==strlen($datetime)) return null;
    $time=strR($datetime," ");
    return ((strlen($time)>8)?substr($time,0,8):$time);
	}
}
function justDateTime($datetime) {
	if (strpos($datetime,".")===false) return $datetime;
	if (strpos($datetime,"T")!==false) $datetime=str_replace("T"," ",$datetime);
	return strL($datetime,".");
}

function dateDiff($start,$end) {
	$date1=new DateTime($start);
	$date2=new DateTime($end);
	$interval=$date1->diff($date2);
	return $interval->days;
}

// a nice, plurised, suffixed summary of how many days, months and years something is away/ago
function dateDifference($firstDate,$secondDate,$suffixPrefix=true,$exclamationMark=true) {
  $interval=$firstDate->diff($secondDate);
  $string=$suffix=$prefix="";
  $exclamationMark=(($exclamationMark)?"!":"");
  foreach (['year','month','day'] as $diff) {
    $i=substr($diff,0,1);
    if ($i=="d" && $string=="" && $interval->$i==0) {
      $string.="today".$exclamationMark;
      $suffix="";
    } else if ($i=="d" && $string=="" && $interval->$i==1) {
      if ($interval->invert) {
        $string.="yesterday";
        $suffix="";
	    } else {
        $string.="tomorrow";
        $suffix="";
      }
    } else if ($interval->$i>=1) {
    	if ($interval->invert) {
		  	$suffix=" ago";
		  } else {
		  	$prefix="in ";
		  }
      if ($string!="") $string.=" & ";
      $string.=pluralise($interval->$i,$diff);
    }
  }
  return $prefix.$string.$suffix;
}

// Return a word depending on whether the number associated with it, e.g. 2 monkeys
function pluralise($count,$text) {
	if (!$text) return $count;
	if ($count==1 || $count==-1) return $count." ".$text;
  if (strrpos($text,"s")===strlen($text)-1) return $count." ".$text; // Already pluralised
  return $count." ".$text."s";
}

// Super-quick func to prevent double in-line conditional checking when building strings
// e.g. (($x==$y)?"<b>":"")."something that might be bold".(($x==$y)?"</b>":"") becomes simply wrap("<b>","something...","</b>",($x==$y));
function wrap($a,$b,$c,$go=false) { return ($go)?$a.$b.$c:$b; }

// 4.2 DATE/TIME MANIPULATION
// As of PHP 5.3 you can use DateTime $now->diff($date)
// Returns your age now!
function age($dob=false,$showMonths=false,$format='Y-m-d',$html=true,$pretty=false) {
  if (!$dob || $dob=='--/--/----' || $dob=='1970-01-01') return false;
  $tz=new DateTimeZone('Europe/London');
  $dt=DateTime::createFromFormat($format, $dob);
  if (!$dt) {
    trace("Cannot create date from dob string [".$dob."] format [".$format."]");
    return false;
  } else {
    $diff=$dt->diff(new DateTime('now', $tz));
  }
  if (!$showMonths) return $diff->y;
  if ($pretty){
    return $diff->y."yrs".(($diff->y<8)?wrap(" <span class='small'>",$diff->m."mths","</span>",$html):"");
  } else {
    return $diff->y.(($diff->m>0)?wrap("<span class='small'>",".".$diff->m."mths","</span>",$html):"");
  }
}

function addMonths($theDate,$m) {
  $yyyy=(int)substr($theDate,0,4);
  $mm=(int)substr($theDate,5,2);
  $dd=(int)substr($theDate,8,2);
	// Move years on/back?
	if (($mm+$m)<=12 && ($mm+$m)>0) {
		$mm+=$m;
	} else {
		$yyyy+=ceil($m/12);
		$mm=($mm+$m)%12;
		if ($mm==0) $mm=12;
	}
  // Handle different month ends / leap years
	if ($dd==31 || ($dd>28 and $mm==2)) {
		$dd=getLastDayInMonth($mm,$yyyy);
	}
	return $yyyy."-".str_pad($mm,2,"0",STR_PAD_LEFT)."-".str_pad($dd,2,"0",STR_PAD_LEFT);
}
function getLastDayInMonth($m,$year) {
  $daysInMonths = [1=>31,2=>28,3=>31,4=>30,5=>31,6=>30,7=>31,8=>31,9=>30,10=>31,11=>30,12=>31];
  $m=(int)$m;
  if ($m==2) {
    $leap = date('L', mktime(0, 0, 0, 1, 1, $year));
    return ($leap)?29:28;
  }
  return $daysInMonths[$m];
}

// Accepts YYYY-MM-DD and the number of days to offset, and returns a YYYY-MM-DD
function addDays($startdate,$d) { return ($d===0)?$startdate:date("Y-m-d", strtotime((($d>0)?"+":"").$d." day", strtotime($startdate))); }
function addYears($startdate,$y) { return date("Y-m-d",strtotime($startdate." ".$y." years")); }
function addMins($time,$mins) {
	$dt = new DateTime($time);
	if ($mins<0) {
		$dt->sub(new DateInterval('PT'.($mins*-1).'M'));
	} else {
		$dt->add(new DateInterval('PT'.$mins.'M'));
	}
	return $dt->format('H:i');
}

// Accepts YYYYMMDD and the number of business days (mon-fri) to offset, returning a YYYYMMDD
function addBusinessDays($startdate,$d) {
  if ($d==0) {
  	return $startdate;
  }
	$yyyy=(int)substr($startdate,0,4);
	$mm=(int)substr($startdate,4,2);
	$dd=(int)substr($startdate,6,2);
	$daysLeftToAdd=$d;
	$newDate=$startdate;
	// What day we startin on? (0=Sun -> 6=Sat)
	$weekday=date('w',mktime(0, 0, 0, $mm, $dd, $yyyy));
	$safety=0;
	while ($daysLeftToAdd>0 and $safety++<999) {
		// Is this an easy case that doesn't even hit a weekend?
		if (($weekday+$daysLeftToAdd)<=5) {
	  	return addDays($newDate,$daysLeftToAdd);
		}
		// finish this week off
		$nonBD=0; // num of non-business days to add
		$bd=0; // num of business days to add
		if ($weekday==1) {
			$nonBD=2;
			$bd=5;
		} else if ($weekday==0) {
			$nonBD=1;
		} else if ($weekday==6) {
			$nonBD=2;
		} else {
			$nonBD=2;
			$bd=5-$weekday;
		}
		$newDate=addDays($newDate,$bd+$nonBD);
		$daysLeftToAdd=$daysLeftToAdd-$bd;
		// It's always monday after the first time around the loop
		$weekday=1;
	}
	return $newDate;
}

// 4.3 LEGACY DATE FUNCTIONS
function timestampToNiceDate($timestamp) { return convertDate($timestamp,"/","U"); }
function timestampToNiceDateTime($timestamp,$twentyFour=true) { return (isnull($timestamp))?null:date("d/m/Y ".(($twentyFour)?"H:i":"h:i A"),$timestamp); }
function sqlToTimestamp($sqlDate=null) { return convertDate($sqlDate,"U"); }
function roughDateToTimestamp($roughDate,$roughTime=null) { return convertDate($roughDate,"U"); }
function sqlToNiceDate($sqlDate=null) { return convertDate($sqlDate,"/"); }
function sqlToNiceDateTime($sqlDateTime=null) { return convertDate($sqlDateTime,"/"); }
function toSlashDate($inDateTime=null) { return convertDate($inDateTime,"/"); }
function dbdate() { return date("Ymd"); }
function dbdatetime() { $d=getdate(); return $d["year"].str_pad($d["mon"],2,"0",STR_PAD_LEFT).str_pad($d["mday"],2,"0",STR_PAD_LEFT).str_pad($d["hours"],2,"0",STR_PAD_LEFT).str_pad($d["minutes"],2,"0",STR_PAD_LEFT).str_pad($d["seconds"],2,"0",STR_PAD_LEFT); }
function dbtime() { $d=getdate(); return str_pad($d["hours"],2,"0",STR_PAD_LEFT).str_pad($d["minutes"],2,"0",STR_PAD_LEFT).str_pad($d["seconds"],2,"0",STR_PAD_LEFT); }

// Return echo-able string with memory usage
function getMemoryUsage() {
  $bytesUsed=memory_get_usage();
  $memoryLimit=ini_get('memory_limit'); // e.g. 256M or 4G
  $num=toInt(substr($memoryLimit,0,strlen($memoryLimit)-1));
  $unit=substr($memoryLimit,strlen($memoryLimit)-1,1);
  $multipliers=["K"=>1024,"M"=>1024*1024,"G"=>1024*1024*1024];
  $byteLimit=$num*((isset($multipliers[$unit]))?$multipliers[$unit]:1);
  $mbUsed=round($bytesUsed/1024/1024);
  $mbLimit=round($byteLimit/1024/1024);
  return formatNum($mbUsed)."M / ".$memoryLimit." (".round(100*($mbUsed/$mbLimit),1)."%)";
}

// 5. CURRENCY HANDLING
// --------------------

// formatNum adds commas to a number
function formatNum($s) {
  $s=toNum($s);
  $num=number_format($s,2);
  $i=strpos($num,'.00');
  if ($i>-1 && $i==strlen($num)-3) {
    $num=substr($num,0,strlen($num)-3);
  }
  return $num;
}

function formatMoney($inAmount, $currency=false, $showPence=false) {
  if (!$showPence) {
    $num=strval($inAmount);
  } else {
    $num=strval((float)$inAmount/100);
  }
	$ret=(string)formatNum($num);
  if ($currency) {
    return getCurrencySymbol($currency).$ret;
  }
  return $ret;
}

// a as a percentage of b avoiding divide-by-zero errors
function perc($a,$b,$dp=1,$default=0,$addPerc=false) { return ($b==0)?$default:round(100*$a/$b,$dp).(($addPerc)?"%":""); }
function getCurrencySymbol($currencyCode=false) {
	$cc=($currencyCode)?$currencyCode:((isset($_SESSION['state']['me']['currency']))?$_SESSION['state']['me']['currency']:"GBP");
	switch ($cc) {
	case "GBP": case "£": case "&pound;": case "STERLING": return "&pound;"; break;
	case "EUR": case "&euro;": case "€": return "&euro;"; break;
	case "USD": case "\$": return "\$"; break;
	default: return ($currencyCode)?$currencyCode:"&pound;"; break;
	}
}

// Convert a decimal to a binary array e.g 9 => [1,8]
function decToBinArr($decimal) {
	$arr=unpack("C*", pack("L", $decimal));
	$r=[];
	foreach ($arr as $i) { $r[$i]=$i; } // Create indexed array
	return $r;
}

// 5. FILE AND IMAGE HANDLING
// --------------------------

// Convert e.g. 12G, 255M, 128K to num bytes
function toBytes($str) {
  $val=trim($str);
  $last=strtolower($str[strlen($str)-1]);
  $val=cleanNum($val);
  switch($last) { case 'g': $val*=1024; case 'm': $val*=1024; case 'k': $val*=1024; } // Note: no breaks
  return $val;
}

// Global functions/variables that might be required even before an AssetPage object exists
$validFileTypes=[
  "png"=>['type'=>'img'],
  "jpg"=>['type'=>'img'],
  "jpeg"=>['type'=>'img'],
  "gif"=>['type'=>'img'],
  "bmp"=>['type'=>'img'],
  "txt"=>['type'=>'doc'],
  "rtf"=>['type'=>'doc','icon'=>'doc.png'], // word.png
  "rtfd"=>['type'=>'doc','icon'=>'doc.png'], // word.png
  "doc"=>['type'=>'doc','icon'=>'doc.png','forceDownload'=>1], // word.png
  "docx"=>['type'=>'doc','icon'=>'doc.png','forceDownload'=>1],
  "pages"=>['type'=>'doc','icon'=>'doc.png','forceDownload'=>1],
  "dot"=>['type'=>'doc','icon'=>'doc.png','forceDownload'=>1],
  "ami"=>['type'=>'doc','icon'=>'doc.png','forceDownload'=>1],
  "csv"=>['type'=>'doc','icon'=>'unknownFile.png'], // spread.png
  "xls"=>['type'=>'doc','icon'=>'unknownFile.png','forceDownload'=>1], // spread.png
  "xlsx"=>['type'=>'doc','icon'=>'unknownFile.png','forceDownload'=>1], // spread.png
  "numbers"=>['type'=>'doc','icon'=>'unknownFile.png','forceDownload'=>1], // spread.png
  "xml"=>['type'=>'doc','icon'=>'unknownFile.png'],  // spread.png
  "ctf"=>['type'=>'doc','icon'=>'unknownFile.png'], // spread.png
  "Xnn"=>['type'=>'doc','icon'=>'unknownFile.png'], // e.g. X11 (exam files)
  "pdf"=>['type'=>'doc','icon'=>'pdf.png'],
  "ppt"=>['type'=>'doc','icon'=>'unknownFile.png','forceDownload'=>1], // presentation.png
  "pptx"=>['type'=>'doc','icon'=>'unknownFile.png','forceDownload'=>1], // presentation.png
  "mp3"=>['type'=>'doc','icon'=>'unknownFile.png'], // mp3.png
  "wav"=>['type'=>'doc','icon'=>'unknownFile.png'], // mp3.png
  "aac"=>['type'=>'doc','icon'=>'unknownFile.png'], // mp3.png
  "midi"=>['type'=>'doc','icon'=>'unknownFile.png'], // mp3.png
  "sib"=>['type'=>'doc','icon'=>'unknownFile.png'], // mp3.png
  "mov"=>['type'=>'mov','icon'=>'unknownFile.png','videoType'=>'video/mp4'], // movie.png
  "mp4"=>['type'=>'mov','icon'=>'unknownFile.png','videoType'=>'video/mp4'], // movie.png
  "m4a"=>['type'=>'mov','icon'=>'unknownFile.png','videoType'=>'video/mp4'], // movie.png
  "avi"=>['type'=>'mov','icon'=>'unknownFile.png','videoType'=>'video/mp4'], // movie.png
  "flv"=>['type'=>'mov','icon'=>'unknownFile.png','videoType'=>'video/mp4'] // movie.png
];

function getFileType($ext,$useDefault=0) {
	global $validFileTypes;
	if (substr($ext,0,1)=="X") { // Weirdo exam files
	  return $validFileTypes['Xnn'];
	}
  $defaultType=['type'=>'unk','icon'=>'unknownFile.png'];
	return (isset($validFileTypes[$ext]))?$validFileTypes[$ext]:(($useDefault)?$defaultType:false);
}

// e.g. uploadFile('thefile','i/uploads/');  OverrideName is optional
function uploadFile($inFieldName,$inDestDirectory,$inOverrideName=NULL,$overwrite=false) {
  if (is_uploaded_file($_FILES[$inFieldName]['tmp_name'])) {
    if ($_FILES[$inFieldName]['error']==0) {
      $errorMessage="OK";
      if ($inOverrideName) {
        $newFilename=$inOverrideName;
      } else {
        $newFilename=escFile($_FILES[$inFieldName]['name']);
      }
			$fullFilename=$inDestDirectory.$newFilename;
      if (!$overwrite) {
      	// Give this file a unique name
				$ext=getExt($newFilename);
				$shortName=strL($newFilename,".".$ext);
      	$counter=0;
      	while (file_exists($fullFilename) && 99>$counter++) {
      		$newFilename=$shortName.$counter.".".$ext;
		      $fullFilename=$inDestDirectory.$newFilename;
      	}
      }
      $tempFilename=$_FILES[$inFieldName]['tmp_name'];
      if (move_uploaded_file($tempFilename, $fullFilename)) {
        // Set permissions to read and write for all (but NOT execute)
        chmod($fullFilename,0666);
      } else {
        error("File $newFilename was uploaded, but could not be moved from ".$tempFilename." to ".$fullFilename);
      }
    } else {
      error("File ".$HTTP_POST_FILES[$inFieldName]['name']." could not be uploaded 2 - error #".$HTTP_POST_FILES['thefile']['error']);
    }
    return $newFilename;
  } else {
    trace("No file ".$inFieldName." or file too big");
    return " ";
  }
}

// resizeImage takes an optimum width and/or height
//	passing ONLY width preserves the aspect ratio - resizing to the given width, and vice-versa for height
// 	the resizeMethod kicks in when BOTH width and height are passed:
//		STRETCH: enforce both width and height, so given box is filled
//		TRIM or CROP: Given box is filled but aspect ratio is preserved (cropping where necessary)
//		FIT: Fit the image inside the given box, regardless whether the whole box is filled
//		CENTRE: As TRIM, but centres image
//		OVERLAP: As TRIM but leaves the full image, so that it flows outside the box if necessary
//	newFilename is optional
function resizeImage($inFilename,$inWidth=0,$inHeight=0,$newFilename="",$noResizeIfOrigSmaller=false,$quality=100,$resizeMthd='CENTRE') {
	if (isnull($inFilename) || !file_exists($inFilename) || is_dir($inFilename)) return false;
	$resizeMethod=($resizeMthd=="CROP")?"TRIM":$resizeMthd;
  // Get new dimensions
  list($origWidth, $origHeight) = getimagesize($inFilename);
  if (nvl($origWidth,0)==0) return false;
  // Leave the image alone if:
  if ($resizeMethod=="NONE"
  		|| ($inWidth==0 && $inHeight==0)
  		|| ($inWidth==$origWidth && $inHeight==$origHeight)
  		|| ($inWidth==0 && $inHeight==$origHeight)
  		|| ($inHeight==0 && $inWidth==$origWidth)
  		|| ($noResizeIfOrigSmaller and (($inWidth>0 and $origWidth<$inWidth) or ($inHeight>0 and $origHeight<$inHeight)))) {
  	if ($newFilename!="" && $inFilename!=$newFilename) {
  		// Copy the file as is
	  	copy($inFilename,$newFilename);
	  	chmod($newFilename,0666);
	  }
  	return true;
	} else {
		$origAspect=(float)($origWidth/$origHeight);
		$newWidth=$inWidth;
		$newHeight=$inHeight;
		if ($newWidth==0) {
			$newWidth=(int)((float)$newHeight*$origAspect);
			$resizeMethod="STANDARD";
		} else if ($newHeight==0) {
			$newHeight=(int)((float)$newWidth/$origAspect);
			$resizeMethod="STANDARD";
		} else {
			$targetAspect=(float)($inWidth/$inHeight);
			// resizeMethod of STRETCH happens by default
			if ($resizeMethod=="OVERLAP" || $resizeMethod=="TRIM" || $resizeMethod=="CENTRE") {
				if ($origAspect>$targetAspect) {
					// Keep the target height only
					$newWidth=(int)((float)$newHeight*$origAspect);
					if ($resizeMethod=="CENTRE") $centreX=(-1)*($newWidth-$inWidth)/2;
				} else {
					$newHeight=(int)((float)$newWidth/$origAspect);
					if ($resizeMethod=="CENTRE") $centreY=(-1)*($newHeight-$inHeight)/2;
				}
			} else if ($resizeMethod=="FITINSIDE" || $resizeMethod=="FIT") {
				if ($origAspect>$targetAspect) {
					// Keep the target width only
					$newHeight=(int)((float)$newWidth/$origAspect);
				} else {
					$newWidth=(int)((float)$newHeight*$origAspect);
				}
			}
		}
		// Load the original
		$ext=getExt($inFilename);
		$origImage=loadImage($inFilename);
		if ($resizeMethod=="TRIM" || $resizeMethod=="CENTRE") {
			$newImage=imagecreatetruecolor($inWidth, $inHeight);
		} else {
			$newImage=imagecreatetruecolor($newWidth, $newHeight);
		}
		imagecopyresampled($newImage, $origImage, (isset($centreX))?$centreX:0, (isset($centreY))?$centreY:0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
		// Save the new image
		$th=($newFilename?$newFilename:$inFilename);
		if (file_exists($th)) unlink($th);
		switch ($ext) {
		case "jpg":
		case "jpeg":
			$success=imagejpeg($newImage, $th, $quality);
			break;
		case "gif":
			$success=imagegif($newImage, $th);
			break;
		case "png":
			$success=imagepng($newImage,$th,max(0,min($quality,9)));
			break;
		}
	  if (notnull($newFilename)) {
	  	// chmod($newFilename,0666);
	  }
	  return $success;
	}
}

function loadImage($inFilename) {

  $file_dimensions = getimagesize($inFilename);
  $file_type = strtolower($file_dimensions['mime']);

  if ($file_type=='image/jpeg' || $file_type=='image/pjpeg') {
    if ($f=imagecreatefromjpeg($inFilename)) {
      return $f;
    }
  } else if ($file_type=='image/gif' || $file_type=='image/pgif') {
    if ($f=imagecreatefromgif($inFilename)) {
      return $f;
    }
  } else if ($file_type=='image/png' || $file_type=='image/ppng') {
    if ($f=imagecreatefrompng($inFilename)) {
      return $f;
    }
  }
  return false;
}

function areFilesIdentical($filename1,$filename2) {
	if (!file_exists($filename1) && !file_exists($filename2)) return true;
	if (!file_exists($filename1) || !file_exists($filename2)) return false;
	return (md5_file($filename1)==md5_file($filename2))?true:false;
}

// getBestTextColour for a given background
function chooseTextColour($backgroundColour) {
	return (isDark($backgroundColour))?"#eee":"#000";
}
function isDark($colour) { // Pass in hex colour e.g. #123321
	if (!$colour) return false;
	if (!is_array($colour)) {
		$rgb=hexToRgb($colour);
	} else {
		$rgb=$colour;
	}
	$howLight=($rgb['r']+$rgb['g']+$rgb['b'])/7.65; // How light between 0 & 100?
	return !($howLight>45 || ($howLight>20 && $rgb['g']*2>($rgb['r']+$rgb['b'])));
}
function hexToRgb($hc) {
	$hc=str_replace('#','',$hc);
	if (strlen($hc)==3) $hc=$hc[0].$hc[0].$hc[1].$hc[1].$hc[2].$hc[2]; // expand eee to eeeeee
	return ['r'=>hexdec(substr($hc,0,2)),'g'=>hexdec(substr($hc,2,2)),'b'=>hexdec(substr($hc,4,2)),'a'=>1];
}
// Supplement dechex to add leading zeros (to give valid HTML colours)
function decToHex($d) { return str_pad(dechex($d),2,'0',STR_PAD_LEFT); }
function rgbToHex($ac) { return "#".decToHex($ac['r']).decToHex($ac['g']).decToHex($ac['b']); }
function addHashToColour($hc) { if (!$hc || isnull($hc)) return false; return (strpos($hc,'#')!==false)?$hc:"#".$hc; }

/* returns a nice colour from a selection of paletteSize e.g. style='background-color: ".autoColour($n,99,'HTML').";' */
function autoColour($i,$paletteSize,$format='ARRAY',$alpha=0) {
  $deg = 360.0/$paletteSize*$i;
  $hi = floor ($deg/60);
  $f = $deg/60.0 - $hi;
  switch ($hi) {
      case 0: $r=1.0; $g=$f; $b=0; break;
      case 1: $r=(1.0-$f); $g=1.0; $b=0; break;
      case 2: $r=0; $g=1.0; $b=$f; break;
      case 3: $r=0; $g=(1.0-$f); $b=1.0; break;
      case 4: $r=$f; $g=0; $b=1.0; break;
      case 5: $r=1.0; $g=0; $b=(1.0-$f); break;
      case 6: $r=0.4; $g=0.78; $b=0.78; break; //for 100% pies.
      default: $r=0; $g=0; $b=0; break;
  }
  $arrayColour=array($r*255,$g*255,$b*255,$alpha);
  if ($format=='ARRAY') return $arrayColour;
  if ($format=='HTML') return rgbToHex(['r'=>$r*255,'g'=>$g*255,'b'=>$b*255,'a'=>$alpha]);
}
function getRandomColour($paletteSize=128) { return autoColour(rand(0,$paletteSize),$paletteSize,'HTML'); }

function autoGrey($i,$paletteSize=50,$format='ARRAY',$alpha=0) { // 50 shades by default
  // Never black, never white
  $shadeOfGrey=0.25+(($i/$paletteSize)*0.5);
  $r=(1.0-$shadeOfGrey); $g=(1.0-$shadeOfGrey); $b=(1.0-$shadeOfGrey);
  $arrayColour=array($r*255,$g*255,$b*255,$alpha);
  if ($format=='ARRAY') return $arrayColour;
  if ($format=='HTML') return rgbToHex(['r'=>$r*255,'g'=>$g*255,'b'=>$b*255,'a'=>$alpha]);
}

// Merge two array based colours
function mergeColours($colourArrays) {
  $tR=0; $tG=0; $tB=0; $tA=0;
  foreach ($colourArrays as $ca) {
    $tR+=$ca['r']/255;
    $tG+=$ca['g']/255;
    $tB+=$ca['b']/255;
    $tA+=$ca['a'];
  }
  $r=(($tR>0)?$tR/max($tR,$tG,$tB):0);
  $g=(($tG>0)?$tG/max($tR,$tG,$tB):0);
  $b=(($tB>0)?$tB/max($tR,$tG,$tB):0);
  $alpha=$tA/safeCount($colourArrays);
  return ['r'=>(int)($r*255), 'g'=>(int)($g*255), 'b'=>(int)($b*255), 'a'=>$alpha];
}

// return a complimentary shade
function lightenDarken($colour,$amount=2,$forceLighten=false) {
	if (!is_array($colour)) {
		$colour=hexToRgb($colour);
		$format='HTML';
	} else {
		$format='ARRAY';
	}
	if (isDark($colour) || $forceLighten) {
		$red=min($colour['r']*$amount+(20*$amount),255);
		$green=min($colour['g']*$amount+(20*$amount),255);
		$blue=min($colour['b']*$amount+(20*$amount),255);
		$alpha=$colour['a'];
	} else {
		$red=max($colour['r']/$amount-(20*$amount),0);
		$green=max($colour['g']/$amount-(20*$amount),0);
		$blue=max($colour['b']/$amount-(20*$amount),0);
		$alpha=$colour['a'];
	}
	$merged=['r'=>intval($red),'g'=>intval($green),'b'=>intval($blue),'a'=>$alpha];
	if ($format=='HTML') return rgbToHex($merged);
	return $merged;
}

function getNiceColour() {
	$hue=rand(1,100)/100;
	$sat=rand(25,75)/100;
	$light=rand(25,75)/100;
	return hslToRgb($hue,$sat,$light);
}

function hslToRgb($h, $s, $l){
    $r=$g=$b=0;
    if ($s == 0){
        $r = $g = $b = $l; // achromatic
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = hue2rgb($p, $q, $h + 1/3);
        $g = hue2rgb($p, $q, $h);
        $b = hue2rgb($p, $q, $h - 1/3);
    }
    return ['r'=>intval($r * 255), 'g'=>intval($g * 255), 'b'=>intval($b * 255),'a'=>1];
}

function hue2rgb($p, $q, $t){
    if($t < 0) $t += 1;
    if($t > 1) $t -= 1;
    if($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if($t < 1/2) return $q;
    if($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}

function rgbToHsl($r, $g, $b){
    $r /= 255;
    $g /= 255;
    $b /= 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $h=$s=$l = ($max + $min) / 2;
    if($max == $min) {
        $h = $s = 0; // achromatic
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        switch($max){
            case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
            case $g: $h = ($b - $r) / $d + 2; break;
            case $b: $h = ($r - $g) / $d + 4; break;
        }
        $h /= 6;
    }
    return [$h, $s, $l];
}


// Rather annoyingly, Ubuntu's GD library does not include the imagerotate function!
if (!function_exists("imagerotate")) {
	function imageRotate($src_img, $angle, $bicubic=false) {

		 // convert degrees to radians
		 $angle = $angle + 180;
		 $angle = deg2rad($angle);

		 $src_x = imagesx($src_img);
		 $src_y = imagesy($src_img);

		 $center_x = floor($src_x/2);
		 $center_y = floor($src_y/2);

		 $cosangle = cos($angle);
		 $sinangle = sin($angle);

		 $corners=array(array(0,0), array($src_x,0), array($src_x,$src_y), array(0,$src_y));

		 foreach($corners as $key=>$value) {
			 $value[0]-=$center_x;        //Translate coords to center for rotation
			 $value[1]-=$center_y;
			 $temp=[];
			 $temp[0]=$value[0]*$cosangle+$value[1]*$sinangle;
			 $temp[1]=$value[1]*$cosangle-$value[0]*$sinangle;
			 $corners[$key]=$temp;
		 }

		 $min_x=1000000000000000;
		 $max_x=-1000000000000000;
		 $min_y=1000000000000000;
		 $max_y=-1000000000000000;

		 foreach($corners as $key => $value) {
			 if($value[0]<$min_x)
				 $min_x=$value[0];
			 if($value[0]>$max_x)
				 $max_x=$value[0];

			 if($value[1]<$min_y)
				 $min_y=$value[1];
			 if($value[1]>$max_y)
				 $max_y=$value[1];
		 }

		 $rotate_width=round($max_x-$min_x);
		 $rotate_height=round($max_y-$min_y);

		 $rotate=imagecreatetruecolor($rotate_width,$rotate_height);
		 imagealphablending($rotate, false);
		 imagesavealpha($rotate, true);

		 //Reset center to center of our image
		 $newcenter_x = ($rotate_width)/2;
		 $newcenter_y = ($rotate_height)/2;

		 for ($y = 0; $y < ($rotate_height); $y++) {
			 for ($x = 0; $x < ($rotate_width); $x++) {
				 // rotate...
				 $old_x = round((($newcenter_x-$x) * $cosangle + ($newcenter_y-$y) * $sinangle))
					 + $center_x;
				 $old_y = round((($newcenter_y-$y) * $cosangle - ($newcenter_x-$x) * $sinangle))
					 + $center_y;

				 if ( $old_x >= 0 && $old_x < $src_x
							 && $old_y >= 0 && $old_y < $src_y ) {

						 $color = imagecolorat($src_img, $old_x, $old_y);
				 } else {
					 // this line sets the background colour
					 $color = imagecolorallocatealpha($src_img, 255, 255, 255, 127);
				 }
				 imagesetpixel($rotate, $x, $y, $color);
			 }
		 }
		return($rotate);
	}
}

// Call this function BEFORE writing anything to a page to send a http header to the browser that
// forces the content streamed afterwards to be treated as the given file type
function forceDownloadHeader($ext=null,$filename=false,$size=false) {
  mimeHeader($ext,$size);
  header("Content-Type: application/force-download");
  header("Content-Type: application/download");
  header("Content-Disposition: attachment; filename=\"".basename($filename)."\"");
}
function mimeHeader($ext,$size=false) {
  switch( strtolower($ext) ) {
    case "pdf": $ctype="application/pdf"; break;
    case "exe": $ctype="application/octet-stream"; break;
    case "zip": $ctype="application/zip"; break;
    case "doc": case "docx": $ctype="application/msword"; break;
    case "xls": case "xlsx": $ctype="application/vnd.ms-excel"; break;
    case "ppt": $ctype="application/vnd.ms-powerpoint"; break;
    case "gif": $ctype="image/gif"; break;
    case "png": $ctype="image/png"; break;
    case "jpeg":
    case "jpg": $ctype="image/jpeg"; break;
    case "mp3": $ctype="audio/mpeg3"; break;
    case "csv": $ctype="text/csv"; break;
    case "xml": $ctype="text/xml"; break;
    default: $ctype="application/force-download";
  }
  header("Pragma: public", true);
  header("Expires: 0"); // set expiration time
  header("Cache-Control: max-age=1209600"); // 2 wk caching
  // header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  // header("Cache-Control: private",false); // required for certain browsers ?
  header("Content-Type: application/octet-stream");
  header("Content-Type: ".$ctype);
  header("Content-Transfer-Encoding: binary");
  if ($size) header("Content-Length: ".$size);
}

function getExt($filename) { return strtolower(substr(strrchr($filename,"."),1)); }

// Forces file download (call BEFORE writing to a page as the page will 'become' the file)
function forceDownload($actualFilename,$inDir,$fakeFilename=false) {
  // required for IE, otherwise Content-disposition is ignored
  if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
  $fakeFilename=($fakeFilename)?$fakeFilename:$actualFilename;
	// Kill any attempt to traverse directories
	$filename=str_replace("\\","",$actualFilename);
	$filename=str_replace("/","",$filename);
  $fullFilename=$inDir.$filename;
  if ($filename=="") { error('No file associated with this download'); exit; }
  if (!file_exists($fullFilename)) { error('Could not find file: '.$filename); exit; }
  $ext=getExt($filename);
  forceDownloadHeader($ext,$fakeFilename,filesize($fullFilename));
  streamFile($fullFilename);
}

function streamFile($fullFilename) {
  $fp = @fopen($fullFilename,"rb");
  fpassthru($fp);
  fclose($fp);
}

// 6. GENERIC DB FUNCTIONS
// -----------------------

// This pass-by-reference addWhere function allows calls like:
// addWhere($sql,"policyID=3");
function addWhere(&$sqlSoFar,$statement="") {
	$sqlSoFar=where($sqlSoFar,$statement);
}

// This pass-by-value where function allows calls like:
// $sql=where("SELECT * FROM docs","policyID=3");
function where($sql,$statement=false) {
	if (!$statement) return $sql;
	$stmt=$statement;
	if (strpos($stmt,"WHERE")!==false) $stmt=str_replace("WHERE","",$stmt); // Kill any passed WHERE
	$stmt=((mb_strpos($sql,"WHERE")===false)?" WHERE ":" AND ").$stmt; // put a where or an and on
	$posOrder=(mb_strpos($sql,"ORDER BY")!==false)?mb_strpos($sql,"ORDER BY"):false;
	$posGroup=(mb_strpos($sql,"GROUP BY")!==false)?mb_strpos($sql,"GROUP BY"):false;
	$insertAt=false;
	if ($posOrder && $posGroup) {
		$insertAt=($posOrder>$posGroup)?$posGroup:$posOrder;
	} else if ($posOrder) {
		$insertAt=$posOrder;
	} else if ($posGroup) {
		$insertAt=$posGroup;
	}
	if ($insertAt) {
		$sql=mb_substr($sql,0,$insertAt).$stmt." ".mb_substr($sql,$insertAt,mb_strlen($sql));
	} else {
		$sql.=$stmt;
	}
	return $sql;
}

// Returns the browser's IP address, even if behind load balancing
function getIP() {
  if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
  if (!isset($_SERVER['REMOTE_ADDR'])) return "CLI"; // Command line interface
  return $_SERVER['REMOTE_ADDR'];
}

// Returns true if IS malicious
function scanMaliciousSql($s,$strict=true) {
  $s=strtolower($s);
  $nonStrict=array("/*","--","alter table","drop table","delete from","select *");
  $strict=array(";","delete");
  if (!multiPos($s,$nonStrict)) return false;
  if (!$strict) return false;
  if (!multiPos($s,$strict)) return false;
  if ($s==escSQL($s)) return false;
  return false;
}

// Pass in haystack string and array of strings to find, returns array of locations
function multiPos($s=false,$arr=false) {
  $r=[];
  if (!$s || !$arr) return false;
  foreach ($arr as $find) { $pos=strpos($s,$find); if ($pos!==false) array_push($r,$pos); }
  return (sizeOf($r)==0)?false:$r;
}

// if running IE returns version, else returns false
function runningIE($simulateIE=false) {
  $ua=($simulateIE)?"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; InfoPath.2; .NET CLR 3.5.30729; .NET CLR 3.0.30618; .NET4.0C; BRI/2)":$_SERVER['HTTP_USER_AGENT'];
  $pos=strpos(($ua),'MSIE');
  return ($pos)?(int)(substr($ua,$pos+5,strpos($ua,";",$pos+5)-($pos+5))):false;
}

// ARRAYS & SELECT-BOX OPTIONS
// ---------------------------

// try urlToArray()={ return getPairs($url,"&"); } or getXmlParams()={ return getPairs($tag," "); } or getWherePairs()={ return getPairs($where," AND ","="," IS ","'"); }
function getPairs($s,$delimiter,$balancer1="=",$balancer2=false,$quoteChr=false,$killQuotesInFinalOutput=true) {
  if (isnull($s)) return [];
  if (strClosest($s,$delimiter,$balancer1)==$delimiter) getNextStringBlock($s,$delimiter); // Zoom past initial bumf
  // trace("Initial bumf gone, left with [".$s."]");
  $safety=0;
  $pairs=[];
  while (mb_strlen($s)>0 && $safety++<999) {
    $col=getNextStringBlock($s,(($balancer2)?strClosest($s,$balancer1,$balancer2):$balancer1),$quoteChr);
    $val=getNextStringBlock($s,$delimiter,$quoteChr);
    if ($killQuotesInFinalOutput && $quoteChr && mb_substr($val,0,1)==$quoteChr && mb_substr($val,mb_strlen($val)-1,1)==$quoteChr) {
			$val=mb_substr($val,1,mb_strlen($val)-2);
    }
    $pairs[trim($col)]=trim($val);
  }
  return $pairs;
}
// Sellotape pairs back together into a string
function stickPairs($arr,$delimiter,$balancer="=") {
  $r="";
  foreach ($arr as $var=>$val) {
  	if (is_array($val)) $val=(isset($val[0]))?$val[0]:0;
    if (is_array($val)) $val=(isset($val[0]))?$val[0]:0; // We've gotta do this twice, coz if we have a multidimensional array it needs to look in there twice
  	$r.=((isnull($r))?"":$delimiter).$var.$balancer.$val; }
  return $r;
}

// Compact an array of database records into a 2-item index array of keyCol=>valCol, usually for passing to a select box
// e.g. echo getSelect("productID",getOptionArray($allProducts,"productID","productName"));
// Pass keyCol = -1 to use the existing index
// Pass a string as seedArray to add a null option
function getOptionArray($arr,$keyCol=0,$valCol=0,$seedArray=null) {
	if (!is_array($arr)) $arr = getOptions($arr);
  if (!$arr || sizeOf($arr)==0) return null;
	if ($valCol===false) $valCol=0;
	if ($keyCol==-1) {
    if (!$valCol && isTwoDimensional($arr)) {
      $options = $arr; // Already 2D (don't do this work earlier than though)
      $valCol=0;
    } else {
      $keys=array_keys($arr);
      $cols=array_column($arr, $valCol);
  	  if (sizeOf($keys)!=sizeOf($cols)) {
        // array_combine fails in this instance (which occurs when one or more record doesn't have the requisite valCol)
        // have to go old-school-inefficient and loop it, GAH
        $options=[];
        foreach ($arr as $i=>$d) {
          if (isset($d[$valCol])) $options[$i] = $d[$valCol];
        }
      } else {
        $options=array_diff(array_combine($keys,$cols), [null]);
      }
    }

	} else {
    $options=array_column($arr,$valCol,$keyCol);
  }
  if ($seedArray) {
    if (!is_array($seedArray)) $seedArray=[''=>$seedArray];
    $options = $seedArray + $options;
  }
  return $options;
}
function cribOptions($options,$maxChar) {
	if (!is_array($options) || sizeOf($options)==0) return null;
  foreach ($options as $i=>$v) {
    if (strlen($v)>$maxChar) $options[$i]=substr($v,0,($maxChar/2))."...".substr($v,-($maxChar/2));
  }
  return $options;
}
// Combine 2 arrays (a2 overwrites a1 where fields match)
// Unlike array_merge, preserves existing keys. Used primarily for merging arrays of select box options, but works just fine for other arrays
// NB: you can just do $a3+$a2+$a1 in PHP now
function mergeOptions($a1,$a2=false,$a3=false,$a4=false) {
	if (isnull($a2) && !$a3 && !$a4) return $a1;
	if (!$a1 && !$a3 && !$a4) return $a2;
	if (!is_array($a1)) return false;
	// Can use built-in "+" operator now
	$r=$a2+$a1;
	if ($a3 && is_array($a3)) $r=$a3+$r;
	if ($a4 && is_array($a3)) $r=$a4+$r;
	return $r;
}

function getNumericOptions($x,$y,$symbol="") {
  $a=[];
  if ($y>$x) {
    for ($n=$x; $n<=$y; $n++) {
      $a[$n]=$n.$symbol;
    }
  } else {
    for ($n=$x; $n>=$y; $n--) {
      $a[$n]=$n.$symbol;
    }
  }
  return $a;
}

// Return array of key=>val pairs $options (or $arr['options']) can be array, CSV list or SQL
function getOptions($options=false,$existingOptions=false,$sort=0) {
	if (!$options) return false;
	$newOptions=[];
	if (is_array($options)) {
		// Already an array of key=>val pairs (hopefully)
		$newOptions=$options;
	} else {
		// Extra contains a comma seperated string of options
		$newOptions = iExplode($options);
	}
	if (!$newOptions || !is_array($newOptions) || sizeOf($newOptions)==0) return $existingOptions; // Return the existingOptions as is
	if (!$existingOptions || !is_array($existingOptions) || isnull($existingOptions)) return $newOptions;
	return $existingOptions+$options;
}

// Take a CSV or keys (e.g. 123,423,554) and extract a range of those, e.g. 0 to 9
function extractRange($keys,$startRow=0,$numRows=0) {
  if (!$startRow && !$numRows) return $keys;
  if ((int)$startRow>=countCsv($keys)) return false;
  $keys=explode(',',$keys);
  $r=false;
  $total=sizeOf($keys);
  $endRow=($numRows>0)?($startRow+$numRows):$total; // e.g. print requests all outstanding data
  if ($endRow>$total) $endRow=$total;
  for ($i=$startRow; $i<$endRow; $i++) {
    if (!isset($keys[$i])) return $r; // Out of rows!
    $r.=(($r)?",":"").$keys[$i];
  }
  return $r;
}

// Take an array key=>val pairs (e.g. from getOptions()) and product <option>
function getHtmlOptions($options=false, $default=false, $selectionHasHappened=false) {
	$c="";
    $ops=getOptions($options);
	if (!$ops) return false;
	foreach ($ops as $opVal=>$disp) {
        if (is_array($disp)) {
            $c.="<optgroup label='".$opVal."'>";
            $c.=getHtmlOptions($disp,$default,$selectionHasHappened);
            $c.="</optgroup>";
        } else {
            $selected=false;
            if (!$selectionHasHappened) {
                if ($default===0 || $default==='0') { // Handle zeros differently, as they evaluate strangely e.g. (float)0===(float)'V' evaluates true!
                    $selected=($opVal===0 || $opVal==='0');
                } else {
                    $selected=($default===$opVal || (isNum($default) && (float)($default)===(float)($opVal) && strpos($opVal,",")===false));
                }
            }
            $c.="<option value='".escHTML($opVal)."'".(($selected)?" selected":"").">".$disp."</option>";
            if ($selected) $selectionHasHappened=true; // Only select the first matching option
        }
    }
	return $c;
}

// Return html for <select>. $options (or $arr['options']) can be array or CSV list
// $options can contain: ['name'=>'mySelect', 'options'=>[], 'default'=>25, 'autoSubmit'=>1, 'disable'=>1, 'allowOverride'=>1, 'js'=>"onChange='doSomething()'"]
function getSelect($arrOrName, $options=false, $default=false, $autoSubmit=false) {
  $arr=(is_array($arrOrName))?$arrOrName:['name'=>$arrOrName,'options'=>$options,'default'=>$default,'autoSubmit'=>$autoSubmit];
  $name=(isset($arr['name']))?$arr['name']:((isset($arr['id']))?$arr['id']:false);
  $id = (isset($arr['id']))? "id='{$arr['id']}'" : "id='{$name}'"; //this often leads to duplicate IDs but I can't remove it for legacy reasons...
  $onChange=false;
	if ((isset($arr['autoSubmit']) && $arr['autoSubmit']) || $autoSubmit) $onChange.="if ($(this.form).find(\"#saveBtn\").length>0) { $(this.form).find(\"#saveBtn\").click(); } else { this.form.submit(); } ";
	if (isset($arr['extendOnChangeJs'])) $onChange.=$arr['extendOnChangeJs'];
	if (isset($arr['replaceChangeJS'])) $onChange=$arr['replaceChangeJS'];
	if (isset($arr['onChange'])) $onChange=$arr['onChange'];
  $disabled=((isset($arr['disable']) && $arr['disable']) || (isset($arr['disabled']) && $arr['disabled']))?1:0;
  $selected=(isset($arr['default']))?$arr['default']:((isset($arr['selected']))?$arr['selected']:false);
  $hidden=(isset($arr['hidden']))?$arr['hidden']:0;
  $allowOverride=(isset($arr['allowOverride']))?$arr['allowOverride']:0;
  $js=(isset($arr['js']))?$arr['js']:false;
	$htmlOptions=getHtmlOptions(((isset($arr['options']))?$arr['options']:[]), $selected);
	$out="<select {$id} name='".$name."'".(($onChange)?" onChange='".$onChange."'":"").(($disabled)?" disabled":"").(($hidden)?" hidden":"")." ".$js.((isset($arr['class']))?" class='".$arr['class']."'":"").">";
	$out.=$htmlOptions;
	$out.="</select>";
	if ($allowOverride) $out.="<br /><span class='small'>or enter a value <input id='new".$id."' name='new".$name."' type='text' value='' /></span>";
	return $out;
}
function addNullOption($ops,$val='',$title=' ') {
    $new=[$val=>$title];
    if (!$ops) return $new;
    if (!is_array($ops)) $ops=iExplode($ops);
    return $new+$ops;
}

function getCheckboxes($arr,$horizontal=false,$tableClass='goLeft') {
	$name=(isset($arr['name']))?$arr['name']:((isset($arr['id']))?$arr['id']:false);
	$id=(isset($arr['id']))?$arr['id']:$name;
	$cbs=[]; $n=0;
	foreach (getIfSet($arr,'options',[]) as $val=>$title) {
		$thisId=$id.($n++);
		array_push($cbs, ['title'=>"<label for='".$thisId."'>".$title."</label>", 'input'=>"<input type='checkbox' id='".$thisId."' name='".$name."' value='".$val."'".((getIfSet($arr,'disabled'))?" disabled":"")." ".getIfSet($arr,'js').((getIfSet($arr,'class'))?" class='".$arr['class']."'":"").((in($val,getIfSet($arr,'checked')))?" checked":"")." />"]);
	}
	$cols=($horizontal)?sizeOf($cbs):1;
  $rows=ceil(sizeOf($cbs)/$cols);
  $r="<table class='".$tableClass."'>";
  $n=0;
  for ($row=1;$row<=$rows;$row++) {
    $r.="<tr>";
    for ($col=1;$col<=$cols;$col++) { $r.="<td>".$cbs[$n]['input']."</td><td>".$cbs[$n]['title']."</td>"; $n++; }
    $r.="</tr>";
  }
  return $r."</table>";
}

// JSON
function jsonify($arr) { return json_encode($arr, JSON_INVALID_UTF8_SUBSTITUTE); }
function jsonErr($msg,$rc=2,$debugData=[]) { badResponse(['rc'=>$rc, 'msg'=>$msg]); }
function badResponse($data = [])
{
  if ($data && !is_array($data)) $data = ['msg'=>$data];
  $default = ['rc' => 2, 'msg' => 'No access to API '.p("action"), 'action' => p('action')];
  die(json_encode($data + $default, JSON_INVALID_UTF8_SUBSTITUTE));
}

function goodResponse($data = [])
{
  $default = ['rc' => 0, 'msg' => 'OK'];
  $ret = json_encode($data + $default, JSON_INVALID_UTF8_SUBSTITUTE);
  if ($ret === false) {
    badResponse(['rc'=>2,'msg'=>'json_encode err ['.p('action').'] '.decodeJsonError(json_last_error())]);
  }
  die($ret);
}

function testResponse($data = false)
{
  $default = ['rc' => 3, 'msg' => 'testResponse - check console', 'action' => p('action')];
  die(json_encode($data + $default, JSON_INVALID_UTF8_SUBSTITUTE));
}

// Take the # from json_last_error and give a nice error back. See: https://www.php.net/manual/en/function.json-last-error.php
function decodeJsonError($jsonErrorNum) {
  $msg = "(".$jsonErrorNum.") ";
  switch ($jsonErrorNum) {
    case JSON_ERROR_NONE:
        $msg .= 'No errors';
    break;
    case JSON_ERROR_DEPTH:
        $msg .= 'Maximum stack depth exceeded';
    break;
    case JSON_ERROR_STATE_MISMATCH:
        $msg .= 'Underflow or the modes mismatch';
    break;
    case JSON_ERROR_CTRL_CHAR:
        $msg .= 'Unexpected control character found';
    break;
    case JSON_ERROR_SYNTAX:
        $msg .= 'Syntax error, malformed JSON';
    break;
    case JSON_ERROR_UTF8:
        $msg .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
    break;
    case JSON_ERROR_RECURSION:
      $msg .= 'One or more recursive references in the value to be encoded'; // PHP 5.5.0
    break;
    case JSON_ERROR_INF_OR_NAN:
      $msg .= 'One or more NAN or INF values in the value to be encoded'; // PHP 5.5.0
    break;
    case JSON_ERROR_UNSUPPORTED_TYPE:
      $msg .= 'A value of a type that cannot be encoded was given'; // PHP 5.5.0
    break;
    case JSON_ERROR_INVALID_PROPERTY_NAME:
      $msg .= 'A property name that cannot be encoded was given'; // PHP 7.0.0
    break;
    case JSON_ERROR_UTF16:
      $msg .= 'Malformed UTF-16 characters, possibly incorrectly encoded'; // PHP 7.0.0
    break;
    default:
        $msg .= 'Unknown error';
    break;
  }
  return $msg;
}

// json_encode/decode leads to an unpredictable structure where single-entry sub-arrays are collapsed
// This function takes any array and if it does not have a numbered index, gives it one
function treatAsArr($arr) { return (is_array($arr) && isset($arr[0]))?$arr:array($arr); }
// Choose a random number of entries from the given array
function getRandIndexes($arr,$numToGet=-1) {
  if (!is_array($arr)) return false;
  $whichOnes=getRandNumbers(sizeOf($arr)-1,(($numToGet==-1)?sizeOf($arr)-1:$numToGet));
  $res=[];
  foreach ($whichOnes as $thisOne) { array_push($res,$arr[$thisOne]); }
  return $res;
}
function getRandEntry($arr) {
  $keys = array_keys($arr);
  $rand = rand(0,sizeOf($arr)-1);
  $key = $keys[$rand];
  return $arr[$key];
}
// Strip an array down to just the given set of keys (e.g. to reduce the size of returned JSON / ensure only valid keys are used)
function stripDown($a,$keys) {
  if (is_array($keys)) $keys = implode(',',$keys);
  if (countCsv($keys)==1) return getIfSet($a,$keys); // fasterer
  $keys=(is_array($keys))?$keys:explode(",",$keys);
  $res=[];
  foreach ($keys as $key) { $res[$key]=(isset($a[$key]))?$a[$key]:false; }
  return $res;
}
// Strip an array of arrays down
function stripDownArray($arr,$keys) {
	if (!$arr || !is_array($arr) || !$keys) return false;
  $res=[];
  foreach ($arr as $i=>$data) { $res[$i]=stripDown($data,$keys); } // Preserve existing row key
  return $res;
}
// Translate a value using a lookup
function map($val, $lookup, $default=false) { return getIfSet($lookup,$val,$default); }
function getSalt() { return rand(999,99999); }

// Returns a list (array) of random numbers between 0 and highest WITH NO REPETITIONS
function getRandNumbers($highest,$numToGet,$lowest=0) {
  if ($numToGet>($highest-$lowest)+1) $numToGet=$highest-$lowest+1; // maximum returned is the difference between highest and lowest INCLUSIVE
  $safety=0; $hits=[]; $res=[];
  while ($numToGet>0 && ($safety++)<(10+($highest-$lowest)*($highest-$lowest))) {
    $try=rand($lowest,$highest);
    if (!isset($hits[$try])) {
      $hits[$try]=true;
      array_push($res,$try);
      $numToGet--;
    }
  }
  return $res;
}

// SORTING & ORDERING
// ------------------

function sortOptions($options,$sort=1) {
  // uksort preserves keys, but we want to sort on the option title too, so we need to refer back to the options array
  $tmpOptions=$options;
	if ($sort==1) {
	  uksort($options,function($a,$b) { return (strToLower($tmpOptions[$a])<strToLower($tmpOptions[$b]))?-1:1; });
	} else if ($sort==-1) {
	  uksort($options,function($a,$b) { return (strToLower($tmpOptions[$a])<strToLower($tmpOptions[$b]))?1:-1; });
	}
	return $options;
}

function reIndex($arr,$col) {
  return array_column($arr,NULL,$col);
}

// Sort array of records on $field preserving indexes
// Pass $field=false to sort a 2 dimensional array on value
// Use re-index to also index on a different field
function superSort($arr,$field=false,$rev=false,$reIndex=false) {
	// Create a lookup of key=>field for asort to work with
	if (!$arr || !is_array($arr)) return $arr;
	$index=getOptionArray($arr,-1,$field);
	natcasesort($index);
	if ($rev) $index=array_reverse($index, true);
	// Repopulate all the data
	$r=[];
	$j=0; // The new index
	foreach ($index as $i=>$f) {
		if ($reIndex===true) {
		} else if ($reIndex) {
			$r[$arr[$i][$reIndex]]=$arr[$i];
		} else {
			$r[$i]=$arr[$i];
		}
	}
	return $r;
}

$fieldsAndDirectionArray=[];
$translationArray=[];
// sorts multidimensional arrays on multiple fields whilst preserving keys. I know. I rock.
// pass in fields and directions in an array ['age'=>'desc','name'=>'asc','height'=>'desc'] or simply a string if all desc "surname,firstname"
// pass in $translation for sorting things like days of week ie. ['days'=>['monday'=>1, 'tuesday'=>2, 'wednesday'=>3]]...
function superMultiSort($arr,$fieldsAndDirection,$translation=false) {
  global $fieldsAndDirectionArray, $translationArray;
  if (!is_array($fieldsAndDirection)) {
    $fieldsAndDirectionArray = [];
    foreach(explode(',',$fieldsAndDirection) as $field) {
      $fieldsAndDirectionArray[$field]='desc';
    }
  } else {
    $fieldsAndDirectionArray=$fieldsAndDirection;
  }
  $translationArray=$translation;
	uasort($arr,"superMultiSortCompare");
	return $arr;
}
// support function for superMultiSort (cycles through given fields, returning if equal and checking next param if not)
function superMultiSortCompare($comp1,$comp2) {
	global $fieldsAndDirectionArray, $translationArray;
	foreach ($fieldsAndDirectionArray as $field=>$direction) {
    $val1 = ($translationArray && isset($translationArray[$field])) ? $translationArray[$field][$comp1[$field]] : $comp1[$field];
    $val2 = ($translationArray && isset($translationArray[$field])) ? $translationArray[$field][$comp2[$field]] : $comp2[$field];
    if ($val1 != $val2) return ($direction=="desc") ? (($val1 < $val2) ? -1 : 1 ) : (($val1 > $val2) ? -1 : 1 );
	}
}

$altSortField=false;
// Sort using numbered/named field (uses usort). INDEX NOT PRESERVED
function altSort($arr,$field) { global $altSortField; $altSortField=$field; usort($arr,"altSortCompare"); return $arr; }
function altSortCompare($row1, $row2) { global $altSortField; return (strtolower($row1[$altSortField])>strtolower($row2[$altSortField]))?1:((strtolower($row1[$altSortField])<strtolower($row2[$altSortField]))?-1:0); }
// Note: to sort on alphanumeric index use ksort(&$arr, SORT_NATURAL | SORT_FLAG_CASE) or krsort() for descending
// Reorder an array starting with the items listed in $order e.g. forcedSort(['cherries','lemons','apples','oranges'],['oranges','lemons']) produces ['oranges','lemons','cherries','apples']
function forcedSort($arr,$order) {
	if (!is_array($arr) || !is_array($order)) return $arr;
	$r=[];
	foreach ($order as $i) { $r[$i]=(isset($arr[$i]))?$arr[$i]:false; }
	foreach ($arr as $i=>$d) { if (!isset($r[$i])) $r[$i]=(isset($arr[$i]))?$arr[$i]:false;  }
	return $r;
}

// Return an MD5 hash of all given arguments (including arrays)
// NB: hashData will return different results if strings / ints passed (so '43221' and 43221 will return different hash strings) so pass explicitly typed variables e.g. (int)$myID if you're having trouble
function hashData() { return md5(implode('',func_get_args()));}
// Note: better to use password_hash() and password_verify() as these generate different salts each time

function getFileContents($file, $uid="text"){
	$text = (file_exists($file)) ? file_get_contents($file) : file_put_contents($file, "");
	$text = ($text != "") ? $text : "";
	if(loginCheck()){
		if(p($uid)) file_put_contents($file, p($uid));
	  $kf = new Form();
	  $kf->addCol($uid, "Text:", "textarea", "COLS=0,ROWS=0", $text);
	  $kf->wrapInTable = false;
	  $text = $kf->getForm();
	}
	return $text;
}

function loginCheck(){ return (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true); }

// Pass in a float, get HTML with fractions in return
function superRound($f,$precision=0) {
  return round($f,$precision);
  /*
  if (strpos($f,'.3333')) return "".round($f)."&frac13;";
  if (strpos($f,'.6666')) return "".round($f)."&frac23;";
  return round($f,$precision);
  */
}

function toBinary($s) { return ($s && notnull($s) && in(strToUpper($s),"Y,YES,T,TRUE,X,1"))?1:0; }

function isHTML($string) { return preg_match("/<[^<]+>/",$string,$m) != 0; }

// Give me an int (e.g. 4) I return you a roman numeral (e.g. iv)
function romanNumeral($i) {
	$romanLookup=['M'=>1000, 'CM'=>900, 'D'=>500, 'CD'=>400, 'C'=>100, 'XC'=>90, 'L'=>50, 'XL'=>40, 'x'=>10, 'ix'=>9, 'v'=>5, 'iv'=>4, 'i'=>1];
	$r="";
	while ($i>0) {
		foreach ($romanLookup as $char=>$arb) {
			if ($i>=$arb) { $i-=$arb; $r.=$char; break; }
		}
	}
	return $r;
}

// shamelessly stolen from http://stackoverflow.com/questions/5696412/get-substring-between-two-strings-php
function getStringBetween($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function setMemoryLimit($limit) {
	global $env;
	$memoryLimit=(isset($env) && isset($env['localMemoryLimit']))?$env['localMemoryLimit']:$limit;
	ini_set('memory_limit', $memoryLimit);
}

// for removing blanks in arrays
function killBlanks($arr,$spareThese=false) {
  $out=[];
  if (!$arr || !is_array($arr) || sizeOf($arr)<1 || isnull($arr)) return $out;
  foreach ($arr as $i=>$val) {
    if (is_array($val)) $val=killBlanks($val);
    if (notnull($val) || ($spareThese && in($i,$spareThese))) $out[$i]=$val;
  }
  return $out;
}

// used by asset.php to sort pattr posts
function sortByPostID($a, $b) {
  return strcmp($b['postID'], $a['postID']);
}

function wordToSlug($string) {
    $string=preg_replace("/[^ \w]+/", "", $string);
    $string=strtolower($string);
    //$string=strtolower(preg_replace('/[^a-zA-Z\s]/',' ',$string));
    $string=str_replace(' ','-',$string);
    $string=str_replace('--','-',$string); // remove doubles from weird characters
    return $string;
}

// Encourage HTML to log a var to the console
function consoleLog($var, $name='') {
	global $env;
	if (!$env || $env['instance']!='DEV') return false;
	if ($var === null)          $type = 'NULL';
	else if (is_bool    ($var)) $type = 'BOOL';
	else if (is_string  ($var)) $type = 'STRING['.strlen($var).']';
	else if (is_int     ($var)) $type = 'INT';
	else if (is_float   ($var)) $type = 'FLOAT';
	else if (is_array   ($var)) $type = 'ARRAY['.count($var).']';
	else if (is_object  ($var)) $type = 'OBJECT';
	else if (is_resource($var)) $type = 'RESOURCE';
	else                        $type = '???';
	if (strlen($name)) {
	  consoleLogStr("$type $name = ".var_export($var, true).';');
	} else {
	  consoleLogStr("$type = "      .var_export($var, true).';');
}
}

function consoleLogStr($str) {
  echo "<script type='text/javascript'>\n";
  echo "//<![CDATA[\n";
  echo "console.log(", json_encode($str), ");\n";
  echo "//]]>\n";
  echo "</script>";
}

function getDateRange($startDate, $endDate) {
  $start=new DateTime($startDate);
  $end=new DateTime($endDate);
  $interval = new DateInterval('P1D');
  $dateRange = new DatePeriod($start, $interval ,$end);
  /*
  //var_dump($dateRange);
  $theDates=[];
  foreach ($dateRange as $date) {
    array_push($theDates,$date);
  }
  array_push($theDates,$end);
  */
  $theDates=$dateRange;
  array_push($theDates,$end);
  return $theDates;
}

function getSiteURL() {
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'].'/';
    return $protocol.$domainName;
}

// send back either all $_GET/$_POST combined together (if $params is false)
// OR send an array of the values you are looking for along with default values
function getParams($params=false) {
	$allParams=fetchAllParams();
	if (!$params) return $allParams;
	if (!is_array($params)) return false; // unclear what you really want here
	foreach ($params as $key=>$defaultValue) {
		$value=(isset($allParams[$key]))?$allParams[$key]:false;
		if ($value) $params[$key]=$value;
	}
	return $params;
}

function fetchAllParams() {
	return array_merge($_GET,$_POST);
}

// ohmygodthisishorribledontjudgeme
// This is needed for attainment 8 shite. It's used in an uasort.
function sortBestThree($a,$b){
  if ($a['ps'] == $b['ps']) return 0;
  return ($a['ps'] > $b['ps']) ? -1 : 1;
}

function whichShard($ref) {
  global $env;
  if (!$ref) return false;
  $shard=substr($ref,0,1);
  if (isnum($shard)) {
    // trace("ID [".$ref."] passed where ref required");
    return $_SESSION['shard'];
  }
  if (!isset($env['shards'][$shard])) { trace("shard [".$shard."] invalid for ref [".$ref."]",1,1); return false;}
  return $shard;
}
function whichID($ref) {
  if (isnum($ref)) return $ref;
  $id=substr($ref,1,strlen($ref));
  if ($id!=cleanNum($ref)) die("id [".$id."] extracted from ref [".$ref."] is too weird");
  return $id;
}

function getStartDateEndDateSQL($startDate=false,$endDate=false,$tableName=false) {
  $sql=false;
  // Note: even if start date is before $startDate, if $endDate is AFTER we want to include these kids, as they were ACTIVE during the period requested
  if ($endDate) $sql.="(".(($tableName)?$tableName.".":"")."startDate IS NULL OR ".(($tableName)?$tableName.".":"")."startDate<=".fss($endDate).")";
  if ($startDate) {
    if ($sql) $sql.=" AND ";
    $sql.="(".(($tableName)?$tableName.".":"")."endDate IS NULL OR ".(($tableName)?$tableName.".":"")."endDate>=".fss($startDate).")";
  }
  return $sql;
}

// helper function to tell if we are superadmin based on session ( not very robust I imagine)
function isSuperAdmin() {
	return isset($_SESSION) && isset($_SESSION['userType']) && $_SESSION['userType'] == 'SUPERADMIN';
}

// Based on: http://php.net/manual/en/function.debug-backtrace.php#42041
// You may want to consider the more straightforward stackTrace() or debug_backtrace()
function backtrace($msg=false, $showArgs=false) {
  if ($msg) trace($msg,1,2);
  $vDebug = debug_backtrace();
  $vFiles = array();
  $file=$class=$function=$line=$args=false;
  for ($i=0;$i<safeCount($vDebug);$i++) {
      $aFile = $vDebug[$i];
      $file = basename($aFile['file']);
        if($showArgs) { $args = $aFile['args']; }
        if(isset($aFile['file'])) { $file = basename($aFile['file']); }
        if(isset($aFile['class'])) { $class = $aFile['class']; }
        if(isset($aFile['function'])) { $function = $aFile['function']; }
        if(isset($aFile['line'])) { $line = $aFile['line']; }
      $vFiles[] = [
        'file'=>$file,
        'line'=>$line,
        'function'=>$function,
        'class'=>$class,
        'args'=>$args
      ];
    }
  echo "<style>#stackTable, #stackTable tr, #stackTable td { border: 1px solid black; } </style>";
  echo "<style>#stackTable td { width: 25%; text-align: center;} </style>";
  echo "<table id='stackTable'>";
  echo "<tr><th class='stackData'>File</th><th class='stackData'>Line</th><th class='stackData'>Function being called at this line</th><th class='stackData'>Class</th><th class='stackData'>Parameter Values.<br/>For more details: test(get_defined_vars())</th></tr>";
    for ($i=0;$i<safeCount($vFiles);$i++) {
      $vFile = $vFiles[$i]['file'];
      $vClass = $vFiles[$i]['class'];
      $vFunction = $vFiles[$i]['function'];
      $vLine = $vFiles[$i]['line'];
      $vArgs = $vFiles[$i]['args'];
      echo "<tr><td class='stackData'>".$vFile."</td><td class='stackData'>".$vLine."</td><td class='stackData'>".$vFunction."</td><td class='stackData'>".$vClass."</td><td>";
      // If we've been given a function name and it matches the current row, show the parameters.
      // If we've not been given a function name show it for all the functions.
      if($showArgs) {
        echo "<table>";
        echo getTraceArgs($vArgs);
        echo "</table></td>";
      }
      echo "</tr>";
    }
  echo "</table>";
}

function getTraceArgs($args,$i=0) {
  if ($i>9) return false; // Stop the recursion!
  foreach ($args as $key => $arg) {
    echo "<tr>";
      if (is_object($arg)) {
        echo "<td style='border: 2px solid red;'>(Object)</td>";
      } else if(!is_array($arg)) {
        echo "<td style='border: 2px solid green;'>".$arg."</td>";
      } else {
        foreach ($arg as $parameter => $value) {
          if(!is_array($value)) {
            echo "<tr><td  style='border: 2px solid purple;'>".$parameter.": ".$value."</td></tr>";
          } else {
            echo getTraceArgs($arg,++$i);
          }
        }
      }
      echo "</tr>";
  }
}

function toNiceDate($date) {
  $d = new DateTime($date);
  $today = new DateTime();
  $s = $d->format('D jS');
  if ($d->format('Y')!=$today->format('Y')) {
    $s.= " ".$d->format('M Y');
  } else if ($d->format('M')!=$today->format('M')) {
    $s.= " ".$d->format('M');
  }
  return $s;
}


//Adapted from here: https://stackoverflow.com/a/13566675
function getNumericalMonth($mon){
  $monthArray = date_parse($mon);
  return $monthArray['month'];
}

// PHP Performance monitoring
// Add startFunc() and endFunc() to start/end of functions you are monitoring
// Use getFuncLog() at end for stats
$funcLog=[];
$funcLogOn=0; //

function getFunctionName($layer=2) {
  // Grab the name of the function that called funcStart or funcEnd
  $backTrace=debug_backtrace();
  if (!is_array($backTrace) || safeCount($backTrace)<=$layer) {
    return "topLayer"; // Not called from within a function
  }
  return $backTrace[$layer]['function'];
}

function startFunc($func=false) {
  global $funcLog, $funcLogOn;
  if (!$funcLogOn) return false;
  $func=($func)?$func:getFunctionName();
  if (!isset($funcLog[$func])) {
    $funcLog[$func]=[
      'count'=>0,
      'totalSecs'=>0,
      'calledBy'=>[]
    ];
  }
  $funcLog[$func]['thisStartTime']=microtime(true);
  $funcLog[$func]['count']++;
  $callingFunc=getFunctionName(3);
  if (!isset($funcLog[$func]['calledBy'][$callingFunc])) {
    $funcLog[$func]['calledBy'][$callingFunc]=0;
  }
  $funcLog[$func]['calledBy'][$callingFunc]++;
}

function endFunc($func=false) {
  global $funcLog, $funcLogOn;
  if (!$funcLogOn) return false;
  $func=($func)?$func:getFunctionName();
	if (!isset($funcLog[$func])) startFunc($func);
	$nowTime=microtime(true);
	$timeTaken=$nowTime-$funcLog[$func]['thisStartTime'];
  $funcLog[$func]['totalSecs']+=$timeTaken;
}

function getFuncLog() {
  global $funcLog;
  // Re-index on count / ms
  $log=[];
  foreach ($funcLog as $func=>$info) {
    unset($info['thisStartTime']);
    $log[$info['count']." @ ".round($info['totalSecs'],4)."secs"]=[
      'function'=>$func,
      'totalSecs'=>round($info['totalSecs'],2),
      'count'=>$info['count'],
      'avg'=>round($info['totalSecs']/$info['count'],4),
      'calledBy'=>$info['calledBy']
    ];
  }
  $log=superSort($log, 'totalSecs', true);
  return $log;
}

function getAsPhpArray($arr,$indent=0,$origKillComma=0) {
  $c="";
  $killComma=$origKillComma;
  // if ($indent) $c.="<br />".repeat("&nbsp;",$indent);
  if (is_array($arr)) {
    $isAssoc = isAssociative($arr);
    $c.="[";
    $c.="<br />";
    $count=0;
    foreach ($arr as $key=>$val) {
      $indent+=2;
      // Suppress the comma on the final array entry
      $killComma=($count===safeCount($arr)-1)?1:0;
      $c.=repeat("&nbsp;",$indent);
      if ($isAssoc) $c.=quoteIfStr($key)."=>";
      $c.=getAsPhpArray($val,$indent,$killComma);
      $count++;
      $indent-=2;
    }
    $c.=repeat("&nbsp;",$indent);
    $c.="]";
  } else {
    $c.=quoteIfStr($arr);
  }
  if (!$origKillComma && $indent) $c.=",";
  $c.="<br />";
  if (!$indent) {
    return "<div style='font: 12px courier;'>".$c."</div>";
  }
  return $c;
}

function quoteIfStr($a) {
  if (isNum($a)) {
    return $a;
  }
  return "'".$a."'";
}

function isAssociative(array $arr) {
  if (array() === $arr) return false;
  return array_keys($arr) !== range(0, sizeOf($arr) - 1);
}

function safeCount($a){
	if (is_array($a) || $a instanceof Countable) {
    return count($a);
	}
	return 0;
}

function isMS() {
  if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
  $ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
  if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0; rv:11.0') !== false) || (strpos($ua, 'Edge') !== false)) {
	  return 1;
  }
  return 0;
}

function mergeArraysAndPreserveKeys($arr1,$arr2) {
  if(!is_array($arr1) || !is_array($arr2)) {
    return false;
  }
  $newArray=[];
  $arr1Keys=array_keys($arr1);
  $arr2Keys=array_keys($arr2);
  $arrayKeysAll=array_merge($arr1Keys,$arr2Keys);
  $arrayKeys = array_unique($arrayKeysAll, SORT_REGULAR);
  foreach ($arrayKeys as $key => $value) {
    $newArray[$value]=[];
  }
  foreach ($arr1 as $key => $value) {
    $newArray[$key]=$value;
  }
  foreach ($arr2 as $key => $value) {
    if(isset($newArray[$key]) && !empty($newArray[$key])) {
      $newArray[$key]=$newArray[$key]+$value;
    } else {
      $newArray[$key]=$value;
    }
  }
  // test($newArray,2);
  return $newArray;
}
// Returns a, b from best fit line of y = ax + b ($data is array: [[x1 => y1],..., [xn => yn]] where n is the total number of data points
function linearBestFit($data) {
  $sx = $sy = $sxy = $sx2 = 0;
  $n = safeCount($data);
  if ($n == 0) return false;
  foreach ($data as $x => $y){
    if (is_numeric($x) && is_numeric($y)) {
      $sx += $x;
      $sy += $y;
      $sxy += $x * $y;
      $sx2 += $x * $x;
    } else {
      return false;
    }
  }
  if (($n * $sx2 - $sx * $sx) == 0) {
    $a = false;
  } else {
    $a = ($n * $sxy - $sx * $sy) / ($n * $sx2 - $sx * $sx);
  }
  $b = $sy / $n - $sx * $a / $n;

  return [ 'a' => $a, 'b' => $b ];
}

// Find all occurences of needle in haystack, returning an array of character positions
function multiStrpos($haystack, $needle) {
	$r=[]; $safety=0;
	if (strlen($needle)===0) return $r;
	$pos=strpos($haystack,$needle);
	while ($pos !==false && $safety++<999) {
		array_push($r,$pos);
		$pos=strpos($haystack,$needle,$pos+1);
	}
	return $r;
}
/*
// Write a row to the activity log
function logActivity($what=false) {
  global $DB;
  // Collate generic info
  $sessionID=nvl(session_id(),"NONE");
  $request_uri=(isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:"CLI";
  // Do NOT accidentally log passwords
  if (strpos($request_uri,'password')) $request_uri=substr($request_uri,0,strpos($request_uri,'password')+9).'xxxx';
  $remote_addr=getIp();
  $http_user_agent=$_SERVER['HTTP_USER_AGENT'] ?? "CLI";
  if (strlen($what)>256) $what = substr($what,0,255);
  $data=[
    'what'=>$what,
    'url'=>getPageURL(),
    'request'=>$request_uri,
    'sessionID'=>$sessionID,
    'ipAddress'=>$remote_addr,
    'userAgent'=>$http_user_agent,
    'totalMS'=>((isset($DB->stats['totalMS']))?($DB->stats['totalMS']):0)
  ];
  // Add in any relevant session info (e.g. userID)
  foreach ($DB->getColsFromDD('activity') as $colDef) {
    $col=$colDef['COLUMN_NAME'];
    if (isset($_SESSION[$col])) $data[$col]=$_SESSION[$col];
  }
  return $DB->doInsert('activity',$data);
}
*/

function quoteEncase($csv, $quote = "'") {
  $oppquote = ($quote == "'")?'"':"'";
  $s = escSql($csv);
  return $quote.$s.$quote;
}
// Count the number of leaves in an array
function countChildren($arr) {
  if (!is_array($arr)) return 0;
  $count=0;
  foreach ($arr as $key=>$data) {
    $count+=(!is_array($data))?1:countChildren($data);
  }
  return $count;
}

function logToFile($str,$logFile='../log/log.txt') {
  file_put_contents($logFile, "\n:".sqlNow()."\n", FILE_APPEND);
  file_put_contents($logFile, $str, FILE_APPEND);
}

function startSession($sessionName = "society") {
  if (session_status() == 2) {
    return false; // Already started
  }
  session_name($sessionName);
  $daysUntilCookieTimeout = 365;
  session_set_cookie_params(60*60*24*$daysUntilCookieTimeout);
  session_cache_limiter('private, must-revalidate');
  session_cache_expire(60);
  session_start();
}

function stopSession() {
  if (session_status() == 1) return false; // Already stopped / not started
  session_write_close();
}  

function logout() {
  $this->startSession();
  session_unset();
  $this->stopSession();
}

?>
