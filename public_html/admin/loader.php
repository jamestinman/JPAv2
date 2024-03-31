<html>
	<head>
		<title>
			Record loader
		</title>
		<script type="text/javascript" src="/js/libs/jquery-1.7.2.min.js"></script>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
		<script src='js/loader.js'></script>
	</head>
	<body>
		<div id='website'>

<?php 
	include_once('sidebar.html');
	include_once('../../include/johnpeel.php');
?>
			<div id='content'>
				<select id='recordType'>
					<option value=1>12" record</option>
					<option value=2>7" single</option>
					<option value=3>Other</option>
				<select>
				<input type='button' value='Go!' onClick="uploadRecords($('#uploadData').val(),$('#recordType').val(),uploadedRecords)">
				<hr>
				<textarea id='uploadData' style='width:90%; height: 70%;'></textarea>
			</div> <!-- #content -->
		</div> <!-- #website -->
	</body>
</html>
	