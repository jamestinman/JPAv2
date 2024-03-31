<?php
include_once('../../include/johnpeel.php');

$boxID=p('boxID',false);
if ($boxID=='false') $boxID=false;
$recordBitID=p('recordBitID',false);

if (p('duplicate',false) && p('recordBitID',false)) {
	duplicateRecordBit(p('recordBitID'));
	$recordBitID=false;
	redir('recordBits.php'); // get rid of params
}


if (p('submitBtn')=='Save') {

}
$kf=new Form('recordBits');
$kf->handleSubmission();


if (p('submitBtn')=='Cancel') $recordBitID=false; // cancel should take you back to main screen
if (p('submitBtn')=='Delete') $recordBitID=false; // so should delete

?>
<html>
	<head>
		<title>
			Record Bits
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
	if (!$recordBitID) {
		// get list of recordBits to edit...
		echo recordBoxDropper(true);
		$recordBits=getRecordBits($boxID);
		if (!$recordBits) $recordBits=array();
		echo "<table>";
		$colour=false;
		foreach ($recordBits as $recordBit) {
			echo "<tr class='row".(($colour)?" highlight":"")."'>";
			echo "<td>".$recordBit['recordBitID']."</td>";
			$box=getRecordBox($recordBit['boxID']);
			if (!$boxID) echo "<td>".$box['title']."</td>";
			echo "<td><a href='recordBits.php?recordBitID=".$recordBit['recordBitID']."'>".$recordBit['tags']."</a></td>";
			$record=getRecord($recordBit['recordID']);
			if ($record) {
				echo "<td>".$record['artist']." - ".$record['title']."</td>";
			} else {	
				echo "<td><i>No record associated</i></td>";
			}
			echo "<td><a href='recordBits.php?duplicate=true&recordBitID=".$recordBit['recordBitID']."'>Duplicate</a></td>";
			echo "</tr>";
			$colour=!$colour;
		}
		echo "</table>";
	} else {
		
		$kf=new Form('recordBits');
		$kf->redefine('boxID','Record Box','select','SELECT boxID, title FROM boxes');
		$kf->redefine('recordID','Record','select','SELECT recordID, CONCAT((SELECT title FROM artists WHERE records.artistID=artists.artistID)," - ",title) FROM records ORDER BY artistID');
		echo "<div class='editTable'>";
		echo $kf->getForm();
		echo "</div> <!--.editTable -->";
	}
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	