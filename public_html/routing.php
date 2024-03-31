<?php
// routing.php
// JPA routing isn't it
// should try for record box first, then record, then artist page

include_once('../include/recordbox.php');

$path = strtolower(ltrim($_SERVER['REQUEST_URI'], '/'));    // Trim leading slash(es)
$elements = explode('/', $path);                // Split path on slashes
if (count($elements)==0) {
	header('Location: /');
	die();
}
if ($elements[0]=='admin') { // admin pages stuff
	header('Location: /admin/index.php');
	die;
}
$params=array();
if (!array_key_exists(1,$elements)) $elements[1]=false;
// this is for finding a record box
$boxID=getRecordBoxID();
//var_dump($boxID);
if ($boxID) {
	$params=['boxID'=>$boxID];
	redirect('records/index.html',$params);
}

// this is for picking up urls in format /artist/album
$record=findRecord($elements[0],$elements[1]);
if ($record) {
	$params=['recordID'=>$record['recordID']];
	redirect('records/index.html',$params);
}

// failing that, see if there's a relevant artist page
$artist=findArtist($elements[0]);
if ($artist) {
	$params=['artistID'=>$artist['artistID']];
	redirect('records/index.html',$params);
}

// next, we look for stuff like /letter/ for those pages
if ($elements[0]=='letter') {
	$params=['letter'=>$elements[1]];
	redirect('records/index.html',$params);
}

// look for johnpeelarchive.com/1967 for instance
$tag=findTag($elements[0]);
if ($tag) {
	$params=['tagID'=>$tag['tagID']];
	redirect('records/index.html',$params);
}

header('Location: /');
die;

function redirect($url,$params=false) {
    if ($params) $_POST=array_merge($_POST,$params);
	include(dirname(__FILE__).'/'.$url);
	die;
}
?>