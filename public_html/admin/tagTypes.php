<html>
	<head>
		<title>
			Tag Types
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
	$kf=new Form('tagTypes');
	//$kf->redefine("tagTypeID","Tag Type","select","SELECT tagTypeID,title FROM tagTypes");
	//$kf->redefine('artistID','Artist','select','select artistID, title FROM artists');
	$kf->handleSubmission();
	echo $kf->getMultiForm();

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	