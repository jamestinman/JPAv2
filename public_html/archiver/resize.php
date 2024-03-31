<?php
// Process images e.g.:
// php resize.php w=1080 h=1080 c=80
include_once('../../include/society/DB.php');
$DB=new DB('johnpeelarchive','thelookoflove','archiver');
startSession("johnpeel");

$width=p("w",1080);
$height=p("h",1080);
$compression=p("c",80);
$limit=p("limit",0); // default to no limit

$meDir=dirname(__FILE__);
$codeDir=substr($meDir,0,strrpos($meDir,'/'));
$rootImgDir=$meDir."/i";

$countResized=0;
$countAll=0;

// Loop over all files from the database and resize
e("Resizing all images to ".$width."x".$height." with compression ".$compression."%");

foreach (scandir($rootImgDir) as $file) {
  $realFilename=$rootImgDir."/".$file;
  if (!is_dir($realFilename) && @getimagesize($realFilename)) {
    $countAll++;
    // We should have a valid image file now
    list($origWidth, $origHeight) = getimagesize($realFilename);
    if ($origWidth>$width || $origHeight>$height) {
      // Re-save
      resizeImage($realFilename,$width,$height,$realFilename,false,$compression,"FIT");
      list($newWidth, $newHeight) = getimagesize($realFilename);
      e($countAll.". Resized ".$realFilename." from ".$origWidth."x".$origHeight." -> ".$newWidth."x".$newHeight);
      $countResized++;
    } else {
      echo $countAll.". ";
      e("Leaving ".$realFilename." at ".$origWidth."x".$origHeight);
    }
    if ($limit && $countResized>$limit) die('Limit reached at file #'.$count);
  }
}

e($countResized."/".$countAll." images resized");
?>
