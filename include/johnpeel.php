<?php
// include folder end of johnpeel ajax
// key is 8c979e3f83c3c93068f7470a9ae6c78b for testing (delete this pls)

include_once('society/DB.php');
include_once('society/Cache.php');

$DB=new DB('johnpeel','thelookoflove','johnpeel');
startSession('johnpeel');
//$rows=$DB->GetAll('SELECT * FROM plays');
$cacheFace = new Cache('johnpeel','127.0.0.1');

//var_dump($_SESSION);

$action=p('action');

if ($action=='login') {
	$username=p('username',false);
	$password=p('password',false);
	$codedPass=md5('beefteeth'.$password);
	$foundUser=$DB->GetRow("SELECT * FROM users WHERE username='".$username."' AND password='".$codedPass."'");
	if ($foundUser) {
		$DB->execute("UPDATE users SET loginCount=loginCount+1 WHERE userID=".$foundUser['userID']);
		$_SESSION['userID']=$foundUser['userID'];
		//echo "<h1>Welcome to JPA, ".$foundUser['screenName']."<h1><br>";
		//echo json_encode(array('rc'=>0,'msg'=>'Login successful! Username is '.$foundUser['username'].' and your login count is '.($foundUser['loginCount']+1)));
	} else {
		redir("login.php?msg=Username and password do not match!");
		die;
	}
}

if ($action=='logout') {
	$_SESSION['userID']=false;
	//echo json_encode(array('rc'=>0,'msg'=>'Logout successful!'));
	redir("login.php?msg=You have successfully logged out!");
	die;
}

if (!array_key_exists('userID',$_SESSION) || !$_SESSION['userID']) {
	// key is alternative to login and used by ajax
	$key=p('key',false);
	if ($key!=md5('brasseye')) {
		if ($key) { // if responding to ajax, respond in JSON
			echo failure(); // this function lives in jpAjax.php
			die;	
		} else {
			redir("/admin/login.php?msg=You need to be logged in to do that!");
			die;
		}
		
	}
}

// returns full and partial plays for each box, per year starting Sept 1st
function getPlayTotals() {
	global $DB;
	$plays2014=$DB->GetArr("SELECT boxID,COUNT(*) as count FROM plays WHERE (ended=1 OR playedSeconds>29) AND playDate>'2014-09-10' AND playDate<'2015-09-11' GROUP BY boxID ORDER BY count DESC",'boxID');
	$total2014=$DB->GetRow("SELECT boxID,COUNT(*) as count FROM plays WHERE (ended=1 OR playedSeconds>29) AND playDate>'2014-09-10' AND playDate<'2015-09-11'");
	$plays2015=$DB->GetArr("SELECT boxID,COUNT(*) as count FROM plays WHERE (ended=1 OR playedSeconds>29) AND playDate>'2015-09-10' AND playDate<'2016-09-11' GROUP BY boxID ORDER BY count DESC",'boxID');
	$total2015=$DB->GetRow("SELECT boxID,COUNT(*) as count FROM plays WHERE (ended=1 OR playedSeconds>29) AND playDate>'2015-09-10' AND playDate<'2016-09-11'");
	return [
		2014=>['plays'=>$plays2014,'total'=>$total2014],
		2015=>['plays'=>$plays2015,'total'=>$total2015]
	];
}

// archiving tool moved to klikhome.co.uk
/*
// if user is archiverOnly - redirect them to mobile bit and away from any of the other backend stuff.....
$user=getUser($_SESSION['userID']);
if ($_SESSION['userID'] && !strpos($_SERVER['REQUEST_URI'],'archive.php') && $user['archivingOnly']==1) {
	redir("/admin/archive.php");
	die;
}*/

// anything else that happens will thus require login.
function getRecordBits($boxID=false) {
	global $DB;
	if ($boxID) {
		return $DB->GetAll('SELECT recordBitID, boxID, tags, recordID FROM recordBits WHERE boxID='.$boxID);
	} else {
		return $DB->GetAll('SELECT recordBitID, boxID, tags, recordID FROM recordBits');
	}
}

// rather than create a new one, allows one to be copied (easier starting point)
function duplicateRecordBit($recordBitID) {
	global $DB;
	return $DB->cloneRow('recordBits',$recordBitID);	
}

function getUser($userID) {
	global $DB;
	if (!$userID) return false;
	return $DB->getRow("SELECT * FROM users WHERE userID=".$userID);
}

function getRecords($boxID) {
	global $DB;
	if ($boxID) {
		return $DB->GetAll('SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) as recordTitle, jpID, type FROM records WHERE recordID IN (SELECT recordID from recordBits WHERE boxID='.$boxID.') ORDER BY jpID');
	} else {
		return $DB->GetAll('SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) as recordTitle, jpID, type FROM records ORDER BY jpID');
	}
}

function getRecord($recordID) {
	global $DB;
	$record=$DB->GetRow("SELECT * FROM records WHERE recordID=".$recordID);
	if (!$record) return false;
	$record['artist']=$DB->GetOne("SELECT title FROM artists WHERE artistID=".$record['artistID']);
	return $record;
}

// anything else that happens will thus require login.
function getTracks($boxID=false) {
	global $DB;
	if ($boxID) {
		$tracks=$DB->GetAll('SELECT * FROM tracks WHERE trackID IN (SELECT trackID FROM boxTracks WHERE boxID='.$boxID." ORDER BY ordering)");
	} else {
		$tracks=$DB->GetAll('SELECT * FROM tracks');
	}
	if (!$tracks) return false;
	foreach ($tracks as $id=>$track) {
		$tracks[$id]['media']=$DB->GetAll("SELECT * FROM media WHERE trackID=".$track['trackID']." ORDER BY priority");
	}
	return $tracks;
}

function getTrackBoxes($trackID) {
	global $DB;
	return $DB->getAll("SELECT * FROM boxTracks WHERE trackID=".$trackID);
}

function getRecordBoxes($dynamicOnly=false) {
	global $DB;
	if ($dynamicOnly) {
		$where=" WHERE dynamic=1";
	} else {
		$where="";
	}
	return $DB->GetAll('SELECT boxID, title FROM boxes'.$where);
}

function getRecordBox($boxID) {
	global $DB;
	return $DB->GetRow("SELECT * FROM boxes WHERE boxID=".$boxID);
}

// get the boxes that a record currently lives in
function getBoxRecords($recordID) {
	global $DB;
	return $DB->GetAll("SELECT * FROM boxRecords WHERE recordID=".$recordID);
}

function getRecordSleeve($recordID) {
	global $DB;
	return $DB->GetRow("SELECT * FROM sleeves WHERE recordID=".$recordID);
}

function recordBoxDropper($dynamicOnly=false) {
	$recordBoxes=getRecordBoxes($dynamicOnly);
	$output="<form method='post' action='".$_SERVER['PHP_SELF']."'>";
	$output.="<select name='boxID' onchange='this.form.submit()'>";
	$selected=((p('boxID',false)==false)?" selected":"");
	$output.="<option".$selected." value=false>- All -</option>";
	foreach ($recordBoxes as $recordBox) {
		$selected=((p('boxID',false)==$recordBox['boxID'])?" selected":"");
		$output.="<option".$selected." value=".$recordBox['boxID'].">".$recordBox['title']."</option>";
	}
	$output.="</select>";
	$output.="<input type='submit' value='Submit'>";
	$output.="</form>";
	return $output;
}

function addRecords($data,$recordType) {
	global $DB;
	if (!is_array($data)) $data=array($data);
	foreach ($data as $id=>$record) {
		$media=array();
		$record['type']=$recordType;
		$record['jpID']=$id;
		if (array_key_exists('album',$record) && !array_key_exists('title',$record)) {
			$record['title']=$record['album'];
		}
		if (array_key_exists('listen',$record) && count($record['listen'])>0) {
			foreach($record['listen'] as $listen) {
				$listen['type']='listen';
				array_push($media,$listen);
			}
			unset($record['listen']);
		}
		if (array_key_exists('info',$record) && count($record['info'])>0) {
			foreach($record['info'] as $info) {
				$info['type']='info';
				array_push($media,$info);
			}
			unset($record['listen']);
		}
		if (array_key_exists('videos',$record) && count($record['videos'])>0) {
			foreach($record['videos'] as $videos) {
				$videos['type']='videos';
				array_push($media,$videos);
			}
			unset($record['videos']);
		}
		if ($existing=$DB->getRow("SELECT recordID FROM records WHERE jpID='".$record['jpID']."'")) {
			$record['recordID']=$existing; // update previous record if already exists and avoid duplicates
		}
		$recordID=$DB->writeArray('records',$record);
		foreach ($media as $link) {
			$link['recordID']=$recordID;
			$linkID=$DB->writeArray('links',$link);
		}
	}
	return $data;
	//return true;
}


function getArtists() {
    global $DB;
    return $DB->GetAll("SELECT * FROM artists");
}

function getArtist($artistID) {
    global $DB;
    if (!$artistID) return false;
    return $DB->GetRow("SELECT * FROM artists WHERE artistID=".$artistID);
}

function getPageLoads($limit=false) {
	global $DB; 
	$limitText='';
	if ($limit) $limitText=' LIMIT '.$limit." ";
	$boxIDs=$DB->GetArr("SELECT boxID, count(boxID) AS countBox FROM pageLoads WHERE boxID>0 GROUP by boxID ORDER BY countBox DESC".$limitText,'boxID');
	$artistIDs=$DB->GetArr("SELECT artistID, count(artistID) AS countArtist FROM pageLoads WHERE artistID>0 GROUP by artistID ORDER BY countArtist DESC".$limitText,'artistID');
	$recordIDs=$DB->GetArr("SELECT recordID, count(recordID) AS countRecord FROM pageLoads WHERE recordID>0 GROUP by recordID ORDER BY countRecord DESC".$limitText,'recordID');
	return ['boxIDs'=>$boxIDs,'artistIDs'=>$artistIDs,'recordIDs'=>$recordIDs];
}

function getTags($tagTypeID=false) {
	global $DB;
	if ($tagTypeID) {
		return $DB->GetAll("SELECT * FROM tags WHERE tagTypeID=".$tagTypeID);
	} else {
		$tagTypes=$DB->GetArr("SELECT * FROM tagTypes");
		if (!$tagTypes) return false;
		//var_dump($tagTypes);
		$tags=[];
		foreach ($tagTypes as $tagTypeID=>$tagType) {
			$tags[$tagTypeID]=$DB->GetAll("SELECT * FROM tags WHERE tagTypeID=".$tagTypeID);
		}
		return $tags;
	}
}

function getTag($tagID) {
	global $DB;
	return $DB->GetRow("SELECT * FROM tags WHERE tagID=".$tagID);
}

function getTagTypes() {
	global $DB;
	return $DB->GetAll("SELECT * FROM tagTypes");
}

function getTagType($tagTypeID) {
	global $DB;
	return $DB->GetRow("SELECT * FROM tagTypes WHERE tagTypeID=".$tagTypeID);
}

function getRecordTags($recordID,$tagTypeID) {
	global $DB;
	$recordTags=$DB->GetAll("SELECT * FROM tags WHERE tagID IN (SELECT tagID FROM recordTags WHERE recordID=".$recordID.") AND tagTypeID=".$tagTypeID);
	return $recordTags;
}

function getRecordTagList($recordID) {
	$tagTypes=getTagTypes();
	global $DB;
	$list='';
	$div='';
	foreach ($tagTypes as $tagType) {
		$tags=getRecordTags($recordID,$tagType['tagTypeID']);
		if ($tags) {
			$list.=$div;
			$list.=$tagType['title'].": ";
			$comma='';
			foreach ($tags as $tag) {
				$list.=$comma.$tag['value'];
				$comma=', ';
			}	
		}
		$div='<br>';
	}
	return $list;
}

function getTagList($tagTypeID=false) {
	global $DB;
	if ($tagTypeID) {
		return $DB->GetArr("SELECT tagID, CONCAT(tagTypes.title,': ',tags.value) AS title FROM tags, tagTypes WHERE (tags.tagTypeID=tagTypes.tagTypeID) AND tags.tagTypeID=".$tagTypeID,'tagID');
	} else {
		return $DB->GetArr("SELECT tagID, CONCAT(tagTypes.title,': ',tags.value) AS title FROM tags, tagTypes WHERE (tags.tagTypeID=tagTypes.tagTypeID)",'tagID');	
	}
}

function getArtistList() {
	global $DB;
	return $DB->GetArr("SELECT artistID, title FROM artists ORDER BY title",'artistID');
}

function getRecordList() {
	global $DB;
	return $DB->GetArr("SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID),' - ',title) FROM records ORDER BY artistID",'recordID');
}

function getBoxList() {
	global $DB;
	return $DB->GetArr("SELECT boxID, title FROM boxes",'boxID');
}

function getContentList() {
	global $DB;
	return $DB->GetArr("SELECT contentID, LEFT(blurb, 40) FROM content",'contentID');
}

function getContent($contentID=false) {
    global $DB;
    if (!$contentID) {
        return $DB->GetAll("SELECT * FROM content");
    } else {
        return $DB->GetRow("SELECT * FROM content WHERE contentID=".$contentID);    
    }
}

function getContentAttachments($contentID) {
	global $DB;
	$contentLinks=$DB->GetAll("SELECT * FROM contentLinks WHERE contentID=".$contentID);
	if (!$contentLinks) return false;
	$types=['tagID','recordID','artistID','boxID'];
	$attachments=['tagID'=>[],'recordID'=>[],'artistID'=>[],'boxID'=>[]];
	foreach ($contentLinks as $contentLink) {
		foreach ($types as $type) {
			if (isset($contentLink[$type])) {
				$attachments[$type][$contentLink['contentLinkID']]=$contentLink[$type];
				//array_push($attachments[$type],$contentLink[$type]);
			}
		}
	}
	return $attachments;
}

function listContentAttachments($contentID) {
	global $DB;
	$attachments=getContentAttachments($contentID);
	if (!$attachments) return false;
	$html="";
	foreach ($attachments as $type=>$things) {
		$label=$type;
		$stuff="";
		$comma="";
		foreach ($things as $attachment) {
			if ($type=='tagID') {
				$tag=getTag($attachment);
				$tagType=getTagType($tag['tagTypeID']);
				$label="";
				$stuff.=$tagType['title'].": ".$comma.$tag['value']."<br>";
			} else if ($type=='recordID') {
				$record=getRecord($attachment);
				$label="Record";
				$stuff.=$comma.$record['artist'].' - '.$record['title'];
			} else if ($type=='artistID') {
				$artist=getArtist($attachment);
				$label="Artist";
				$stuff.=$comma.$artist['title'];
			} else if ($type=='boxID') {
				$box=getRecordBox($attachment);
				$label="Record box";
				$stuff.=$comma.$box['title'];
			}
		}
		if (strlen($stuff)>0) $html.=((strlen($label)>0)?$label.": ":"").$stuff.((strlen($label)>0)?"<br>":"");
	}
	return $html;
}

//BITS STOLEN FROM RECORDBOX.PHP

// defaults to 
function getArtistCover($artist) {
	if (!is_array($artist)) $artist=getArtist($artist);
    global $DB;
    $record=$DB->GetRow("SELECT * FROM records WHERE artistID=".$artist['artistID']." LIMIT 1");
	$path=false;    
    if ($record['numImages'] && $record['numImages']>0) {
        $path=getRecordCover($record,1);
    }
    if ($path) return $path;
    return false;
}

function getRecordCover($record,$number=1) {
    if (!is_array($record)) $record=getRecord($record);
    // TODO (both here and in recordBox.php) - check if there is actually a jpID, and return blank sleeve if not
    $letter=substr($record['jpID'],0,1);
    if ($record['type']==1) { // albums
        return "/albums/img/".$letter."/".$record['jpID']."-".str_pad($record['card'],5,'0', STR_PAD_LEFT)."-".$number.".jpg";
    } else {
        return "/singles/img/".$letter."/".$record['jpID']."-".$number.".jpg";
    }
    return false; // not sure when this will happen but yeah
}

function getBoxCover($box) {
	if (!is_array($box)) $box=getRecordBox($box);
	$iconPath="/content/".$box['folder']."/box-icon.jpg";
	return $iconPath;
}


?>