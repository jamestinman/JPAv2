<?php
// JPA Player ajax
// used for system side ajax stuff like play logging
// logs using filename for now - will expand to actually use trackIDs once the whole site is more DB-centric

include_once('../../include/johnpeel.php');

switch ($action) {
    case "login":
        // dealt with in johnpeel.php
        break;

    case "logout":
        // dealt with in johnpeel.php
        break;

    case "logPlay":
        //echo 'Logging a play';
        $filename=p('filename');
        $ended=p('ended',1);
        $playedSeconds=p('playedSeconds',false);
        if (!$playedSeconds) $playedSeconds=0;
        $boxID=0;
        $recordID=0;
        if (locate("boxID",p('boxID'))) {
            $boxID=locate("boxID",p('boxID'));
        } else if (locate("recordID",p('boxID'))) {
            $recordID=locate("recordID",p('boxID'));
        }
        $trackID=$DB->GetOne('SELECT trackID FROM media WHERE source="'.$filename.'"');
        if ($trackID) {
        	$DB->execute('INSERT INTO plays (trackID, playDate, ended, boxID, recordID, playedSeconds) VALUES ("'.$trackID.'",NOW(), '.$ended.', '.$boxID.', '.$recordID.', '.$playedSeconds.')');
        	echo json_encode(array("rc"=>2,"msg"=>"Play successfully logged"));
        } else {
        	echo failure();
        }
        break;

    case "showPlays":
        $boxID=p('boxID',false);
        if (!$boxID) {
            $which='';
        } else {
            $which=' AND boxID='.$boxID;
        }
        $finPlays=$DB->GetAll('SELECT trackID,COUNT(*) as count FROM plays WHERE ended=1'.$which.' GROUP BY trackID ORDER BY count DESC;');
        $plays=$DB->GetAll('SELECT trackID,COUNT(*) as count FROM plays WHERE ended IN (0,1)'.$which.' GROUP BY trackID ORDER BY count DESC;');
        $output=array();
        foreach ($plays as $play) {
            $totalFinPlays=0;
            $track=$DB->GetRow('SELECT * FROM tracks WHERE trackID='.$play['trackID']);
            $trackName=$track['artist'].' - '.$track['title'];
            $totalPlays=$play['count'];
            foreach ($finPlays as $finPlay) {
                if ($finPlay['trackID']==$play['trackID']) {
                    $totalFinPlays=$finPlay['count'];
                }
            }
            $popular=$totalFinPlays/$totalPlays;
            $output[$play['trackID']]=array("trackName"=>$trackName,"plays"=>$totalPlays,"finPlays"=>$totalFinPlays,"fracFinished"=>$popular);
        }
        echo json_encode(array('rc'=>2,'msg'=>'Log retrieved','data'=>$output,'boxID'=>$boxID));
        break;

    case "getBoxes":
        $boxes=$DB->GetAll('SELECT * FROM boxes');
        if ($boxes) {
            $tracksByBox=array();
            foreach($boxes as $box) {
                $tracksByBox[$box['boxID']]=$DB->GetAll('SELECT * FROM tracks WHERE trackID IN (SELECT trackID FROM boxTracks WHERE boxID='.$box['boxID'].')');
            }    
            echo json_encode(array("rc"=>2,"msg"=>"Boxes retrieved","boxes"=>$boxes,"tracks"=>$tracksByBox));
        } else {
            echo failure();
        }
        break;

    case "addRecords":
        $data=p('data',false);
        $recordType=p('recordType',false);
        if (!$data || !$recordType) {
            echo ajaxError('ERROR: data or recordType missing');
            die;
        }
        $success=addRecords(json_decode($data,true),$recordType);
        if (!$success) echo ajaxError('something went utterly horribly there');
        echo json_encode(array("rc"=>2,"msg"=>"Records totally added",'data'=>$success));
        break;

  default:
    echo failure();
}

function ajaxError($msg) {
    return json_encode(array("rc"=>1,"msg"=>$msg));
}

// used by ajax mostly
function failure() {
    return json_encode(array("rc"=>0,"msg"=>"Call failed"));
}

?>