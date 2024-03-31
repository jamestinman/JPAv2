<?php
include_once('../../include/johnpeel.php');

global $cacheFace;

$boxID=p('boxID',false);
if ($boxID=='false') $boxID=false;
$recordID=p('recordID',false);

if (p('submitBtn')=='Save') {	
	$kf=new Form('records');
	$kf->redef('numImages',array('coltype'=>'TEXT','title'=>'Number of sleeve images')); // needed so it saves a number and not a file
	$kf->handleSubmission();
	
	$cacheFace->delCached("jpaAdminRecordRow".$recordID);
}

if (p('submitBtn')=='Cancel') $recordID=false; // cancel should take you back to main screen
if (p('submitBtn')=='Delete') $recordID=false; // so should delete

?>

<html>
	<head>
		<title>
			Records
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
	$recTypes=array(false=>'unknown',1=>'12" album', 2=>'7" record',3=>'12" acetate',4=>'7" acetate',5=>'Compact Disc',6=>'Cassette Tape');
	if (!$recordID) {
		// get list of recordBits to edit...
		echo recordBoxDropper(true);
		echo "<p><a href='records.php?recordID=new'>Add new record...</a></p><br>";
		$records=getRecords($boxID); // will filter by boxID if applicable
		//var_dump($records);
		if (!$records) $records=array();
		echo "<table>";
		$colour=false;
		echo "<tr class='row' style='background-color:#cceedd;'>";
		echo "<td><b>JPID</b></td>";
		echo "<td><b>Title</b></td>";
		echo "<td><b>Record Type</b></td>";
		echo "<td><b>Tags</b></td>";
		echo "<td> </td>";
		echo "</tr>";
		foreach($records as $record) {
			if ($cachedHtml=$cacheFace->get("jpaAdminRecordRow".$record['recordID'])) {
				echo $cachedHtml;
			} else {
				$cachedHtml="<tr class='row".(($colour)?" highlight":"")."'>";
				$cachedHtml.="<td>".$record['jpID']."</td>";
				$cachedHtml.="<td>".$record['recordTitle']."</td>";
				$cachedHtml.="<td>".$recTypes[$record['type']]."</td>";
				$cachedHtml.="<td>".getRecordTagList($record['recordID'])."</td>";
				$cachedHtml.="<td><a href='records.php?recordID=".$record['recordID']."'>Edit</a></td>";
				$cachedHtml.="</tr>";
				$cacheFace->set("jpaAdminRecordRow".$record['recordID'],$cachedHtml,6*60*60); // for 6 hours
				echo $cachedHtml;
			}
			
			$colour=!$colour;
		}
		echo "</table>";
	} else {
		
		$kf=new Form('records');
		$kf->redefine('artistID','Artist','select','select artistID, title FROM artists');
		$kf->redef('card',array('title'=>"John's card number"));
		$kf->redef('jpID',array('title'=>"John Peel Archive record reference"));
		$kf->hide('artist');
		$kf->hide('customPath');
		$kf->hide('highlightPath');
		$kf->hide('label');
		$kf->redefine('type','Record type','select',$recTypes);
		
		$kf->redef('numImages',array('coltype'=>'TEXT','title'=>'Number of sleeve images'));
		$kf->ignore('album'); // duplicated as title for consistency so ignore this
		echo "<div class='editTable'>";
		echo $kf->getForm();
		echo "</div> <!--.editTable -->";
		echo "<hr>";
		if ($recordID!="new") {
			// record must be saved before other stuff can be added.
			$tagTypes=getTagTypes();
			foreach ($tagTypes as $tagType) {
				$recordTags=getRecordTags($recordID,$tagType['tagTypeID']);
				echo "<h2>".$tagType['title']."</h2>";
				echo "<table>";
				echo "<tr class='row'><td><b>Type</b></td><td><b>Tags</b></td><td><b>Edit</b></td></tr>";
				if ($recordTags) {
					foreach ($recordTags as $recordTag) {
						echo "<tr class='row' style='background-color: #ccddee;'>";
						$tagID=$recordTag['tagID'];
						$tag=getTag($tagID);
						$tagType=getTagType($tag['tagTypeID']);
						echo "<td><i>type</i><br>".$tagType['title']."</td>";
						echo "<td><i>title</i><br>".$recordTag['value']."</td>";
						echo "<td><a href='recordTags.php?tagTypeID=".$tagType['tagTypeID']."&recordID=".$recordID."&tagID=".$tagID."'>Edit...</a></td>";
						echo "</tr>";
					}
				}
				echo "<tr style='background-color:#cceedd;'><td colspan=3><a href='recordTags.php?tagTypeID=".$tagType['tagTypeID']."&recordTagID=0&recordID=".$recordID."'>Add new tag...</a></td></tr>";
				echo "</table>";
			}
			echo "<hr>";
			
			// list / edit which boxes it may live in
			$boxRecords=getBoxRecords($recordID); // naming is weird here, but there's a getRecordBox function already and this matches the DB table better
			echo "<h2>Record Boxes</h2>";
			echo "<table>";
			echo "<tr class='row'><td><b>Record Box</b></td><td><b>Edit</b></td></tr>";
			if ($boxRecords) {
				foreach ($boxRecords as $boxRecord) {
					echo "<tr class='row' style='background-color: #ccddee;'>";
					$boxID=$boxRecord['boxID'];
					$box=getRecordBox($boxID);
					echo "<td><i>title</i><br>".$box['title']."</td>";
					echo "<td><a href='boxRecords.php?boxRecordID=".$boxRecord['boxRecordID']."'>Edit...</a></td>";
					echo "</tr>";
				}
			}
			echo "<tr style='background-color:#cceedd;'><td colspan=2><a href=boxRecords.php?boxRecordID=0&recordID=".$recordID."'>Add new tag...</a></td></tr>";
			echo "</table>";
			echo "<hr>";
			// edit cover etc
			$sleeve=getRecordSleeve($recordID);
			if ($sleeve) {
				$link='sleeves.php?sleeveID='.$sleeve['sleeveID']; // edit current custom sleeve
				echo "<div style='max-width:200px;'>";
				echo "<a href='".$link."'>";
				echo "<p>Edit sleeve/parallax details</p>";
				echo "<img style='width:100%;' src='".getRecordCover($recordID)."'>";
				echo "</a></div>";
			} else {
				$link='sleeves.php?recordID='.$recordID; // new custom sleeve
				echo "<a href='".$link."'>Add custom sleeve...</a>";
			}
		}
		
		


	}
	

?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	