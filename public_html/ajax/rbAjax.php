<?php

// JPA Player record box fetcher.
// functions called dynamically by record boxes to build pages

include_once('../../include/recordbox.php');

$action=getIfSet($_GET,'action',false);

switch ($action) {
    
    case "test":
        echo json_encode(array("rc"=>2,"msg"=>"Test is fine"));
        break;

    case "getRecordBox":
        $boxID=p('boxID',false);
        if (!$boxID) failure();
        recordPageCount($boxID,false,false);
        echo json_encode(array("rc"=>2,"msg"=>"Record bits retrieved","recordBox"=>getRecordBox($boxID)));
        break;

    case "getRecordPage":
        $recordID=p('recordID',false);
        if (!$recordID) {
            $jpID=p('jpID',false);
            if ($jpID) $recordID=findRecordID($jpID);
            if (!$recordID) {
                failure('record not found');
            }
        }
        recordPageCount(false,$recordID,false);
        $recordPage=getRecordPage($recordID);
        echo json_encode(array("rc"=>2,"msg"=>"Record found","page"=>$recordPage));
        break;

    case "getArtistPage":
        $artistID=p('artistID',false);
        if (!$artistID) failure('artist not found');
        $artistPage=getArtistPage($artistID);
        recordPageCount(false,false,$artistID);
        echo json_encode(array("rc"=>2,"msg"=>"Artist found","page"=>$artistPage));
        break;

    case "getLetterPage":
        $letterID=p('letterID',false);
        if (!$letterID) failure('letter not found');
        $letterPage=getLetterPage($letterID);
        //recordPageCount(false,false,$artistID);
        echo json_encode(array("rc"=>2,"msg"=>"Letter found","page"=>$letterPage));
        break;

    case "getTagPage":
        $tagID=p('tagID',false);
        if (!$tagID) failure('tag not found');
        $tagPage=getTagPage($tagID);
        //recordPageCount(false,false,$artistID);
        echo json_encode(array("rc"=>2,"msg"=>"Tag found","page"=>$tagPage));
        break;

    // pass this a URL, this will tell you what content it refers to (if any)
    case "getJPABoxID":
        $url=p('url',false);
        if (!$url) failure('no URL to check');
        $pageDetails=findBoxFromUrl($url);
        $jpaBoxID='';
        if ($pageDetails) {
            foreach($pageDetails as $id=>$pageDetail) {
                $jpaBoxID.=$id.$pageDetail;
            }
        }
        echo json_encode(["rc"=>2,"jpaBoxID"=>$jpaBoxID,"pageDetails"=>$pageDetails]);
        break;

  default:
    echo failure();
}

function failure($msg="Call failed") {
	echo json_encode(array("rc"=>0,"msg"=>$msg));
    die;
}
?>