<?php

// this file contains functions for building record box pages
// for more generic system-side functionality (and login stuff for backend) see johnpeel.php

set_time_limit(30);

include_once('society/DB.php');
include_once('society/Cache.php');
$DB=new DB('johnpeel','thelookoflove','johnpeel');
startSession("johnpeel");
$cacheFace = new Cache('johnpeel','127.0.0.1');

// work out where we are from URL (or passed URL)
function getRecordBoxID($name=false) {
    global $DB;
    if (!$name) $name=getThisFolder($_SERVER['REQUEST_URI']); // get name from URL
    if ($name=='ajax') { // boxID will have been passed insteadt
        if (array_key_exists('boxID',$_GET) && $_GET['boxID']>0) {
            return $_GET['boxID'];
        }
    }
    $box=$DB->GetRow("SELECT * FROM boxes WHERE folder='".$name."'");
    if ($box) return $box['boxID'];
    return false;
}

// used to parse 
function findRecord($artist,$title) {
    global $DB;

    //$moddedArtist=preg_replace('/[^a-zA-Z\s]/',' ',$artist);
    $moddedArtist=preg_replace("/[^a-zA-Z0-9\s]/",' ',$artist);
    $moddedTitle=preg_replace("/[^a-zA-Z0-9\s]/",' ',$title);
    //$moddedTitle=preg_replace('/[^a-zA-Z\s]/',' ',$title);

    // todo: remove double spaces, allow numeric characters

    //var_dump($artist);
    //var_dump($title);

    return $DB->GetRow("SELECT * FROM records WHERE (((SELECT title FROM artists WHERE artists.artistID=records.artistID) LIKE '".$moddedArtist."') OR ((SELECT customPath FROM artists WHERE artists.artistID=records.artistID) LIKE '".$artist."')) AND ((title LIKE '".$moddedTitle."') OR (customPath LIKE '".$title."'))");
}

function findArtist($artist) {
    global $DB;
    //$moddedArtist=preg_replace('/[^a-zA-Z\s]/',' ',$artist);
    $moddedArtist=preg_replace("/[^a-zA-Z0-9\s]/",' ',$artist);
    return $DB->GetRow("SELECT * FROM artists WHERE title='".$moddedArtist."' OR customPath='".$artist."'");
}

function findTag($tag) {
    global $DB;
    $moddedTag=preg_replace("/[^a-zA-Z0-9\s]/",' ',$tag);
    $tag=$DB->GetRow("SELECT * FROM tags WHERE value='".$moddedTag."' OR customPath='".$tag."'");
    // if tag not found, obviously false
    if (!$tag) return false;
    // check if there are any records or attached content, otherwise no page
    $records=searchRecords(['tagID'=>$tag['tagID']]);
    $attachments=getAttachedContent(['tagID'=>$tag['tagID']]);
    if (!$records && !$attachments) return false;
    return $tag;
}

function getRecordBoxData($boxID) {
    global $DB;
    return $DB->GetRow("SELECT * FROM boxes WHERE boxID=".$boxID);
}

function recordPageCount($boxID=false,$recordID=false,$artistID=false) {
    global $DB;
    $pageLoad=[];
    if ($boxID) $pageLoad['boxID']=$boxID;
    if ($recordID) $pageLoad['recordID']=$recordID;
    if ($artistID) $pageLoad['artistID']=$artistID;
    $pageLoad['loadDate']=sqlNowTime();
    return $DB->writeArray('pageLoads',$pageLoad);
}

// retrieves record - to do - check whether box has started yet and redirect to generic record boxes page if not.
function getRecordBox($boxID) {
    global $cacheFace;
    
    $box=$cacheFace->get('jpaBoxID'.$boxID);
    if ($box) return $box;

	$box=getRecordBoxData($boxID);
	if (!$box) {
        boxFail();
        return false;
    } else if ($box['cmsBox']==1) {
        return getCMSRecordBox($boxID);
    } else {
        $box['playlist']=buildBoxPlaylist($box['boxID']);
        $box['identifier']='boxID'.$box['boxID'];
        $box['title']=(($box['pageTitle'])?$box['pageTitle']:"John Peel Archive: ".$box['title']); // pageTitle is format of 'Joe Boyd's Record Box', and is preferable
        $box['description']=$box['introText'];
        $box['slug']=$box['folder'];
        $box['header']=getBoxHeader($box);
        $box['image']=(($box['shareImage'])?'/content/'.$box['folder'].'/'.$box['shareImage']:false); // will be false if no image - implement default at receiving end
        $box['tracks']=getBoxTracks($box['boxID']);
        $box['recordBits']=getRecordBits($box['boxID']);
        $box['socialButtons']=getSocialButtons($box);
        $box['video']=checkMainVideo($box);
        $cacheFace->set('jpaBoxID'.$boxID,$box,60*60); // save for an hour
        return $box;
    }
}

// collates record stuff for ajax output
function getRecordPage($recordID) {
    global $cacheFace;
    $recordPage=$cacheFace->get("jpaRecordPage".$recordID);
    if ($recordPage) return $recordPage;

    $record=getRecord($recordID);
    if (!$record) return false;
    $recordPage=array();
    $recordPage['record']=$record;
    $recordPage['identifier']='recordID'.$record['recordID'];
    $recordPage['title']="John Peel Archive: ".$record['artist']." - ".$record['title'];
    $recordPage['description']="Copy of ".$record['artist']." - ".$record['title'];
    $recordPage['recordBits']=buildRecordPage($recordID);
    $recordPage['image']=getRecordCover($record); // will be false if no image - implement default at receiving end
    $recordPage['playlist']=buildRecordPlaylist($recordID);
    $recordPage['slug']=getRecordSlug($recordID);
    $recordPage['socialButtons']=getSocialButtons($recordPage);
    $cacheFace->set("jpaRecordPage".$recordID,$recordPage,60*60);
    return $recordPage;
}

// collates artist stuff for ajax output
function getArtistPage($artistID) {
    global $cacheFace;
    $artistPage=$cacheFace->get("jpaArtistPage".$artistID);
    if ($artistPage) return $artistPage;

    $artist=getArtist($artistID);
    if (!$artist) return false;
    $artistPage=array();
    $artistPage['artist']=$artist;
    $artistPage['identifier']='artistID'.$artist['artistID'];
    $artistPage['title']="John Peel Archive: ".$artist['title'];
    $artistPage['description']="Records by ".$artist['title'];
    $artistPage['recordBits']=buildArtistPage($artistID);
    $artistPage['image']=getArtistCover($artist); // will be false if no image - implement default at receiving end
    $artistPage['playlist']=false;
    $artistPage['slug']=getArtistSlug($artistID);
    $artistPage['socialButtons']=getSocialButtons($artistPage);
    $cacheFace->set("jpaArtistPage".$artistID,$artistPage,60*60);
    return $artistPage;
}

// collates records of each letter for ajax output
function getLetterPage($letter) {
    global $cacheFace;
    $letterPage=$cacheFace->get("jpaLetterPage".$letter);
    if ($letterPage) return $letterPage;

    $records=searchRecords(['letter'=>$letter]);
    if (is_numeric($letter)) $letter='numbers';
    if (!$records) return false;
    $letterPage['title']="John Peel Archive: Records beginning with ".$letter;
    $letterPage['identifier']='letterID'.$letter;
    $letterPage['description']="Records beginning with ".$letter;
    $letterPage['recordBits']=buildLetterPage($letter);
    $letterPage['image']=getLetterCover($letter); // will be false if no image - implement default at receiving end
    $letterPage['playlist']=false;
    $letterPage['slug']=getArtistSlug($letter);
    $letterPage['socialButtons']=getSocialButtons($letterPage);
    $cacheFace->set("jpaLetterPage".$letter,$letterPage,60*60);
    return $letterPage;
}

// collates information for tagPage
function getTagPage($tagID) {
    global $cacheFace;
    $tagPage=$cacheFace->get("jpaTagPage".$tagID);
    if ($tagPage) return $tagPage;
    $tag=getTag($tagID);
    if (!$tag) return false;
    $records=searchRecords(['tagID'=>$tagID]);
    $tagPage=array();
    $tagPage['title']="John Peel Archive: ".$tag['value'];
    $tagPage['identifier']='tagID'.$tag['tagID'];
    $tagPage['description']="Records by ".$tag['value'];
    $tagPage['recordBits']=buildTagPage($tagID);
    $tagPage['image']=getRecordCover(getFirstRecord($records)); // will be false if no image - implement default at receiving end
    $tagPage['playlist']=false; // could be a thing in future though
    $tagPage['slug']=getTagSlug($tagID);
    $tagPage['socialButtons']=getSocialButtons($tagPage);
    $cacheFace->set("jpaTagPage".$tagID,$tagPage,60*60);
    return $tagPage;
}

// helper function used for choosing first record - could be improved in future to allow a selection or perhaps random?
function getFirstRecord($records) {
    if (!$records || !is_array($records)) return false;
    foreach ($records as $record) {
        return $record;
    }
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

function getContent($contentID) {
    global $DB;
    if (!$contentID) {
        return $DB->GetAll("SELECT * FROM content");
    } else {
        return $DB->GetRow("SELECT * FROM content WHERE contentID=".$contentID);    
    }
}

// $arr contains what we're looking for, which can include artistID, recordID, tagID for now
function getAttachedContent($arr) {
    global $DB;
    if (!$arr || !is_array($arr)) return false;
    $and='';
    if (isset($arr['boxID'])) {
        $and=" AND cl.boxID=".$arr['boxID'];
    }
    if (isset($arr['noBoxID'])) {
        $and=" AND cl.boxID IS NULL";
    }
    if (isset($arr['artistID'])) {
        return $DB->GetAll("SELECT c.*, cl.linkType, cl.boxID, cl.contentLinkID FROM content AS c,contentLinks AS cl WHERE c.contentID = cl.contentID AND cl.artistID=".$arr['artistID'].$and);
        //return $DB->GetAll("SELECT * FROM content WHERE contentID IN (SELECT contentID FROM contentLinks WHERE artistID=".$arr['artistID'].$and.")");
    } else if (isset($arr['recordID'])) {
        return $DB->GetAll("SELECT c.*, cl.linkType, cl.boxID, cl.contentLinkID FROM content AS c,contentLinks AS cl WHERE c.contentID = cl.contentID AND cl.recordID=".$arr['recordID'].$and);
        //return $DB->GetAll("SELECT * FROM content WHERE contentID IN (SELECT contentID FROM contentLinks WHERE recordID=".$arr['recordID'].$and.")");
    } else if (isset($arr['tagID'])) {
        return $DB->GetAll("SELECT c.*, cl.linkType, cl.boxID, cl.contentLinkID FROM content AS c,contentLinks AS cl WHERE c.contentID = cl.contentID AND cl.tagID=".$arr['tagID'].$and);
        //return $DB->GetAll("SELECT * FROM content WHERE contentID IN (SELECT contentID FROM contentLinks WHERE tagID=".$arr['tagID'].$and.")");
    } else if (isset($arr['linkType']) && isset($arr['boxID'])) {
        return $DB->GetAll("SELECT c.*, cl.linkType, cl.boxID, cl.contentLinkID FROM content AS c,contentLinks AS cl WHERE c.contentID = cl.contentID AND cl.linkType='".$arr['linkType']."'".$and);
    }
    return false; // couldn't find anything to find I guess
}

/* function for outputting directly into PHP pages */

function outputRecordBox($box) {
    global $cacheFace;
    if (!is_array($box)) $box=getRecordBox($box); // can pass ID or whole box
    recordPageCount($box['boxID'],false,false);

    $boxID=getIfSet($box,'boxID');
    if ($boxID) {
        $html=$cacheFace->get('jpaBox'.$boxID.'HTML');
        if ($html) return $html;
    }
    $html=$box['header'];
    $html.=$box['video'];
    if ($box['recordBits']) {
        foreach ($box['recordBits'] as $recordBit) {
            $html.=$recordBit['content'];
        }    
    }
    $html.='<script>';
    $html.='playlist='.json_encode($box['playlist']).';';
    $html.='</script>';
    $cacheFace->set('jpaBox'.$boxID.'HTML',$html,60*60);
    return $html;
}

function outputRecordPage($recordID) {
    global $cacheFace;
    recordPageCount(false,$recordID,false);

    $html=$cacheFace->get('jpaRecord'.$recordID.'HTML');
    if ($html) return $html;

    $html=buildRecordPage($recordID);
    
    $html.="<script>";
    $html.="playlist=".json_encode(buildRecordPlaylist($recordID)).";";
    $html.="</script>";
    $cacheFace->set('jpaRecord'.$recordID.'HTML',$html,60*60);
    return $html;
}

// doesn't do much for now - but just to bring them all in line
function outputArtistPage($artistID) {
    global $cacheFace;
    recordPageCount(false,false,$artistID);
    
    $html=$cacheFace->get('jpaArtist'.$artistID.'HTML');
    if ($html) return $html;

    if (is_array($artistID)) $artistID=$artistID['artistID']; // means artist object can be passed and it won't break
    $html=buildArtistPage($artistID);
    $cacheFace->set('jpaArtist'.$artistID.'HTML',$html,60*60);
    return $html;
}

function outputLetterPage($letter) {
    if (!$letter) return false;
    $html=buildLetterPage($letter);
    return $html;
}

function outputTagPage($tagID) {
    global $cacheFace;
    if (!$tagID) return false;

    $html=$cacheFace->get('jpaTag'.$tagID.'HTML');
    if ($html) return $html;

    $html=buildTagPage($tagID);
    $cacheFace->set('jpaTag'.$tagID.'HTML',$html,60*60);
    return $html;
}

function getRecordSlug($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false;
    $title=false;
    if ($record['customPath']) $title=$record['customPath'];
    $artist=getArtistSlug($record['artistID']);
    if (!$title) $title=wordToSlug($record['title']);
    return $artist."/".$title;
}

function getArtistSlug($artistID) {
    $artist=getArtist($artistID);
    if (!$artist) return false;
    if ($artist['customPath']) return $artist['customPath'];
    return wordToSlug($artist['title']);
}

function getLetterSlug($letter) {
    if (!$letter) return false;
    if (is_numeric($letter) || $letter='numbers') $letter='1';
    return wordToSlug('letter/'.tolowercase($letter));
}

function getTagSlug($tagID) {
    $tag=getTag($tagID);
    if (!$tag) return false;
    if ($tag['customPath']) return $tag['customPath'];
    return wordToSlug($tag['value']);
}

function getRecordBits($boxID) {
    global $DB;
    return $DB->GetAll("SELECT * FROM recordBits WHERE boxID=".$boxID." ORDER BY recordBitID");
}

function getBoxTracks($boxID) {
    global $DB;
    $tracks=$DB->GetAll("SELECT * FROM tracks, boxTracks WHERE (boxTracks.boxID=".$boxID." AND tracks.trackID=boxTracks.trackID) ORDER BY tracks.recordID, boxTracks.ordering;");
    if (!$tracks) return false;
    foreach ($tracks as $id=>$track) {
        $boxTrack=$DB->GetRow("SELECT * FROM boxTracks WHERE boxID=".$boxID." AND trackID=".$track['trackID']);
        $tracks[$id]=array_merge($tracks[$id],$boxTrack);
        $tracks[$id]['media']=$DB->GetAll("SELECT * FROM media WHERE trackID=".$track['trackID']." ORDER BY priority");
    }
    return $tracks;
}

function searchRecords($params) {
    global $DB;
    if (!is_array($params)) return false;
    //var_dump($params);
    $search="SELECT recordID FROM records";
    $limit=false;
    $link=" WHERE ";
    foreach ($params as $key=>$value) {
        switch ($key) {
            case 'avoidRecordID':
                $search.=$link."recordID!=".$value;
                $link=" AND ";
                break;
            case 'letter': // if letter is a number, search for any numbers instead
                if (is_numeric($value)) {
                    $search.=$link."artistID IN (SELECT artistID FROM artists WHERE SUBSTRING(LOWER(jpID),1,1) BETWEEN '0' AND '9')";
                } else {
                    $search.=$link."artistID IN (SELECT artistID FROM artists WHERE jpID LIKE '".substr($value,0,1)."%')";
                }
                $link=" AND ";

                break;
            case 'artist':
                $search.=$link."artistID=(SELECT artistID FROM artists WHERE title='".$value."')";
                $link=" AND ";
                break;
            case 'record':
                $search.=$link."title LIKE '".$value."'";
                $link=" AND ";
                break;
            case 'tagID':
                $search.=$link."recordID IN (SELECT recordID from recordTags WHERE tagID=".$value.")";
                $link=" AND ";
                break;
            case 'year':
                $search.=$link."recordID IN (SELECT recordID from recordTags WHERE tagID IN (SELECT tagID FROM tags WHERE value LIKE '".$value."' AND tagTypeID IN (SELECT tagTypeID FROM tagTypes WHERE title='Year')))";
                $link=" AND ";
                break;
            case 'genre':
                $search.=$link."recordID IN (SELECT recordID from recordTags WHERE tagID IN (SELECT tagID FROM tags WHERE value LIKE '".$value."' AND tagTypeID IN (SELECT tagTypeID FROM tagTypes WHERE title='Genre')))";
                $link=" AND ";
                break;
            case 'label':
                $search.=$link."recordID IN (SELECT recordID from recordTags WHERE tagID IN (SELECT tagID FROM tags WHERE value LIKE '".$value."' AND tagTypeID IN (SELECT tagTypeID FROM tagTypes WHERE title='Label')))";
                $link=" AND ";
                break;
            case 'limit':
                $limit=$value;
                break;
            default:
                // do nothing
        }
    }

    $recordIDs=$DB->GetArr($search); // not including limit as going to do that more obnoixously...
    if (!$recordIDs) return false;
    if ($limit && count($recordIDs)>$limit) {
        $records=prioritiseRecords($recordIDs,$limit);
    } else {
        $records=array(); // this is annoying but ensures that all records that are fetched go through getRecord()    
        foreach ($recordIDs as $recordID) {
            $records[$recordID]=getRecord($recordID);
        }
    }
    return $records;
}

function getRandomRecords($limit=1) {
    global $DB;
    $records=$DB->GetAll("SELECT recordID FROM records ORDER BY RAND() LIMIT ".$limit);
    if (!$records) return false;
    foreach ($records as $id=>$record) {
        $records[$id]=getRecord($record['recordID']);
    }
    return $records;
}

// sorts array based on subarray 'occurrences'
function sortByPopularity($array) {
    usort($array, "sortByPopularityMath");
    return $array;
}

// sorting function for above
function sortByPopularityMath($a, $b) {
  return $b["popularity"] - $a["popularity"];
}

// when we have too many records to show, we're going to use this to put them in order and have a nice time
// if no limit to records, will order them by popularity anyway
// will turn numbers even because half records is too much for my mind to cope with r/n
function prioritiseRecords($recordIDs, $limit=false) {
    global $DB;
    if ($limit) $limit=(($limit>1)?intval($limit/2)*2:2);
    $records=[];
    foreach ($recordIDs as $recordID) {
        if (!is_array($recordID)) {
            $record=getRecord($recordID);
        } else {
            $record=$recordID;
        }
        $popularity=$DB->GetOne("SELECT count(recordID) AS countRecord FROM pageLoads WHERE recordID=".$recordID);
        if (!$popularity) $popularity=0;
        $record['popularity']=$popularity;
        $records[$recordID]=$record;
    }
    $records=sortByPopularity($records);
    if ($limit) {
        $halfLimit=intval($limit/2);
        $firstHalf=array_slice($records,0,$halfLimit);
        $secondHalf=array_slice($records,(count($records)-$halfLimit),$halfLimit);
        $records=array_merge($firstHalf,$secondHalf);    
    }
    
    return $records;
}

// can't find box or box isn't open to the public yet (yet to implement)
function boxFail() {	
	//echo "<div style='color:red;font-size:2em;'>BOX FAIL</div>";
	//header("Location: /boxes.html");
	//die();
}

// gets folder in which we are currently working - ignores ?query=strings and #anchor-tags-etc
function getThisFolder($url) {
	$pieces=explode('/',$url);
	$folder='';
	foreach ($pieces as $piece) {
		if ($piece && strlen($piece)>1 && strpos($piece,'?')===false && strpos($piece,'#')===false) $folder=$piece;
	}
	return $folder;
}

/*
// returns the boxID - this is used to tell the JS which playlist to refer to
function getBoxID() {
	global $recordBox;
	return $recordBox['boxID'];
}*/

// checks if there is a main video (outside the player system and puts the code in if applicable)
function checkMainVideo($box) {
	if (!is_null($box['mainVideo'])) {
		return insertMainVideo($box);
	} else {
		return '<!-- no main video on this page -->';
	}
}
//////////////////////////////////
// re-usable code for record boxes
// change here for all pages
//////////////////////////////////

// uses pageinfo object which is returned by boxes, pages etc
// todo: add keywords derived from DB stuff
function getMetadata($pageInfo) {
    // page basics
    $html='
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>'.$pageInfo["title"].'</title>
        <meta name="description" content="'.$pageInfo["description"].'">
    
        <meta property="og:title" content="'.$pageInfo["title"].'"/>
        <meta property="og:url" content="http://johnpeelarchive.com/'.$pageInfo["slug"].'"/>
        <meta property="og:image" content="http://johnpeelarchive.com'.$pageInfo["image"].'"/>
        <meta property="og:site_name" content="John Peel Archive"/>
        <meta property="og:description" content="'.htmlspecialchars($pageInfo["description"]).'">
    
        <meta name="twitter:card" content="summary">
        <meta name="twitter:url" content="http://johnpeelarchive.com/'.$pageInfo["slug"].'">
        <meta name="twitter:title" content="'.$pageInfo["title"].'"/>
        <meta name="twitter:description" content="'.htmlspecialchars($pageInfo["description"]).'">
        <meta name="twitter:image" content="http://johnpeelarchive.com'.$pageInfo["image"].'">
        <meta name="twitter:creator" content="@johnpeelarchive">
    ';
    return $html;
}

function getCSS() {
    $css='
        <link rel="stylesheet" href="/css/boxes/normalize.min.css">
        <link rel="stylesheet" href="/css/popups.css">
        <link rel="stylesheet" href="/css/boxes/main.css">
        <link rel="stylesheet" href="/css/boxes/jpaplay.css">
        <link rel="stylesheet" href="/js/boxes/fancybox/jquery.fancybox.css">
        <link rel="stylesheet" href="/js/boxes/video-js/video-js.css">
    ';
	return $css;
}

// three.js libraries are now being loaded dynamically to stop IE errors
function getLibraries() {
    $js='
        <script src="/js/boxes/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
        <script src="/js/boxes/jquery.min.js"></script>
        <script src="/js/boxes/video-js/video.js"></script>
        <script src="/js/boxes/fancybox/jquery.fancybox.min.js"></script>
        <!--<script src="/js/boxes/three.min.js"></script>
        <script src="/js/boxes/ThreeCSG.js"></script>-->
        <script src="/js/boxes/scrollfire/jquery.scrollfire.js"></script>
        <script src="/js/boxes/soundmanager/soundmanager2-jsmin.js"></script>
        <script src="/js/libs/galleria-1.4.2.min.js"></script>
    ';
    $js.=getTagManager();
	return $js;
}

//TODO: remove these soon as unnecessary
function getMainSiteScripts() {
    $js='';
    $js='
        <script src="/data/records/allRecords.js?v=2"></script>
        <script src="/js/johnpeel.js"></script>
        <script src="/js/popups.js"></script>
    ';
    return $js;
}

function getRecordBoxJS() {
    $version=rand(1,100);
	// starting boxID doesn't matter - it just holds an identifier of currently playing playlist, so 0 is fine
    $boxID=0;
    $js='<script>var jpaBoxID='.$boxID.';</script>'; // phasing this stuff out
    $js.='<script src="/js/boxes/jpaplay.js?v='.$version.'"></script>
        <script src="/js/boxes/jpaFx.js?v='.$version.'"></script>
        <script src="/js/boxes/easing.js"></script>
        <script src="/data/boxes/playlist.js?v='.$version.'"></script>
        <script src="/js/boxes/plugins.js"></script>
    ';
	return $js;
}

function getSocialAPIs() {
	$js='
		<script type="text/javascript" src="//apis.google.com/js/plusone.js">{lang: "en-GB", parsetags: "explicit"}</script>
        <script type="text/javascript" src="//connect.facebook.net/en_GB/all.js#xfbml=1&appId=28316481223"></script>
        <script type="text/javascript" src="//platform.twitter.com/widgets.js"></script>
        <script type="text/javascript" src="//assets.pinterest.com/js/pinit.js"></script>
	';
	return $js;
}

function getGoogleAnalytics() {
	$js="
        // our analytics
        (function(b,o,i,l,e,r){b.GoogleAnalyticsObject=l;b[l]||(b[l]=
        function(){(b[l].q=b[l].q||[]).push(arguments)});b[l].l=+new Date;
        e=o.createElement(i);r=o.getElementsByTagName(i)[0];
        e.src='//www.google-analytics.com/analytics.js';
        r.parentNode.insertBefore(e,r)}(window,document,'script','ga'));
        ga('create','UA-31313224-1');ga('send','pageview');
";
	return $js;
}

function getTagManager() {
    $html=
    "<!-- Google Tag Manager -->
    <noscript><iframe src='//www.googletagmanager.com/ns.html?id=GTM-P9H4HD'
    height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-P9H4HD');</script>
    <!-- End Google Tag Manager -->";
    return $html;
}

function insertMainVideo($box) {
	$filename=$box['mainVideo'];
    $title=$box['videoInfo'];
    $home='';
    $output='
		<div class="mainvid" id="mainVideo" style="overflow: hidden;">
            <div id="mainFilm" class="inlineVideo main-video-container video-js vjs-default-skin">
                    <video width="auto" height="auto" controls="true" id="mainFilmJS" poster="/content/'.$box['folder'].'/main-video-shield.jpg">
                        <source src="'.$home.'/video/'.$filename.'.mp4" type="video/mp4">
                        <source src="'.$home.'/video/'.$filename.'.ogv" type="video/ogg">
                        <source src="'.$home.'/video/'.$filename.'.webm" type="video/webm">
                        <track label="English" kind="subtitles" srclang="en" src="'.$home.'/video/'.$filename.'.vtt">
                    </video>
                <div class="mainVideoShield"><img src="/content/'.$box['folder'].'/main-video-shield.jpg"/></div>
                <div class="mainVideoControls">
                    <div class="mainVideoBtn">
                        <a onClick="playMainFilm();"><img src="/img/boxes/main-video-play-btn.png" class="fade"></a>
                    </div>
                    <div class="mainVideoInfo">';
    $output.=$title; // video caption from database
    $output.='    
                    </div>
                </div>
            </div>
        </div>
	';
	return $output;
}

//$pageInfo can actually be a box, artistPage or recordPage, so long as they've already had title, description, slug and image worked out
function getSocialButtons($pageInfo) {
    $pageInfo['url']="http://johnpeelarchive.com/".$pageInfo['slug'];
    $output="
                <script>
                    function fbs_click() {u=location.href;t=document.title;window.open('http://www.facebook.com/sharer.php?u='+encodeURIComponent(u)+'&t='+encodeURIComponent(t),'sharer','toolbar=0,status=0,width=626,height=436,left='+(screen.availWidth/2-225)+',top='+(screen.availHeight/2-150)+''); return false;}
                </script>
                <a rel='nofollow' href='http://www.facebook.com/share.php?u=<;url>' onClick='return fbs_click()' target='_blank' class='facebook'><img src='/img/boxes/facebook.png'></a>
                <a href='https://twitter.com/share?url=".$pageInfo['url']."&text=".rawurlencode($pageInfo['description'])."' onClick='javascript:window.open(this.href,\"\", \"menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=285,width=600,left=\"+(screen.availWidth/2-225)+\",top=\"+(screen.availHeight/2-150)+\"\");return false;' class='twitter'><img src='/img/boxes/twitter.png'></a>
                <a href='https://plus.google.com/share?url=".$pageInfo['url']."' onClick='javascript:window.open(this.href,\"\", \"menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600,left=\"+(screen.availWidth/2-225)+\",top=\"+(screen.availHeight/2-150)+\"\");return false;' class='googleplus'><img src='/img/boxes/googleplus.png'></a>
    ";
    return $output;
}

// $pageInfo is passed by the record box, page or artist page and consists of same info
function getPageBottom($pageInfo=false) {
    /*
    if (!$pageInfo) {
        global $recordBox;
        $pageInfo=$recordBox;
    }*/
    $output='
        <div style="clear:both"></div>
        <div class="share">
            <span>Share This Page</span>
        </div>
        <div class="shareButtons">
    ';
    $output.=getSocialButtons($pageInfo);
    $output.='
        </div>
        <div style="clear:both"></div>
        <div style="height:235px;"></div>
    ';
    return $output;
}

function getBoxHeader($box) {
    $output="
        <div id='headerimageWrapper'> 
            <div id='headerimageContainer' class='parallax'>
                <img id='headerimage' src='/content/".$box['folder']."/".$box['headerImage']."'>
            </div>";
    if (!is_null($box['introText'])) {
        $output.="
            <div class='intro".(($box['headerAlignRight']==1)?" right":"")."' id='introText'>
                <h1>".$box['title']."</h1>
                <div style='clear:both;'></div>
                <p>".$box['introText']."</p>
            </div>
        ";
    }
    $output.="
        </div>
    ";
    return $output;
}

function getRecord($recordID) {
    global $DB;
    if (!$recordID) return false;
    $record=$DB->getRow("SELECT * FROM records WHERE recordID=".$recordID);
    if (!$record) return false;
    $record['artist']=$DB->GetOne("SELECT title FROM artists WHERE artistID=".$record['artistID']);
    $record['icon']=getRecordIcon($record);
    // TODO: add sleeve image and page link etc.
    return $record;
}

function getRecordLinks($recordID) {
    global $DB;
    if (is_array($recordID)) $recordID=$recordID['recordID']; // can take whole record entry too
    return $DB->getAll("SELECT * FROM links WHERE recordID=".$recordID);
}

function getYouTubeVideo($artist,$title, $id) {
    $html="
    <div class='vid' id='".$id."'>
        <div class='inlineVideo'></div>
        <div class='video'>
            <div class='playBtn play'></div>
            <div class='videoInfo'>
                <span>
                    <strong>".$artist."</strong><br>".$title."
                </span>
            </div>
        </div>
    </div>";
    return $html;
}

function getAudioPlayer($artist, $title, $id,$addClass='') {
    if (strlen($addClass)>0) $addClass=' '.$addClass;
    $html="
        <div class='audioLink".$addClass."' id='".$id."'>
            <div class='playBtn play'></div>
            <div class='audioInfo'><span><strong>".$artist."</strong><br/>".$title."</span></div>
        </div>";
    return $html;
}

function getLink($link) {
    $html="<a href='".$link['url']."'>";
    if ($link['title']) {
        $html.=$link['title'];
    } else if ($link['track']) {
        $html.=$link['track'];
    } else {
        $html.=$link['url'];
    }
    $html.="</a>";
    return $html;
}

function getPictureViewer($images,$recordID) {
  $html="
    <div class='pictureViewer' id='pictureViewer".$recordID."'>
      <div class='pictureViewerMain'>
        <img src='".$images[0]."'>
      </div>
      <div class='pictureViewerSmall'>";
  foreach ($images as $image) {
    $html.="<img src='".$image."' class='pictureViewer".$recordID."'>";
  }
  $html.=
    "</div> <!-- .pictureViewerSmall -->
  </div> <!-- .pictureViewer -->";
  return $html;
}

function getRecordCard($record) {
  if (!is_array($record)) $record=getRecord($record);
  if ($record['type']!=1) return false;
  $letter=substr($record['jpID'],0,1);
  $html="<div class='recordCard'>";
  $html.="<img src='/albums/img/cards/".$letter."/".$record['jpID'].".jpg' />";
  $html.="</div> <!-- .recordCard -->";
  return $html;
}

function getRecordHeader($images) {
  if (!is_array($images) || count($images)==0) return '';
  return "
    <div class='recordHead headimage parallax'>
      <img src='".$images[0]."'>
    </div>";
}

function findRecordInBoxes($recordID) {
    global $DB;
    $recordBitBoxes=$DB->getArr('SELECT boxID FROM recordBits WHERE recordID='.$recordID,'boxID');
    if (!$recordBitBoxes) $recordBitBoxes=[];
    $boxRecordBoxes=$DB->getArr('SELECT boxID FROM boxRecords WHERE recordID='.$recordID,'boxID');
    if (!$boxRecordBoxes) $boxRecordBoxes=[];
    $boxIDs=array_unique(array_merge($recordBitBoxes,$boxRecordBoxes));
    if (count($boxIDs)==0) return false;
    return $boxIDs;
}

function getBoxIcons($recordID) {
    $boxIDs=findRecordInBoxes($recordID);
    $html="<div class='recordBoxLinks'>";
    $html.="<p>Record boxes containing this record...</p>";
    $links=0;
    if ($boxIDs) {
        foreach ($boxIDs as $boxID) {
            $box=getRecordBoxData($boxID);
            if ($box && $box['active']==1) {
                $links++;
                //var_dump($box);
                $iconPath="/content/".$box['folder']."/box-icon.jpg";
                $alt="This record can be found in the ".$box['title']." record box";
                
                $html.="<img onClick='changeBox(".$boxID.");' class='recordBoxIcon' alt='".$alt."' src='".$iconPath."'>";
                
            }
        }
    }
    $html.="</div> <!-- .recordBoxLinks -->";
    if ($links>0) return $html;
    // or else...
    return false;
}

function getRecordIcons($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false;
    // get other records by this artist
    $limit=12;
    $otherRecords=searchRecords(array('avoidRecordID'=>$record['recordID'],'artist'=>$record['artist'],'limit'=>$limit));
    $html="<div class='recordBoxLinks'>";
    if (count($otherRecords)>=$limit) {
        // show [more] tag, TODO
        $html.="<p>Some other records by this artist... ".getArtistLink($record['artistID'])."<small>(more...)</small></a></p>";
    } else {
        $html.="<p>Other records by this artist...</p>";    
    } 
    $links=0;
    if ($otherRecords) {
        foreach ($otherRecords as $otherRecord) {
            $links++;
            $html.=getRecordIcon($otherRecord,'recordBoxIcon');
            /*$iconPath=getRecordCover($otherRecord);
            $alt=$otherRecord['artist']." - ".$otherRecord['title'];
            
            $html.="<img onClick='changeBox(false,\"".$otherRecord['jpID']."\");' class='recordBoxIcon' alt='".$alt."' src='".$iconPath."'>";
            */
        }
    }
    $html.="</div> <!-- .recordBoxLinks -->";
    if ($links>0) return $html;
    // or else...
    return false;
}

function getTagIcons($recordID,$tagName) {
    $record=getRecord($recordID);
    if (!$record) return false;
    $tag=findRecordTag($record['recordID'],$tagName);
    if (!$tag) return false;
    $limit=12;
    // get other records by this artist
    $otherRecords=searchRecords(array('avoidRecordID'=>$record['recordID'],$tagName=>$tag['value'],'limit'=>$limit));
    if (count($otherRecords)>=$limit) {
        //TODO: add [more] link
    }
    $html="<div class='recordBoxLinks'>";
    $html.="<p>Other records from this ".$tagName."...</p>";
    $links=0;
    if ($otherRecords) {
        foreach ($otherRecords as $otherRecord) {
            $links++;
            $html.=getRecordIcon($otherRecord,'recordBoxIcon');
            /*$iconPath=getRecordCover($otherRecord);
            $alt=$otherRecord['artist']." - ".$otherRecord['title'];
            
            $html.="<img onClick='changeBox(false,\"".$otherRecord['jpID']."\");' class='recordBoxIcon' alt='".$alt."' src='".$iconPath."'>";
            */
        }
    }
    $html.="</div> <!-- .recordBoxLinks -->";
    if ($links>0) return $html;
    // or else...
    return false;
}

function getArtistIcons($artist) {
    $html="<div class='recordBoxLinks'>";
    $html.="<p>More about ".$artist['title']."...</p>";
    $html.=getArtistIcon($artist,'recordBoxIcon');
    $html.="</div> <!-- .recordBoxLinks -->";
    return $html;
}

function getRecordIcon($record,$addClass="") {
    //$record=getRecord($recordID);
    $iconPath=getRecordCover($record);
    $alt=$record['artist']." - ".$record['title'];
    return "<img onClick='changeBox({\"recordID\":".$record['recordID']."});' class='".$addClass."' alt='".$alt."' src='".$iconPath."'>";
}

function getArtistIcon($artist,$addClass="") {
    //$record=getRecord($recordID);
    $iconPath=getArtistCover($artist);
    $alt=$artist['title'];
    return "<img onClick='changeBox({\"artistID\":".$artist['artistID']."});' class='".$addClass."' alt='".$alt."' src='".$iconPath."'>";
}

function findRecordID($jpID) {
    global $DB;
    return $DB->getOne("SELECT recordID FROM records WHERE jpID='".$jpID."'");
}

function getLetterCover($letter) {
    $letter=strtoupper(substr($letter,0,1));
    if (is_numeric($letter)) $letter=1;
    return "/albums/img/boxes/".$letter.".jpg";
}

function getRecordCover($record,$number=1) {
    $letter=substr($record['jpID'],0,1);
    if ($record['type']==1) { // albums
        return "/albums/img/".$letter."/".$record['jpID']."-".str_pad($record['card'],5,'0', STR_PAD_LEFT)."-".$number.".jpg";
    } else {
        return "/singles/img/".$letter."/".$record['jpID']."-".$number.".jpg";
    }
    return false; // not sure when this will happen but yeah
}

function getRecordCoverPath($record) {
    $letter=substr($record['jpID'],0,1);
    if ($record['type']==1) { // albums
        return "/albums/img/".$letter."/";
    } else {
        return "/singles/img/".$letter."/";
    }
    return false; // not sure when this will happen but yeah
}

// use boxID to specify tracks from one particular box (not used on record pages but used by records that live in multiple boxes)
function getRecordTracks($recordID, $boxID=false) {
    global $DB;
    $boxWhere=(($boxID)?" AND boxTracks.boxID=".$boxID:"");
    $tracks=$DB->getAll("SELECT * FROM tracks, boxTracks WHERE (tracks.recordID=".$recordID.$boxWhere." AND tracks.trackID=boxTracks.trackID) ORDER BY tracks.recordID, boxTracks.ordering");
    if (!$tracks) return false;
    foreach ($tracks as $id=>$track) {
        $tracks[$id]['media']=$DB->GetAll("SELECT * FROM media WHERE trackID=".$track['trackID']." ORDER BY priority");
        foreach ($tracks as $otherID=>$otherTrack) {
            if ($track['trackID']==$otherTrack['trackID'] && $id!=$otherID) unset($tracks[$id]); // remove duplicates of same song where it is used in two boxes
        }
    }
    return $tracks;
}

// generate dynamic link to change to artist page
function getArtistLink($artistID) {
    return "<a onClick='changeBox(false,false,".$artistID.");'>";
}

// generate dynamic link to tag page (need to improve JS end for this to work)
function getTagLink($tagID) {
    
}

function buildRecordPage($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false;
    //var_dump($record);
    
    $images=array();
    if (!$record['numImages']) $record['numImages']=1;
    for ($i=1; $i<=$record['numImages']; $i++) {
        $sleeveUrl=getRecordCover($record,$i);
        array_push($images,$sleeveUrl);
    }
    $title=$record['artist']." - ".$record['title'];
    $yearTitle=(($year=findRecordTag($record['recordID'],'year'))?$year['value']:'');
    $labelTitle=(($label=findRecordTag($record['recordID'],'label'))?$label['value']:'');
    $html="";

    //if (count($images)>0) $html.=getRecordHeader($images);
    $html.=buildParallax($recordID);
    $html.="   
        <div class='wrapper recordTitle recordPage'>
            <h2>".$title." <span>".$labelTitle." ".$yearTitle."</span></h2>
        </div> <!-- .wrapper .recordTitle -->";
    
    // start second wrapper
    $html.="<div class='wrapper'>";

  // sleeve viewer
    $html.="<div class='singleRecord'>";
        $html.=getPictureViewer($images,$recordID);
    $html.="</div> <!-- .singleRecord -->";

    $content=getAttachedContent(['recordID'=>$recordID,'noBoxID'=>true]);
    if ($content) {
        $html.="<div class='singleRecord'>";
        foreach ($content as $thing) {
            $html.="<div class='attachedContent'>";
            $html.="<p>".$thing['blurb']."</p>";
            $html.="</div> <!-- .attachedContent -->";    
        }
        $html.="</div>";
    }

    // card
    $html.="<div class='singleRecord'>";
        $html.=getRecordCard($record);
    $html.="</div> <!-- .singleRecord -->";

    // media
    $links=getRecordLinks($recordID); // older style - attached to a record
    
    foreach ($links as $link) {
        $media='';
        if (!$link['title'] && !$link['track']) $link['title']=$title;
        switch (strtolower($link['service'])) {
            case 'youtube':
                $media.=getYouTubeVideo($link['title'],"","LinkID".$link['linkID']);
                break;
            case 'spotify':
                $media.="<img src='/img/boxes/spotify.png' class='icon'>";
                $media.=getLink($link);
                break;
            case 'itunes':
                $media.="<img src='/img/boxes/itunes.png' class='icon'>";
                $media.=getLink($link);
                break;
            default:
                // nothing
        }
        
    }
    $tracks=getRecordTracks($record['recordID']); // attached in the newer more CMS-y manner
    if ($tracks) {
        foreach ($tracks as $track) {
            $media='';
            if (array_key_exists('media',$track) && count($track['media'])>0) {
                switch (strtolower($track['media'][0]['type'])) {
                    case "youtube":
                        $media.=getYouTubeVideo($track['artist'], $track['title'],"trackID".$track['trackID']);
                        break;
                    case "soundmanager":
                        // create an audio player I guess
                        $media.=getAudioPlayer($track['artist'], $track['title'],"trackID".$track['trackID']);
                        break;
                    default:
                        // nothing
                }
            }
            // if it's a thing, wrap it up and pop it in...
            if (strlen($media)>0) {
                $html.="<div class='singleRecord'>".$media."</div> <!-- .singleRecord -->";
            }
        }
    }

    // links to other content
    $html.="<div class='singleRecord'>";
    $html.=getBoxIcons($recordID);
    $artist=getArtist($record['artistID']);
    $html.=getArtistIcons($artist);
    $html.=getRecordIcons($recordID);
    $tags=getRecordTags($recordID);
    if ($tags) {
        foreach ($tags as $tag) {
            $tagType=getTagType($tag['tagTypeID']);
            $html.=getTagIcons($recordID,strtolower($tagType['title']));
        }
    }
    $html.="</div> <!-- .singleRecord -->";
    $html.="</div> <!-- .wrapper -->";
    return $html;  
}

// defaults to 
function getArtistCover($artist) {
    $path=false;
    $records=searchRecords(array('artist'=>$artist['title']));
    foreach ($records as $record) {
        if ($record['numImages'] && $record['numImages']>0) {
            $path=getRecordCover($record,1);
        }
        break;
    }
    if ($path) return $path;
    return false;
}

function buildArtistPage($artistID) {
    $artist=getArtist($artistID);
    if (!$artist) return false;
    $html='';
    $html.=getRecordHeader(array(getArtistCover($artist)));
    $records=searchRecords(array('artist'=>$artist['title']));
    $title=$artist['title'];
    $html.="<div class='wrapper'>";
    $html.="<div class='artistPage'>";
    $html.="<h1>".$title."</h1>";
    $content=getAttachedContent(['artistID'=>$artistID]);
    if ($content) {
        foreach ($content as $thing) {
            $html.="<div class='attachedContent'>";
            $html.="<p>".$thing['blurb']."</p>";
            $html.="</div> <!-- .attachedContent -->";    
        }
        
    }
    $html.=displayReleases($records);
    $html.="</div>";
    $html.="</div>";
    return $html;
}

function buildLetterPage($letter) {
    $records=searchRecords(['letter'=>$letter]);
    if (!$records) return false;
    $html='';
    $html.=getRecordHeader(array(getLetterCover($letter)));
    if (is_numeric($letter)) {
        $letter='numbers';
    } else {
        $letter=strtoupper(substr($letter,0,1));
    }
    $title="Records beginning with ".$letter;
    $html.="<div class='wrapper'>";
    $html.="<div class='artistPage'>";
    $html.="<h1>".$title."</h1>";
    $html.=displayReleases($records,true);
    $html.="</div>";
    $html.="</div> <!-- .wrapper -->";
    return $html;
}

function buildTagPage($tagID) {
    $tag=getTag($tagID);
    if (!$tag) return false;
    $records=searchRecords(['tagID'=>$tagID]);
    //if (!$records) return false; // not sure if we can still display page
    $html='';
    $html.=getRecordHeader(array(getRecordCover(getFirstRecord($records))));
    $title="John Peel Archive: ".$tag['value'];
    $html.="<div class='wrapper'>";
    $html.="<div class='artistPage'>";
    $html.="<h1>".$title."</h1>";
    $content=getAttachedContent(['tagID'=>$tagID]);
    if ($content) {
        foreach ($content as $thing) {
            $html.="<div class='attachedContent'>";
            $html.="<p>".$thing['blurb']."</p>";
            $html.="</div> <!-- .attachedContent -->";    
        }
    }
    $html.=displayReleases($records,true);
    $html.="</div>";
    $html.="</div> <!-- .wrapper -->";
    return $html;
}

function displayReleases($records,$includeArtist=false) {
    if (!$records) return false;
    $html='';
    foreach ($records as $record) {
        $html.="<div class='releaseLink'>";
        $iconPath=getRecordCover($record);
        $alt=$record['artist']." - ".$record['title'];
        $html.="<img onClick='changeBox(false,\"".$record['jpID']."\");' class='recordBoxIcon' alt='".$alt."' src='".$iconPath."'>";
        $yearTitle=(($year=findRecordTag($record['recordID'],'year'))?" - ".$year['value']:'');
        $labelTitle=(($label=findRecordTag($record['recordID'],'label'))?" <i>(".$label['value'].")</i>":'');
        $html.="<p><a onClick='changeBox(false,\"".$record['jpID']."\");'>".(($includeArtist)?$record['artist'].' - ':'').$record['title'].$yearTitle.$labelTitle."</a></p>";
        $html.="</div>";
    }
    return $html;
}

function buildRecordPlaylist($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false;

    $playlist=array();
    $playlist['name']=$record['artist']." - ".$record['title'];
    $playlist['playlistID']='recordID'.$record['recordID'];
    $letter=substr($record['jpID'],0,1);
    $playlist['folder']=getRecordCoverPath($record);
    $playlist['author']='Bruce Grove'; // I guess we'll allow author naming later
    $playlist['hasVideo']=false; // not a top of the page video anyway, for now
    $playlist['dynamic']=true; // because, because, because

    $links=getRecordLinks($recordID);

    $recordTracks=array();

    foreach ($links as $link) {
        if (strtolower($link['service'])=='youtube') {
            $track=array();
            $track['artist']=$record['artist'];
            $track['title']=$record['title'];
            $track['type']="YouTube";
            $track['icon']=basename(getRecordCover($record));
            $track['outputTo']="videoLinkID".$link['linkID']; // auto generated player, auto generated name, see...
            // TO DO - if faced with a full link, parse out v=...... to get videoID for API
            parse_str($link['url'],$urlParts);
            foreach ($urlParts as $id=>$urlPart) {
                if (strtolower(substr($id,-1))=='v') {
                    $track['source']=$urlParts[$id];
                    array_push($recordTracks,$track);
                    break;
                }
            }
        }
    }
    
    $tracks=getRecordTracks($record['recordID']);
    //var_dump($tracks);
    if ($tracks) {
        foreach ($tracks as $track) {
            if (array_key_exists('media',$track) && count($track['media'])>0) {
                switch (strtolower($track['media'][0]['type'])) {
                    case "youtube":
                        //$html.=getYouTubeVideo($track['artist']." - ".$track['title'],"trackID".$track['trackID']);
                        $track['artist']=$record['artist'];
                        $track['title']=$record['title'];
                        $track['type']="YouTube";
                        $track['folder']=false;
                        $track['icon']=basename(getRecordCover($record));
                        $track['outputTo']="trackID".$track['trackID']; // auto generated player, auto generated name, see...
                        $track['source']=$track['media'][0]['source'];
                        if (count($track['media'])>1) {
                            $fallback=$track;
                            $fallback['type']=$track['media'][1]['type'];
                            $fallback['source']=$track['media'][1]['source'];
                            $track['fallback']=$fallback;
                        }
                        array_push($recordTracks,$track);
                        break;
                    case "soundmanager":
                        // create an audio player I guess
                        $track['artist']=$record['artist'];
                        $track['title']=$record['title'];
                        $track['type']="SoundManager";
                        $track['folder']=false;
                        $track['icon']=basename(getRecordCover($record));
                        $track['outputTo']="trackID".$track['trackID']; // auto generated player, auto generated name, see...
                        $track['source']=$track['media'][0]['source'];
                        if (count($track['media'])>1) {
                            $fallback=$track;
                            $fallback['type']=$track['media'][1]['type'];
                            $fallback['source']=$track['media'][1]['source'];
                            $track['fallback']=$fallback;
                        }
                        array_push($recordTracks,$track);
                        break;
                    default:
                        // nothing
                }
            }
        }
    }
    // TODO - add any tracks or relevant videos that we have on this album...
    // make it easier for ourselves by ignoring any video overlays and replacing icons with standard record sleeve for now

    $playlist['tracks']=$recordTracks;
    
    return $playlist;
}

function buildBoxPlaylist($boxID) {
    $box=getRecordBoxData($boxID);
    if (!$box) return false;

    $playlist=array();
    $playlist['name']=$box['title'];
    $playlist['playlistID']='boxID'.$box['boxID'];
    $playlist['folder']=$box['folder'];
    $playlist['author']='Bruce Grove'; // I guess we'll allow author naming later
    $playlist['hasVideo']=!is_null($box['mainVideo']);
    $playlist['dynamic']=true; // because, because, because

    $tracks=getBoxTracks($boxID);

    $boxTracks=array();
    $recordBits=getRecordBits($boxID);
    if ($recordBits) {
        foreach($recordBits as $recordBit) {
            if (!$tracks) break;
            foreach($tracks as $track) {
                if ($track['recordID']==$recordBit['recordID']) {
                    if (count($track['media'])==1) {
                        // if just one, include media in track
                        $track=array_merge($track,$track['media'][0]);
                    } else if (count($track['media'])>1) {
                        // if two, include media and copy everything (+2nd to fallback media)
                        // this fallback mechanism could be nicer tbf
                        $fallback=array_merge($track,$track['media'][1]);
                        $track=array_merge($track,$track['media'][0]);
                        $track['fallback']=$fallback;
                    }
                    $track['recordBitID']=$recordBit['recordBitID'];

                    array_push($boxTracks,$track);
                }
            }
        }
    }
    $playlist['tracks']=$boxTracks;
    
    return $playlist;
}

// generic command that receives a bunch of record objects and puts them into some sort of page thing
function buildRecordsList($records) {
    if (!$records) return false;
    $html='';
    $html.="<div class='searchResults'>";
    //$html.="<h2>".$name."</h2>";
    foreach ($records as $record) {
        $html.=$record['icon'];
    }    
    $html.="</div>";
    return $html;
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

function getRecordTags($recordID,$tagTypeID=false) {
    global $DB;
    if ($tagTypeID) {
        return $recordTags=$DB->GetAll("SELECT * FROM tags WHERE tagID IN (SELECT tagID FROM recordTags WHERE recordID=".$recordID.") AND tagTypeID=".$tagTypeID);
    } else {
        return $recordTags=$DB->GetAll("SELECT * FROM tags WHERE tagID IN (SELECT tagID FROM recordTags WHERE recordID=".$recordID.")");
    }
}

function getSleeve($recordID) {
    global $DB;
    return $DB->GetRow("SELECT * FROM sleeves WHERE recordID=".$recordID);
}

function findTagType($string) {
    global $DB;
    return $DB->GetRow("SELECT * FROM tagTypes WHERE title LIKE '".$string."'");
}

function findRecordTag($recordID, $string) {
    global $DB;
    $tagType=findTagType($string);
    if (!$tagType) return false;
    $tag=$DB->GetRow("SELECT * FROM tags, tagTypes WHERE tagID IN (SELECT tagID FROM recordTags WHERE recordID=".$recordID.") AND tags.tagTypeID=".$tagType['tagTypeID']." AND tagTypes.tagTypeID=".$tagType['tagTypeID']);
    return $tag;
}

// because images are separated by box, this finds correct folder
function getBoxContentPath($boxID=false) {
    global $DB;
    if ($boxID) $box=getRecordBoxData($boxID);
    // record boxes have their sleeves in /content/ folder
    if ($box) return "/content/".$box['folder']."/";
    // all other sleeves
    return false;
}

function buildSleeve($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false; // can't do shit here
    $sleeve=getSleeve($recordID); // check for custom sleeve
    if ($sleeve) $path=getBoxContentPath($sleeve['boxID']); // check for content path
    $html="";
    //$html="<div class='sleeve'>";
    if (!$sleeve || !$path) { // in which case default to standard sleeve
        $url=getRecordCover($record,1);
        $html.="<img class='front' src='".$url."' />";
    } else {
        $class='sleeve3d';
        if ($sleeve['sleeveType']=='single') {
            $class.=" single";
        } else if ($sleeve['sleeveType']=='jukebox') {
            $class.=" jukebox single";
        }
        $html.="<div class='".$class."' id='".$sleeve['imagePrefix']."'>";
        $frontUrl=$path.$sleeve['imagePrefix']."-sleeve.jpg";
        $html.="<img class='front' src='".$frontUrl."'>";
        $backUrl=$path.$sleeve['imagePrefix']."-back.jpg";
        $html.="<img class='back' src='".$backUrl."'>";
        if ($sleeve['sleeveType']=='album') {
            $spineUrl=$path.$sleeve['imagePrefix']."-spine.jpg";
            $html.="<img class='spine' src='".$spineUrl."'>";
        }
        $html.="</div>";

    }
    //$html.="</div>";
    return $html;
}

function buildParallax($recordID) {
    $record=getRecord($recordID);
    if (!$record) return false; // can't do shit here
    $sleeve=getSleeve($recordID); // check for custom sleeve
    if ($sleeve) $path=getBoxContentPath($sleeve['boxID']); // check for content path
    $html="";
    if (!$sleeve || is_null($sleeve['parallaxHeader']) || !$path) { // in which case default to standard sleeve
        $url=getRecordCover($record,1);
        $html.=getRecordHeader([$url]);
    } else {
        $parallaxUrl=$path.$sleeve['parallaxHeader'];
        $html="
            <div class='headimage parallax'>
                <img src='".$parallaxUrl."'>
            </div>";
    }
    return $html;
}

// trial CMS record making thing
function buildRecord($recordID,$boxID=false) {
    //$boxID=1;
    $record=getRecord($recordID);
    if (!$record) return false;
    $labelTag=findRecordTag($recordID,'label');
    $label=(($labelTag)?$labelTag['value']:'');
    
    $yearTag=findRecordTag($recordID,'year');
    $year=(($yearTag)?$yearTag['value']:'');
    
    $getArray=['recordID'=>$recordID];
    if ($boxID) $getArray['boxID']=$boxID;
    $content=getAttachedContent($getArray);

    $pullQuoteID=findContentType($content,'pullquote');
    if ($pullQuoteID!==false) {
        $pullQuote=$content[$pullQuoteID]['blurb'];
        unset($content[$pullQuoteID]);
    } 

    $sideQuoteID=findContentType($content,'sidequote');
    if ($sideQuoteID!==false) {
        $sideQuote=$content[$sideQuoteID]['blurb'];
        unset($content[$sideQuoteID]);
    }
    $tracks=getRecordTracks($recordID,$boxID);

    // trackTypes = 1 = music, 2 = video, 3= speech
    $musicTrackID=findTrackType($tracks,1);
    if ($musicTrackID!==false) {
        $musicTrack=$tracks[$musicTrackID];
        unset($tracks[$musicTrackID]);
    }

    $videoTrackID=findTrackType($tracks,2);
    if ($videoTrackID!==false) {
        $videoTrack=$tracks[$videoTrackID];
        unset($tracks[$videoTrackID]);
    }

    $chatTrackID=findTrackType($tracks,3);
    if ($chatTrackID!==false) {
        $chatTrack=$tracks[$chatTrackID];
        unset($tracks[$chatTrackID]);
    }
    
    // put sleeve on right (we'll assume we won't have an audio chat track if we have video instead)
    if ($musicTrackID!==false && $chatTrackID!==false) {
        $sleeveRight=true;
    } else {
        $sleeveRight=false;
    }

    $html='';
    $html.="
        <div class='record' id='".$record['jpID']."'>";
    $html.=buildParallax($recordID);
    $html.="
            <div class='wrapper recordTitle'>
                <h2>".$record['artist']." - ".$record['title']." <span>".$label." ".$year."</span></h2>
            </div>
            <div class='main wrapper clearfix'>";
    $sleeve="
                <div class='sleeve'>";
    $sleeve.=buildSleeve($recordID);

    $sleeve.=     "<a class='albumLink' onClick='popupAlbumInfo(\"".$record['jpID']."\");'></a>";
    // IF AUDIO OF TYPE SONG.....
    if ($musicTrackID!==false) {
        $sleeve.=getAudioPlayer($musicTrack['artist'], $musicTrack['title'], 'trackID'.$musicTrack['trackID']);
    }
    
    if ($sideQuoteID!==false) {
        $sleeve.="
                    <div class='pullQuote2'>
                        <p><span class='nickdrake'>".$sideQuote."</span></p>
                    </div>";
    }
    $sleeve.="
                </div>";
    if (!$sleeveRight) $html.=$sleeve; // we'll save sleeve for in a minute if it's on the right.
    if (!$sleeveRight) {
        $html.="
                <div class='text-1'>";    
    } else {
        $html.="
                <div class='text-3'>"; // right aligned 
    }
    if ($videoTrackID!==false) {
        $html.=getYouTubeVideo($videoTrack['artist'], $videoTrack['title'], 'trackID'.$videoTrack['trackID']);
    }
    if ($chatTrackID!==false) {
        $addClass=(($sleeveRight)?'second':'');
        $html.=getAudioPlayer($chatTrack['artist'], $chatTrack['title'], 'trackID'.$chatTrack['trackID'],$addClass);
    }
    
    if (findContentType($content)!==false) {
        $id=findContentType($content);
        $html.="<p>".$content[$id]['blurb']."</p>";
        unset($content[$id]);
    }
    if ($pullQuoteID!==false) {
        $html.="<p class='pullQuote'><span class='ammmusic'>".$pullQuote."</span></p>";
    }
    // 
    if (findContentType($content)!==false) {
        $id=findContentType($content);
        $html.="<p>".$content[$id]['blurb']."</p>";
        unset($content[$id]);    
    }
    $html.="
                </div> <!-- .text-x -->";
    if ($sleeveRight) $html.=$sleeve;
    $html.="
            </div> <!-- .wrapper -->
        </div> <!-- .record -->";
    
    return $html;
}

// assembles html playlist and meta data for a record box created in the CMS style
function getCMSRecordBox($boxID) {
    global $cacheFace;

    $box=$cacheFace->get('jpaBoxID'.$boxID);
    if ($box) return $box;

    $box=getRecordBoxData($boxID);
    if (!$box) {
        boxFail();
        return false;
    } else {
        $box['playlist']=buildCMSBoxPlaylist($box);
        $box['title']=(($box['pageTitle'])?$box['pageTitle']:"John Peel Archive: ".$box['title']); // pageTitle is format of 'Joe Boyd's Record Box', and is preferable
        $box['description']=$box['introText'];
        $box['slug']=$box['folder'];
        $box['header']=getBoxHeader($box);
        $box['image']=(($box['shareImage'])?'/content/'.$box['folder'].'/'.$box['shareImage']:false); // will be false if no image - implement default at receiving end
        $box['tracks']=getBoxTracks($box['boxID']);
        $box['recordBits']=getCMSRecordBits($box['boxID']);
        $box['socialButtons']=getSocialButtons($box);
        $box['video']=checkMainVideo($box);
        $cacheFace->set('jpaBoxID'.$boxID,$box,60*60); // save for an hour
        return $box;
    }
}

function getBoxRecords($boxID) {
    global $DB;
    return $DB->GetArr("SELECT recordID FROM boxRecords WHERE boxID=".$boxID);
}

function getCMSRecordBits($boxID) {
    $intro=buildCMSIntro($boxID);
    $boxRecords=getBoxRecords($boxID);
    $records=[];
    if ($intro) array_push($records,$intro);
    if ($boxRecords) {
        foreach ($boxRecords as $boxRecord) {
            $output=['content'=>buildRecord($boxRecord,$boxID)];
            array_push($records,$output);
        }    
    }
    return $records;
}

function buildCMSIntro($boxID) {
    $content=getAttachedContent(['boxID'=>$boxID,'linkType'=>'intro']);
    if (!$content) return false;
    $html="<div class='introPara'>";
    foreach ($content as $piece) {
        $html.="<p>".$piece['blurb']."</p>";
    }
    $html.="</div>";
    return ['content'=>$html];
}

// CMS record maker helper function 
function findContentType($content,$type=false) {
    if (!$content || !is_array($content) || count($content)==0) return false;
    foreach ($content as $id=>$bit) {
        if (!$type) {
            if (is_null($bit['linkType']) || strlen($bit['linkType']==0)) {
                return $id;
            }
        } else {
            if ($bit['linkType']==$type) {
                return $id;
            }
        }
    }
    return false;
}

// CMS record maker helper function 
function findTrackType($tracks,$type=1) {
    if (!$tracks || !is_array($tracks) || count($tracks)==0) return false;
    foreach ($tracks as $id=>$track) {
        if ($track['trackType']==$type) {
            return $id;
        }
    }
    return false;
}

// CMS version of this mind meltingly irritating bunch of lads
function buildCMSBoxPlaylist($box) {
    $playlist=array();
    $playlist['name']=$box['title'];
    $playlist['playlistID']='boxID'.$box['boxID'];
    $playlist['folder']=$box['folder'];
    $playlist['author']='Bruce Grove'; // I guess we'll allow author naming later
    $playlist['hasVideo']=!is_null($box['mainVideo']);
    $playlist['dynamic']=true; // because, because, because

    $recordIDs=getBoxRecords($box['boxID']);
    if (!$recordIDs) return false; // no playlist really
    $tracks=[];
    foreach ($recordIDs as $recordID)  {
        $recordTracks=getRecordTracks($recordID,$box['boxID']);
        if (is_array($recordTracks)) $tracks=array_merge($tracks,$recordTracks);
    }
    //$tracks=getBoxTracks($boxID);

    $boxTracks=array();
    foreach($tracks as $track) {
        $track['outputTo']='trackID'.$track['trackID'];
        if (count($track['media'])==1) {
            // if just one, include media in track
            $track=array_merge($track,$track['media'][0]);
        } else if (count($track['media'])>1) {
            // if two, include media and copy everything (+2nd to fallback media)
            // this fallback mechanism could be nicer tbf
            $fallback=array_merge($track,$track['media'][1]);
            $track=array_merge($track,$track['media'][0]);
            $track['fallback']=$fallback;
        }
        array_push($boxTracks,$track);
    }
    $playlist['tracks']=$boxTracks;
    //var_dump($playlist);
    return $playlist;
}

// code is a version of that used in routing.php, used to check what page we are requesting from URL
function findBoxFromUrl($url) {
    $url=parse_url($url, PHP_URL_PATH);
    $path = strtolower(ltrim($url, '/'));
    $elements = explode('/', $path);
    
    if (count($elements)==0) {
        return false;
    }
    if (!array_key_exists(1,$elements)) $elements[1]=false; // add blank second param if needed to avoid errors
    
    $boxID=getRecordBoxID($elements[0]);
    if ($boxID) return ['boxID'=>$boxID];

    $record=findRecord($elements[0],$elements[1]);
    if ($record) return ['recordID'=>$record['recordID']];

    $artist=findArtist($elements[0]);
    if ($artist) return ['artistID'=>$artist['artistID']];

    if ($elements[0]=='letter') return ['letterID'=>$elements[1]];

    $tag=findTag($elements[0]);
    if ($tag) return ['tagID'=>$tag['tagID']];
    
    return false;
}

?>
