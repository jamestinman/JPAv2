<?php
include_once('../../include/johnpeel.php');
//var_dump($_GET);
//var_dump($_POST);

$boxID=p('boxID',false);
if (!$boxID) {
	$boxID=getIfSet($_SESSION,'boxID',false);
} else {
	$_SESSION['boxID']=$boxID;	
}
// ensure setting dropper to all actually resets and shows all
if ($boxID=='false') {
	$boxID=false;
	$_SESSION['boxID']=false;
}

$trackID=p('trackID',false);

if (p('submitBtn',false)) {
	if (p('submitBtn')=='Save') {
		$kf=new Form('tracks');
		$trackID=$kf->handleSave();
		/*
		if ($trackID && $boxID) {
			global $DB;
			$boxTrackID=$DB->getRow("SELECT * FROM boxTracks WHERE boxID=".$boxID." AND trackID=".$trackID);
			if (!$boxTrackID) {
				$data=array('boxID'=>$boxID,'trackID'=>$trackID);
				$DB->writeArray('boxTracks',$data);
			}
		}*/
		$trackID=false; // and done now...	
	} else {
		$kf=new Form('tracks');
		$kf->handleSubmission();
	}
	
}

if (p('submitBtn')=='Cancel') $trackID=false; // cancel should take you back to main screen
if (p('submitBtn')=='Delete') $trackID=false; // so should delete

?>
<html>
	<head>
		<title>
			Tracks
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
	if (!$trackID) {
		echo recordBoxDropper();
		$tracks=getTracks($boxID);
		if (!$tracks) $tracks=array();
		$html="<table>";
		
		$html.="<tr class='row' style='background-color: #cceedd; text-align:left;'>";
		$html.="<td colspan=3><a href='tracks.php?trackID=new&boxID=".$boxID."'>Add new track...</a></td>";
		$html.="</tr>";

		foreach ($tracks as $track) {
			$html.="<tr style='height:30px; background-color: #333333;'> </tr>";

			$html.="<tr class='row'>";
			$html.="<td><h3>".$track['artist']." - ".$track['title']." - <a href='tracks.php?trackID=".$track['trackID']."'>Edit</a></h3></td>";
			$record=getRecord($track['recordID']);
			$html.="<td colspan=2>".$record['artist']." - ".$record['title']."</td>";
			$html.="</tr>";
			$html.="<tr class='row'>";
			$html.="<td colspan=3>Track media...</td>";
			$html.="</tr>";
			if (array_key_exists('media',$track)) {
				foreach ($track['media'] as $media) {
					$html.="<tr class='row' style='background-color: #ccddee;'>";
					$html.="<td><i>priority</i><br>".$media['priority']."</td>";
					$html.="<td><i>type</i><br>".$media['type']."</td>";
					$html.="<td><i>source</i><br>".$media['source']." - <a href='media.php?mediaID=".$media['mediaID']."'>Edit</a></td>";
					$html.="</tr>";
				}
			}
			$html.="<tr class='row' style='background-color: #cceedd; text-align:right;'>";
			$html.="<td colspan=3><a href='media.php?trackID=".$track['trackID']."'>Add new media....</a></td>";
			$html.="</tr>";
			$boxTracks=getTrackBoxes($track['trackID']);
			$html.="<tr class='row'>";
			$html.="<td colspan=3>Appears in...</td>";
			$html.="</tr>";
			if ($boxTracks) {
				foreach($boxTracks as $boxTrack) {
					$html.="<tr style='background-color: #eeccdd;'>";
					$box=getRecordBox($boxTrack['boxID']);
					$html.="<td><i>title</i><br>".$box['title']." - <a href='boxTracks.php?boxTrackID=".$boxTrack['boxTrackID']."'>Edit</a></td>";
					$html.="<td><i>as</i><br>".$boxTrack['outputTo']."</td>";
					$html.="<td><i>icon</i><br>".$boxTrack['icon']."</td>";
					$html.="</tr>";
				}
			}
			$html.="<tr class='row' style='background-color: #cceedd; text-align:right;'>";
			$html.="<td colspan=3><a href='boxTracks.php?trackID=".$track['trackID']."'>Add to new box...</a></td>";
			$html.="</tr>";
			
		}
		$html.="</table>";
		echo $html;
		//var_dump($tracks);
	} else {

		$kf=new Form('tracks');
		//$kf->redefine('boxID','Box','select','SELECT boxID, title FROM boxes');
		$kf->redefine('recordID','Record','select','SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) FROM records ORDER BY artistID');
		$kf->redefine('trackType','Track type','select',array(0=>"unknown",1=>"Music",2=>"Video",3=>"Speech"));
		echo "<div class='editTable'>";
		echo $kf->getForm();
		echo "</div> <!--.editTable -->";
	}
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	