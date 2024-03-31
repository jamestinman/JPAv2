<?php
// basically a security check
include_once('../../include/johnpeel.php');

?>

<html>
	<head>
		<title>JPA player stats machine</title>
		<script type="text/javascript" src="/js/libs/jquery-1.7.2.min.js"></script>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
		<script src='js/jpaStats.js'></script>
	</head>
	<body>
		<div id='website'>
			<?php include_once('sidebar.html'); ?>
			<h1>John Peel Archive Record Box Statistics Page</h1>
			<div id='overview'>
			<?php
				$data=getPlayTotals();
				foreach ($data as $yearID=>$year) {
					echo "<hr><p>Plays over 30 seconds long for 11th Sept ".$yearID." > 10th Sept ".($yearID+1)."</p>
					
					<table>
							<tr>
								<th>Box</th>
								<th>Plays</th>
							</tr>";
						if (is_array($year['plays'])) {
							foreach ($year['plays'] as $id=>$boxPlays) {
								$box=getRecordBox($id);
								echo "<tr><td>".$box['title']."</td><td>".$boxPlays."</td></tr>";
							}
						}
					echo "<tr><td>Total</td><td><b>".$year['total']['count']."</b></td></tr>";
					echo "</table><br><br>";
				}
			?>
			</div>
			<div id='content'>

			</div>
		</div>
	</body>
	<style>
		.track { position: relative; display: block; padding: 5px; background-color: rgba(128,128,128,0.5);}
		.track.shaded { background-color: rgba(192,192,192,0.5);}
		p { padding: 0px; margin: 0px;}
		p.artistName { font-weight: bold;}
		.stats { text-align: center;}
		.playBar { width: 100%; background-color: #dd0000; height: 20px; overflow:hidden;}
		.unFinPlays { height:100%; background-color: #dddd00; top: 0px; display: inline-block;}
		.finPlays { height: 100%; background-color: #00dd00; top:0px; display: inline-block;}
		.box .tracks.hiding { display: none;}

		table { background-color: rgba(255,255,0,0.2); margin:10px; padding:5px; min-width: 400px;}
		table th {
			background-color:rgba(255,255,0,0.4);
		}
	</style>
</html>
