<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);

if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('boxTracks');
		$kf->handleSave();
		$boxID=p('boxID');
		redir('tracks.php?boxID='.$boxID);	
	} else {
		$kf=new Form('boxTracks');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Box / track settings
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
	$kf=new Form('boxTracks');
	$kf->redefine('trackID','Track','select','select trackID, CONCAT(artist," - ",title) FROM tracks');
	$kf->redefine('boxID','Box','select','SELECT boxID, title FROM boxes');
	$kf->redef("splashImage",array("title"=>"Splash image", "coltype"=>"TEXT"));
	$kf->redef("ordering",array("title"=>"Track play order", "coltype"=>"NUMBER"));
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	