<?php
include_once(dirname(__FILE__).'/../../include/society/DB.php');
include_once(dirname(__FILE__).'/../../include/Form.php');

// Compatibility fix for PHP < 7.2 (i.e. smaug)
if (!defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
  //PHP < 7.2 Define it as 0 so it does nothing
  define('JSON_INVALID_UTF8_SUBSTITUTE', 0);
}

$env=(strpos($_SERVER['HTTP_HOST'],'johnpeelarchive')!==false)?"live":"localhost";
$DB=new DB('johnpeelarchive','thelookoflove','archiver');
startSession("johnpeel");
$_SESSION['userID']=getIfSet($_SESSION,'userID',0);
if ($_SESSION['userID']>0) {
	// Logged in OK
	$inTheFamily=($_SESSION['userID']<=7)?true:false;
} else {
	$_SESSION['redirPage']=$_SERVER['REQUEST_URI'];
	redir("login.php");
}

if (np("recordID")) {
	$record=getRecordInfo(np("recordID"));
	$iA=$_SESSION['iA']=$record['iA'];
	$iB=$_SESSION['iB']=$record['iB'];
	$iC=$_SESSION['iC']=$record['iC'];
	$iD=$_SESSION['iD']=$record['iD'];
	$chunk=$DB->GetRow("SELECT * FROM chunks WHERE iA=".fss($iA)." AND iB='".$iB."'");
} else {
	$iA=$_SESSION['iA']=p('iA',getIfSet($_SESSION,'iA',"LP"));
	$iB=$_SESSION['iB']=(p('iB')!==false)?p('iB'):getIfSet($_SESSION,'iB',"00");
	$iC=$_SESSION['iC']=p('iC',getIfSet($_SESSION,'iC',0));
	$iD=$_SESSION['iD']=p('iD',getIfSet($_SESSION,'iD',0));
  if ($iB == "clear") {
    $iB = $DB->GetOne("SELECT iB FROM chunks WHERE iA=".fss($iA)." ORDER BY chunkID LIMIT 1");
  }
	$chunk=$DB->GetRow("SELECT * FROM chunks WHERE iA=".fss($iA)." AND iB=".fss($iB));
}
if (np("change")) {
  $_SESSION['showValuations'] = ($inTheFamily && np("showValuations"))?1:0;
}
stopSession();
$msg=p("msg");
$users=$DB->GetArr("SELECT userID,username FROM users ORDER BY username");
$letters="A,B,C,D,E,F,G,H,I,J,L,K,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z";
$iAoptions=[
	'LP'=>"LPs",
	'7'=>"7s",
	'12'=>"12s",
	'T'=>"Tapes",
	'M'=>"Misc.",
	'CD'=>"CDs"
];
$iBlookup=[
	'LP'=>"00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29",
	'7'=>"A,B,C,D,E,F,G,H,I,J,L,K,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0,Sp,Re,FB",
	'12'=>"A,B,C,D,E,F,G,H,I,J,L,K,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0,Sp,Mi,Co,Un,Se",
	'T'=>"A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0",
	'M'=>"00,01,02,Se",
	'CD'=>"Mi"
];
$iBdecode=['Sp'=>'Specials', 'Mi'=>'Misc', 'Co'=>'Compilations', 'Un'=>'Artist Unknown', 'Se'=>"Peel Session", 'Re'=>"Reggae/SoundSystem","FB"=>"FireBox"];
$iBoptions=[-1=>'Choose one'];
$iAcounts=$DB->GetArr("SELECT iA, SUM(numCopies) FROM records GROUP BY iA");
$iBcounts=$DB->GetArr("SELECT iB, COUNT(1) FROM records WHERE iA=".fss($iA)." AND iD=1 GROUP BY iB");
foreach ($DB->GetAll("SELECT * FROM chunks WHERE iA=".fss($iA),1) as $chunk) {
	$iB2=$chunk['iB'];
	$desc=getIfSet($iBdecode,$iB2,$iB2);
	if (isset($iBcounts[$iB2])) $desc.=" (".$iBcounts[$iB2]." ".((in($iA,"T,M")?"items":"records"))." - ".round(100*($iBcounts[$iB2]/$chunk['chunkSize']),1)."%)";
	$iBoptions[$iB2]=$desc;
}

$iCoptions=[];
if ($iA && $iB!==false && $iB!=-1) {
	// Pull the records for this chunk
	$records=getRecords($iA,$iB);
	foreach ($records['records'] as $rec) {
		$iCoptions[$rec['iC']]=substr($rec['desc'],0,44).(($rec['numVersions']>1)?" (".$rec['numVersions']." versions)":"");
	}
} else {
	$iCoptions=[-1=>"Choose a chunk first"];
}

$iDoptions=[];
for($i=1; $i<=9; $i++) {
	$iDoptions[$i]="v".lpad($i,strlen("".$i),'0');
}


// MINT / EX / VG+ / VG / Good / Fair / Poor / Bad 
$conditionOptions="?,M,EX,VG+,VG,G,F,P";

function logEvent($event,$recordID=0) {
	global $DB;
	return $DB->doInsert('events',['userID'=>getIfSet($_SESSION,'userID'), 'recordID'=>$recordID, 'event'=>$event, 'eventDate'=>sqlNowTime()]);
}

function getNumRange($a=0,$b=999) {
	$r=[];
	for($i=$a; $i<=$b; $i++) {
		$r[$i]=lpad($i,strlen("".$b),'0');
	}
	return $r;
}

function getRecords($iA, $iB) {
	global $DB;
	$sql="SELECT * FROM records WHERE iA=".fss($iA)." AND iB='".$iB."' ORDER BY iC, iD";
	$records=$DB->GetArr($sql,'iC',1);
  $sql = "SELECT * FROM chunks WHERE iA=".fss($iA)." AND iB=".fss($iB);
	$chunk=$DB->GetRow($sql);
	$all=['count'=>safeCount($records), 'maxiC'=>0, 'records'=>[]];
	foreach (getNumRange(0,$chunk['chunkSize']) as $iC=>$desc) {
		$rs=getIfSet($records,$iC);
		if (!$rs) {
			$record=blankRecord($iA,$iB,$iC);
		} else {
			$record=false;
			// Each iC can have 1 or more records (iD>1 is a different version)
			foreach ($rs as $r) {
				$r['pics']=getPics($r['recordID']);
				$r['numPics']=sizeOf($r['pics']);
				$all['count']++;
				$all['maxiC']=max($all['maxiC'],$iC);
				$r['ref']=getRef($iA,$iB,$iC,$r['iD']);
				$r['desc']=getDesc($r);
				if (!$record) {
					$record=$r;
					$record['versions']=[];
					$record['numVersions']=1;
				} else {
					$record['versions'][$r['iD']]=$r;
					$record['numVersions']++;
				}
			}
		}
		$all['records'][$iC]=$record;
	}
	return $all;
}

function getRecordsFromSQL($sql) {
	global $DB;
	$records=[];
	foreach ($DB->GetAll($sql) as $r) {
		$records[$r['recordID']]=getRecordInfo($r);
	}
	return $records;
}

function getPics($recordID) {
	global $DB;
	return $DB->GetAll("SELECT * FROM pics WHERE recordID=".$recordID,1);
}

function getRef($iA,$iB,$iC,$iD=1) {
	return $iA.$iB.lpad($iC,((in($iA,'LP,M'))?3:4),'0').(($iD>1)?"v".$iD:"");
}

function digestRef($ref) {
  $posV = strpos($ref,'v');
  $iD = ($posV)?substr($str, $posV+1):1;
  if ($posV) $ref = substr($ref, 0, $posV);
  $iA = map($ref[0],['1'=>"12", '7'=>"7", 'L'=>"LP", 'C'=>"CD", 'M'=>"M"]);
  $icLen = ((in($iA,'LP,M'))?3:4);
  $iC = substr($ref,-1*$icLen);
  $iB = substr($ref,strlen($iA),strlen($ref)-(strlen($iA)+strlen($iC)));
  return ['iA'=>$iA, 'iB'=>$iB, 'iC'=>(int)$iC, 'iD'=>(int)$iD];
}

function getRecord($iA, $iB, $iC, $iD=1) {
	global $DB;
	$record=$DB->GetRow("SELECT * FROM records WHERE iA=".fss($iA)." AND iB='".$iB."' AND iC=".$iC." AND iD=".$iD);
	if (!$record) return blankRecord($iA, $iB, $iC, $iD);
	return getRecordInfo($record);
}

function getRecordInfo($recordOrRecordID) {
	global $DB;
	if (!$recordOrRecordID) return false;
	$record=(is_array($recordOrRecordID))?$recordOrRecordID:$DB->GetRow("SELECT * FROM records WHERE recordID=".$recordOrRecordID,1);
	if (!$record) {
		trace("Record no longer exists ".$recordOrRecordID);
		return false;
	}
	$record['pics']=getPics($record['recordID']);
	$record['numPics']=sizeOf($record['pics']);
	$record['ref']=getRef($record['iA'],$record['iB'],$record['iC']);
	$record['desc']=getDesc($record);
	$record['numVersions']=$DB->GetOne("SELECT COUNT(1) FROM records WHERE iA=".fss($record['iA'])." AND iB='".$record['iB']."' AND iC=".nvl($record['iC'],0));
	return $record;
}

function blankRecord($iA,$iB,$iC,$iD=1) {
	return ['recordID'=>0, 'iA'=>$iA,'iB'=>$iB,'iC'=>$iC, 'iD'=>$iD, 'pics'=>false,'numPics'=>0, 'artist'=>false, 'title'=>false, 'ref'=>getRef($iA,$iB,$iC,$iD), 'desc'=>getRef($iA,$iB,$iC,$iD), 'versions'=>[], 'numVersions'=>0];
}

function getDesc($record) {
	$desc=getRef($record['iA'],$record['iB'],$record['iC']);
	if ($record['numPics']>0) $desc.=" &#10003;";
	if ($record['partOfPrevious']) $desc.=" &#8593; (part of previous)";
	if (notnull($record['artist'])) $desc.=" ".$record['artist'];
	if (notnull($record['title'])) $desc.=" ".$record['title'];
	return $desc;
}

// IMAGE MANIPULATION STUFF
// ------------------------
function rotateFile($filename) {
	$img1=false; $angle=90; $quality=100;
	$ext=getExt($filename);
	if (in($ext,'jpg,jpeg')) {
		$img1=@imagecreatefromjpeg($filename);
		$technique=2;
	} else if ($ext=='gif') {
		$img1=@imagecreatefromgif($filename);
		$technique=1;
	} else if ($ext=='png') {
		$img1=@imagecreatefrompng($filename);
		$technique=2;
	}
	if (!$img1) return false;
	$width = imagesx($img1); $height = imagesy($img1);
	$newWidth=($angle==90 || $angle==270)?$height:$width;
	$newHeight=($angle==90 || $angle==270)?$width:$height;
	$img2=@imagecreatetruecolor($newWidth, $newHeight);
	if($img2) {
		if ($technique==1) {
			$img1=imagerotate($img1, $angle, imageColorAllocateAlpha($img1, 255, 255, 255, 255));
			// ...but imagerotate scales, so we copy back to the original size
			imagecopyresampled($img2, $img1, 0, 0, ($newWidth-$width)/2, ($newHeight-$height)/2, $width, $height, $width, $height );
		} else {
			for($i = 0;$i < $width ; $i++) {
				for($j = 0;$j < $height ; $j++) {
					$reference = imagecolorat($img1,$i,$j);
					switch($angle) {
						case 90: if(!@imagesetpixel($img2, ($height - 1) - $j, $i, $reference )){return false;} break;
						case 180: if(!@imagesetpixel($img2, $width - $i, ($height - 1) - $j, $reference )){return false;} break;
						case 270: if(!@imagesetpixel($img2, $j, $width - $i, $reference )){return false;} break;
					}
				}
			}
		}
	}
	// Write the new file back
	if (in($ext,'jpg,jpeg')) {
	 imagejpeg($img2,$filename,$quality);
	} else if ($ext=='gif') {
	 imagegif($img2,$filename);
	} else if ($ext=='png') {
	 imagepng($img2,$filename);
	}
	imagedestroy($img1); imagedestroy($img2);
	return true;
}

// SYNC STUFF...
// -------------
function callServer($action,$data=[]) {
	$curl=curl_init();
	$data['action']=$action;
	$data['salt']=rand(0,999);
	$url="https://www.johnpeelarchive.com/archiver/ajax.php";
	// echo "<p>CALLING ".$url." WITH ACTION ".$action."...</p>";
	curl_setopt ($curl, CURLOPT_URL, $url);
	curl_setopt ($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: multipart/form-data"]);
	$json=curl_exec($curl);
	// echo "<p>RETURNED: ".stest($json)."</p>";
	return (array)json_decode($json,true);
}

function getSyncInfo() {
	global $DB;
	$count=callServer('count',['table'=>'records']);
	$remoteCount=getIfSet($count,'count',0);
	$maxRecordID=getIfSet($count,'maxID',0);
	$maxRecord=getRecordInfo($maxRecordID);
	$localCount=$DB->GetOne("SELECT COUNT(1) FROM records");
	if ($remoteCount<$localCount) {
		$changesText="
			<h3 class='yellow'>".($localCount-$remoteCount)." unsynced records</h3>
			<p><i>Only records entered after #".$maxRecordID."	<a href='record.php?recordID=".$maxRecordID."'>".getIfSet($maxRecord,'artist')." ".getIfSet($maxRecord,'title')."</a> will be synced.</p>
		";
	} else {
		$changesText="";
	}
	return [
		'remoteCount'=>$remoteCount,
		'maxRecordID'=>$maxRecordID,
		'maxRecord'=>$maxRecord,
		'localCount'=>$localCount,
		'changesText'=>$changesText
	];
}

function sync() {
	echo "<p class='green'>SYNCING...</p>";
	$ok=doPush();
	if (!$ok) return false;
	$ok=doPull();
	return $ok;
}

function doPush() {
	global $syncable, $DB;
	$pushData=[];
	foreach (explode(',',$syncable) as $table) {
		$max=callServer('count',['table'=>$table]);
		$maxID=getIfSet($max,'maxID',0);
		$keyCol=$DB->getPKfromDD($table);
		// echo "<p class='green'>Building push request for ".$table." starting with ".$keyCol."=".$maxID."...</p>";
		$pushData[$table]=json_encode($DB->GetAll("SELECT * FROM ".$table." WHERE ".$keyCol.">".$maxID,1));
	}
	echo "<p class='yellow'>Pushing...</p>";
	$push=callServer('push',$pushData);
	return (getIfSet($push,'rc',2)>0)?false:true;
}

function doPull() {
	global $syncable, $DB;
	$stats=[];
	echo "<p class='yellow'>Pulling...</p>";
	$pull=callServer('pull');
	if (!$pull) {
		echo "<p class='yellow'>PULL RETURNED NO RECORDS :(</p>";
	} else {
		echo "<p class='yellow'>Pulled ".sizeOf($pull['data']['records'])." records</p>";
		echo "<p class='yellow'>Updating local database...</p>";
		foreach (explode(',',$syncable) as $table) {
			$stats[$table]=0;
			$tableData=getIfSet($pull['data'],$table);
			if ($tableData && sizeOf($tableData)>10) {
				// Do some primitive backups...
				$DB->execute("DROP TABLE IF EXISTS tmp".$table);
				$DB->execute("CREATE TABLE tmp".$table." AS SELECT * FROM ".$table);
				$keyCol=$DB->getPKfromDD($table);
				foreach ($tableData as $row) {
					$DB->doUpdate($table,$row,$row[$keyCol],$keyCol);
					$stats[$table]++;
				}
			}
		}
		test($stats,2);
	}
	return true;
}


?>

