<html>
	<head>
		<title>
			Boxes
		</title>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
	</head>
	<body>
		<div id='website'>

<?php 
	include_once('sidebar.html');
	include_once('../../include/johnpeel.php');
?>
			<div id='content'>
	
<?php 
	$kf=new Form('boxes');
	//$kf->readOnly("boxID");
	//$kf->redefine("accessLevel","Access Level","SELECT","ANY,USER,ADMIN");
	$kf->redefine("headerImage","Header image", "TEXT");
	$kf->redefine("dynamic","Is page dynamic?","CHECKBOX");
	$kf->handleSubmission();
	echo $kf->getMultiForm();

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	