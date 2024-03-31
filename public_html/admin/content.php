<?php
include_once('../../include/johnpeel.php');

$contentID=p('contentID',false);

if (p('submitBtn')=='Save') {
	$kf=new Form('content');
	$kf->handleSubmission();
	$contentID=false; // go back to main screen
}

if (p('submitBtn')=='Cancel') $contentID=false; // cancel should take you back to main screen
if (p('submitBtn')=='Delete') $contentID=false; // so should delete

?>

<html>
	<head>
		<title>
			Content
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
	if (!$contentID) {
		// get list of content blocks
		echo "<p><a href='content.php?contentID=new'>Add new content...</a></p><br>";
		$content=getContent();
		if (!$content) $content=array();
		echo "<table>";
		$colour=false;
		echo "<tr class='row' style='background-color:#cceedd;'>";
		echo "<td><b>blurb:</b></td>";
		echo "<td><b>written by:</b></td>";
		echo "<td><b>attached to:</b></td>";
		echo "<td> </td>";
		echo "</tr>";
		foreach($content as $thing) {
			echo "<tr class='row".(($colour)?" highlight":"")."'>";
			echo "<td>".substr($thing['blurb'], 0, 100)."</td>";
			$user=getUser($thing['userID']);
			echo "<td>".$user['screenName']."</td>";
			echo "<td>".listContentAttachments($thing['contentID'])."</td>";
			echo "<td><a href='content.php?contentID=".$thing['contentID']."'>Edit</a></td>";
			echo "</tr>";
			$colour=!$colour;
		}
		echo "</table>";
	} else {
		$kf=new Form('content');
		echo "<div class='editTable'>";
		$kf->redefine('userID','User','select','select userID, screenName FROM users');
		echo $kf->getForm();
		echo "</div> <!--.editTable -->";
		if ($contentID && $contentID>0) {
			$attachments=getContentAttachments($contentID);
			if (!$attachments) {
				$attachments=['tagID'=>[]];
			}
			foreach ($attachments as $type=>$stuff) {
				echo "<h2 style='margin-top:20px;'>".$type."</h2>";
				echo "<table>";
				echo "<tr class='row'><td><b>Linked to</b></td><td><b>Edit</b></td></tr>";
				if (count($stuff)>0) {
					foreach ($stuff as $contentLinkID=>$attachment) {
						echo "<tr class='row' style='background-color: #ccddee;'>";
						if ($type=='tagID') {
							$tag=getTag($attachment);
							$tagType=getTagType($tag['tagTypeID']);
							echo "<td><i>title</i><br>".$tagType['title'].": ".$tag['value']."</td>";
						} else if ($type=='artistID') {
							$artist=getArtist($attachment);
							echo "<td><i>title</i><br>Artist: ".$artist['title']."</td>";
						} else if ($type=='recordID') {
							$record=getRecord($attachment);
							echo "<td><i>title</i><br>Record: ".$record['artist'].' - '.$record['title']."</td>";
						} else if ($type=='boxID') {
							$box=getRecordBox($attachment);
							echo "<td><i>title</i><br>Box: ".$box['title']."</td>";
						}
						echo "<td><a href='contentLinks.php?contentLinkID=".$contentLinkID."'>Edit...</a></td>";
						echo "</tr>";
					}
				}
				echo "<tr style='background-color:#cceedd;'><td colspan=3><a href='contentLinks.php?contentLinkType=".$type."&contentLinkID=0&contentID=".$contentID."'>Add content link...</a></td></tr>";
				echo "</table>";
			}
		}
		
	}
?>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	