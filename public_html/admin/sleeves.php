<?php
include_once('../../include/johnpeel.php');

// for sorting out sleeve specifics per record
// pass sleeveID to edit, recordID for new

//var_dump($_GET);
//var_dump($_POST);
if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('sleeves');
		$kf->def(['imagePrefix'=>array('coltype'=>'TEXT')]); // remind Form to process this as text
		$kf->handleSave();
		$recordID=p('recordID');
		redir('records.php?recordID='.$recordID);	
	} else {
		$kf=new Form('sleeves');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Sorting sleeves and graphics
		</title>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
	</head>
	<body>
		<div id='website'>

<?php 
	include_once('sidebar.html');
	
?>
			<div id='content'>
	
<?php 
	$kf=new Form('sleeves');
	$kf->redefine('recordID','Record','select','SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) FROM records ORDER BY artistID');
	$kf->redefine('boxID','Box where content images live','select',getBoxList());
	$kf->redefine('sleeveType','Sleeve type','select',['album'=>'album','single'=>'single','jukebox'=>'jukebox','other'=>'other']); // can't remember them all right now
	$kf->def(
		['imagePrefix'=>array(
			'title'=>'prefix of filename in content folder',
			'coltype'=>'TEXT'
		)]
	);
	$kf->redefine('parallaxHeader','filename of parallax header image (in content folder)','text');
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	