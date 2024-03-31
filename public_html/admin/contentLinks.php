<?php
include_once('../../include/johnpeel.php');
$tagTypeID=p('tagTypeID',false);

if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('contentLinks');
		$kf->handleSave();
		$contentID=p('contentID');
		redir('content.php?contentID='.$contentID);	
	} else {
		$kf=new Form('contentLinks');
		$kf->handleSubmission();
	}
}

?>
<html>
	<head>
		<title>
			Content Linking
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
	$kf=new Form('contentLinks');
	$kf->redefine('contentID','Content','select',addNullOption(getContentList()));
	$kf->redefine('tagID','Tag','select',addNullOption(getTagList()));
	$kf->redefine('artistID','Artist','select',addNullOption(getArtistList()));
	$kf->redefine('recordID','Record','select',addNullOption(getRecordList()));
	$kf->redefine('boxID','Box-specific content','select',addNullOption(getBoxList()));
	echo "<div class='editTable'>";
	echo $kf->getForm();
	echo "</div> <!--.editTable -->";
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	