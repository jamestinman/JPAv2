<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);
$tagTypeID=p('tagTypeID',false);
if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('recordTags');
		$kf->handleSave();
		$recordID=p('recordID');
		$cacheFace->delCached("jpaAdminRecordRow".$recordID); // force admin record row to update
		redir('records.php?recordID='.$recordID);	
	} else {
		$kf=new Form('recordTags');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Record tagging
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
	$kf=new Form('recordTags');
	$kf->redefine('recordID','Record','select','SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) FROM records ORDER BY artistID');
	$kf->redefine('tagID','Tag','select',getTagList($tagTypeID));
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	