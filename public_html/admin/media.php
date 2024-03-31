<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);

if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('media');
		$kf->handleSave();
		$trackID=p('trackID',false);
		redir('tracks.php');	
	} else {
		$kf=new Form('media');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Media
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
	
	$kf=new Form('media');
	$kf->redefine('trackID','Track','select','select trackID, CONCAT(artist," - ",title) FROM tracks');
	//$kf->def(array('filename'=>array('coltype'=>'text')));
	$kf->redefine('type','Type','select',array('SoundManager'=>"SoundManager",'YouTube'=>"YouTube"));
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	