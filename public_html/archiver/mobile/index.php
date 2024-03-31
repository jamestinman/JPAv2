<?php
include_once('../archiver.php');
$_SESSION['iD']=1;
$msg=false;
if (buttonPressed("Start")) redir("okgo.php");
?>
<html>
	<head>
		<title>ARCHIVER MOBILE</title>
		<script src="/jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="mobile.css">
	</head>

	<body>
		<div id='website'>
			<form action="index.php" method="post" name="archivalForm" enctype="multipart/form-data">
				<div class='section'>
					<a style='float:left;' href='index.php'><h1>ARCHIVER MOBILE</h1></a>
					<div style='clear:both; padding:0;'></div>
				</div>
				<?=(($msg)?"<div class='section red msg'>".$msg."</div>":""); ?>
				<h2>Choose a starting record</h2>
				<table>
					<tr><td>Section:</td><td><?=getSelect(['name'=>'iA', 'options'=>$iAoptions, 'selected'=>$iA, 'js'=>"onChange='this.form.submit();'"]); ?></td></tr>
					<tr><td>Chunk:</td><td><?=getSelect(['name'=>'iB','options'=>$iBoptions,'selected'=>$iB, 'js'=>"onChange='this.form.submit();'"]);?></td></tr>
					<tr><td>Record:</td><td><?=getSelect(['name'=>'iC','options'=>$iCoptions,'selected'=>$iC, 'js'=>"onChange='this.form.submit();'"]);?></td></tr>
				</table>
				<div class='section'>
					<input type="submit" name="submitBtn" value="Start Taking Pictures" />
				</div>
			</form>
	</body>
</html>
