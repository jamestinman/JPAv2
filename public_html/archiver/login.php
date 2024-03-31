<?php
include_once('../../include/society/DB.php');
$DB=new DB('johnpeelarchive','thelookoflove','archiver');
startSession("johnpeel");
$msg="Please login";
$users=addNullOption($DB->GetArr("SELECT userID,username FROM users ORDER BY username"),0,'* Who are you? *');
if (np("clearSession")) {
	$_SESSION['userID']=0;
  logout();
	$msg="Logged out OK";
} else if (p("username")) {
	$user=$DB->GetRow("SELECT * FROM users WHERE username=".fss(p("username")));
	if ($user && $user['pwd']==p("pwd")) {
		$_SESSION['userID']=$user['userID'];
		redir(getIfSet($_SESSION,'redirPage',"index.php"));
	} else {
		$msg="Please try again :(";
	}
}
?>
<html>
	<head>
		<title>ARCHIVER LOGIN</title>
		<script src="jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
	</head>

	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>ARCHIVER LOGIN</h1>
				<h2 id='msg' style='color:#e33;'><?=$msg?></h2>
			</div>
			<form action="login.php" method="post" name="loginForm" enctype="multipart/form-data">
				<table>
					<tr><td>User:</td><td><input type='text' name='username' /></td></tr>
					<tr><td>Password:</td><td><input type='password' name='pwd' id='pwd' /></td></tr>
				</table>
				<input type='submit' name='submitBtn' value='Login' />
			</form>
		</div>
	</body>
</html>
