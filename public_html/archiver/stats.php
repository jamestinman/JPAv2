<?php
include_once('archiver.php');
?>
<html>
	<head>
		<title>ARCHIVER</title>
		<script src="jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
	</head>

	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>ARCHIVER STATS - <?=$env?></h1>
				<p><a href='index.php'>back to ARCHIVER home</a></p>
			</div>
			<table>
				<tr><th>User</th><th>Records Pulled</th><th>Photos Taken</th><th>Processed</th><th>Viewed</th></tr>
<?php
foreach ($DB->GetAll("SELECT * FROM users ORDER BY username") as $user) {
	echo "
		<tr>
			<td>".$user['username']."</td>
			<td>".$DB->GetOne("SELECT COUNT(1) FROM records WHERE photoTakenByUserID=".$user['userID'])."</td>
			<td>".$DB->GetOne("SELECT COUNT(1) FROM pics WHERE userID=".$user['userID'])."</td>
			<td>".$DB->GetOne("SELECT COUNT(1) FROM events WHERE userID=".$user['userID']." AND event='Record Saved'")."</td>
			<td>".$DB->GetOne("SELECT COUNT(1) FROM events WHERE userID=".$user['userID']." AND event='Viewed'")."</td>
		</tr>
	";
}
?>
			</table>
		</div>
	</body>
</html>
