<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);

if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('artists');
		$kf->handleSave();
		$artistID=p('artistID',false);
		//redir('tracks.php');	
	} else {
		$kf=new Form('artists');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Artists
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

	$kf=new Form('artists');
	$kf->readOnly("artistID");
	//$kf->redefine("accessLevel","Access Level","SELECT","ANY,USER,ADMIN");
	//$kf->redefine("headerImage","Header image", "TEXT");
	//$kf->redefine("dynamic","Is page dynamic?","CHECKBOX");
	$kf->handleSubmission();
	echo $kf->getMultiForm();

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	