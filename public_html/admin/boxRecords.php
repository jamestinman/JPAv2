<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);
if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('boxRecords');
		$kf->handleSave();
		$recordID=p('recordID');
		redir('records.php?recordID='.$recordID);	
	} else {
		$kf=new Form('boxRecords');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Putting records in boxes
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
	$kf=new Form('boxRecords');
	$kf->redefine('recordID','Record','select','SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) FROM records ORDER BY artistID');
	$kf->redefine('boxID','Box','select',getBoxList());
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	