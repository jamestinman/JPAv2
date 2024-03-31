<?php
set_time_limit(60*5); /* 5 mins */
ini_set('memory_limit', '8G');
include_once('archiver.php');
$DB=new DB('johnpeelarchive','thelookoflove','archiver');
startSession("johnpeel");
$syncable="records,pics,events"; // tables that are accessible for syncing
/*
Each JSON return object contains an rc:
  0 => OK
  1 => WARNING
  2 => ERROR
*/
header('Content-Type: application/json; charset=utf-8');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header('Access-Control-Allow-Origin: http://localhost:81');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Origin: http://localhost.dev');
header('Access-Control-Allow-Credentials: true');
header("Pragma: no-cache");

switch (p("action")) {

case "count":
	$table=p("table");
	if (!in($table,$syncable)) { echo json_encode(['rc'=>2, 'msg'=>"ERR - table not syncable"]); die; }
	echo json_encode(['rc'=>0, 'msg'=>"OK", 'count'=>$DB->GetOne("SELECT COUNT(1) FROM ".$table), 'maxID'=>$DB->GetOne("SELECT MAX(".$DB->getPKfromDD($table).") FROM ".$table)]);
	break;

// Push updates / adds records to _this_ database
case "push":
	$stats=[];
	foreach (explode(',',$syncable) as $table) {
		$stats[$table]=['added'=>0, 'updated'=>0, 'skipped'=>0];
		$data=json_decode(p($table),true);
		if ($data) {
			$keyCol=$DB->getPKfromDD($table);
			$maxID=$DB->GetOne("SELECT MAX(".$keyCol.") FROM ".$table);
			foreach ($data as $row) {
				$id=$row[$keyCol];
				if ($id>$maxID) {
					$newID=$DB->doInsert($table,$row,true);
					$stats[$table]['added']++;
				} else {
					$stats[$table]['skipped']++;
				}
			}
		}
	}
	echo json_encode(['rc'=>0, 'msg'=>"push OK", 'stats'=>$stats]);
	break;

// Pull _this_ entire database to replace that which is on the requesting system
case "pull":
	$data=[];
	foreach (explode(',',$syncable) as $table) {
		$data[$table]=$DB->GetAll("SELECT * FROM ".$table,1);
	}
	echo json_encode(['rc'=>0, 'msg'=>"pull OK", 'data'=>$data]);
	break;

/*
case "loadChunk":
	$sql="SELECT * FROM records WHERE iA=".fss(p("iA"));
	if (p("iB")) $sql.=" AND iB=".fss(p("iB"));
	$sql.=" ORDER BY iC";
	$data=$DB->GetArr($sql,'iC');
	echo json_encode(['rc'=>0, 'msg'=>"OK", 'data'=>$data]);
	break;

// Ping back the data sent, cleaning the json in the process
case "cleanJson":
	$data=$_POST["data"];
	$filename=$_POST["f"];
	file_put_contents($filename,json_encode($data));
	// Write out cleansed json to file
	echo json_encode(['rc'=>0, 'msg'=>"OK", 'filename'=>$filename]);
	break;
*/

case 'saveRecord':
  $record = p("record");
  if (!$record) badResponse('no record passed');
  $id = $record['id'];
  // We can either get a rec[recordID] or a ref[iAiBiC]
  if (startsWith($id,'rec')) {
    $recordID = str_replace('rec','',$id);
    $existingRecord = getRecordInfo($recordID);
    $r = false;
  } else {
    $recordRef = str_replace('ref','',$id);
    $r = digestRef($recordRef);
    $existingRecord = getRecord($r['iA'],$r['iB'],$r['iC'],$r['iD']);
  }
  $recordID = getIfSet($existingRecord, 'recordID', 0);
  if ($recordID) {
    $DB->doUpdate('records',$record,$recordID);
    $msg = 'Update OK';
  } else {
    if ($r) {
      $record['iA'] = $r['iA'];
      $record['iB'] = $r['iB'];
      $record['iC'] = $r['iC'];
      $record['iD'] = $r['iD'];
      $record['infoEnteredByUserID'] = $_SESSION['userID'];
      $record['infoEnteredDate'] = sqlNowTime();
      $recordID = $DB->doInsert('records',$record);
      $msg = "Insert OK";
    }
  }
  $newRecord = getRecordInfo($recordID);
  goodResponse(['id'=>$id, 'recordID'=>$recordID, 'ref'=>$newRecord['ref'], 'msg'=>$msg]);
  break;

case 'test':
  goodResponse(['msg'=>"Ok?", 'arr'=>['one'=>['a'=>'ok','b'=>'ok']]]);

default:
	echo json_encode(['rc'=>1, 'msg'=>"No action passed"]);
	break;
}
?>
