<?php
include_once('../../include/johnpeel.php');

?>
<html>
	<head>
		<title>
			Page Loads
		</title>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
	</head>
	<style>
		.col {
			height:100%;
			width: 30%;
			float:left;
		}
		.thumb { width:90%;}
		h2 { margin:0px 0px 20px 0px;}
		p { margin: 10px 0px 10px 0px;}
	</style>
	<body>
		<div id='website'>

<?php 
	include_once('sidebar.html');
	
?>
			<div id='content'>
	
<?php 

	$data=getPageLoads(50);
	
	if (count($data['boxIDs'])>0) {
		echo "<div class='col'>";
		echo '<h2>Boxes</h2>';
		foreach ($data['boxIDs'] as $boxID=>$views) {
			$box=getRecordBox($boxID);
			echo '<p>'.$box['title']." - ".$views." views</p>";
			echo "<img class='thumb' src='".getBoxCover($box)."'>";
			echo "<br>";
		}
		echo "</div>";
	}

	if (count($data['artistIDs'])>0) {
		echo "<div class='col'>";
		echo '<h2>Artists</h2>';
		foreach ($data['artistIDs'] as $artistID=>$views) {
			$artist=getArtist($artistID);
			echo '<p>'.$artist['title']." - ".$views." views</p>";
			echo "<img class='thumb' src='".getArtistCover($artist)."'>";
			echo "<br>";
		}
		echo "</div>";
	}

	if (count($data['recordIDs'])>0) {
		echo "<div class='col'>";
		echo '<h2>Records</h2>';
		foreach ($data['recordIDs'] as $recordID=>$views) {
			$record=getRecord($recordID);
			$artist=getArtist($record['artistID']);
			echo '<p>'.$artist['title']." - ".$record['title']." - ".$views." views</p>";
			echo "<img class='thumb' src='".getRecordCover($record)."'>";
			echo "<br>";
		}
		echo "</div>";
	}

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	