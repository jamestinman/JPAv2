<?php
include_once('archiver.php');
$syncable="records,pics,events"; // tables that are accessible for syncing

if (buttonPressed("Sync")) {
	if (doPush()) {
		if (doPull()) {
			echo "<h2 class='msg'>Sync OK</h2>";
			logEvent("sync");
		}
	}
} else if (buttonPressed("Push")) {
	doPush();
	logEvent("sync");
} else if (buttonPressed("Pull")) {
	doPull();
	logEvent("sync");
}

$lastSync=$DB->GetRow("SELECT * FROM events WHERE event='sync' ORDER BY eventID DESC LIMIT 1");
$sync=getSyncInfo();
?>
<html>
	<head>
		<title>SYNC</title>
		<script src="jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
		<script>
			var errorFn=function(jqXHR, textStatus) {
				alert('Error:'+textStatus);
				console.log(jqXHR);
			}
			<?php
			/*
			$pushData=[];
			foreach (explode(',',$syncable) as $table) {
				$max=callServer('count',['table'=>$table]);
				$maxID=getIfSet($max,'maxID',0);
				$keyCol=$DB->getPKfromDD($table);
				$pushData[$table]=$DB->GetAll("SELECT * FROM ".$table." WHERE ".$keyCol.">".$maxID,1);
			}
			echo "\nvar pushData=".json_encode($pushData).";\n";
			*/
			?>
			function doPush() {
				$.ajax({
					'url': "http://www.johnpeelarchive.com/archiver/ajax.php",
					'data': {'action':"push",'data':pushData},
					'dataType': "jsonp",
					'error': errorFn,
					'xhrFields': { withCredentials: true },
					'success': function(dat) { console.log("Ajax OK"); console.log(dat) }
				});
			}
		</script>
	</head>

	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>ARCHIVER SYNC</h1>
				<h2><a href='index.php'>ARCHIVER HOME</a> | <a href='sync.php'>SYNC HOME</a></h2>
			</div>
			<form action="sync.php" method="post" name="syncForm" enctype="multipart/form-data">
				<div class='section darker'>
					<p>Logged in as: <b><?=$DB->GetOne("SELECT username FROM users WHERE userID=".$_SESSION['userID']); ?></b> <a href='login.php?clearSession=1'>Logout</a>
				</div>
				<div id='leftCol'>
				<?php
				echo $sync['changesText'];
				?>
					<p>Last Sync: <b><?=getIfSet($lastSync,'eventDate','N/a')?></b> by <b><?=$DB->GetOne("SELECT username FROM users WHERE userID=".getIfSet($lastSync,'userID',0))?></b></p>
					<p>Remote Server has <?=$sync['remoteCount']?> records</p>
					<p>Local Server has <?=$sync['localCount']?> records</p>
				</div>
				<div id='listCol'>
					<input type='submit' name='submitBtn' value='Sync &lrarr;' class='bigSyncBtn' />
					<input type='submit' name='submitBtn' value='Push new records only' class='smallSyncBtn' />
					<input type='submit' name='submitBtn' value='Pull all records (WARNING: Will Overwrite!)' class='smallSyncBtn' />
				</div>
			</form>
			<br />
			<p><?=$sync['changesText']?></p>
			<br /><br />
			<p>After Syncing run this (with your local directory in it) from terminal:</p>
			<p style='font-family:courier; background-color:#000; color:#fff;padding:10px;'>rsync -avz ~/git/JPA/public_html/archiver/i avalon@johnpeelarchive.com:/var/www/johnpeelarchive.com/public_html/archiver/</p>
		</div>
	</body>
</html>
