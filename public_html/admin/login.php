<?php

include_once('../../include/e.php');
$msg=p('msg',false);

?>

<html>
	<head>
		<title>JPA login</title>
		<link href='css/cms.css' rel="stylesheet" type="text/css">
	</head>
	<body>
		<div id='website'>
			<div id='loginPage' style='display: block; margin-left:auto; margin-right:auto; width:300px; text-align: center; padding:50px'>
				<img src='img/record-boxes.png' style='padding: 10px;'>
				<?php
					if ($msg) {
						echo "<p>".$msg."</p>";
					}
				?>
				<form action="index.php" method="post">
				  <input type="hidden" name="action" value="login"><br>
				  Username: <input type="text" name="username"><br>
				  Password: <input type="password" name="password"><br>
				  <input type="submit" value="Submit">
				</form>
			</div>
		</div> <!-- #website -->
	</body>
</html>