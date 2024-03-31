
<?php 

include_once("../../include/johnpeel.php");

//var_dump($user);
var_dump($_POST);

if ($_FILES) {
	foreach ($_FILES as $file) {
		if ($file['size']>0) {
			processFile($file,$_POST);
		}	
	}
}

// adds files to record if it's already logged, logs it first if not
function processFile($file, $post) {
	var_dump($file);
	var_dump($post);
}

$letter=getIfSet($_POST,'letter',false);

function arrayToOptions($array,$value) {
	$html='';
	foreach ($array as $key=>$data) {
		$html.="<option name=";
	}
}

function getLetters() {
	$letters=array('#'=>'#');
	$alphas = range('A', 'Z');
	foreach ($alphas as $alpha) {
		$letters[$alpha]=$alpha;
	}
	return $letters;
}
?>

<html>
	<head>
		<title>ARCHIVER</title>
		<script src="/js/vendor/jquery-1.11.0.min.js"></script>
		<script>
			function showHideOptions(recordType) {
				$('.options').hide(300);
				$('#optionsFor'+recordType).show(300);
			}
			$('.recordType').click(function() {
				$("input", this).click();
			})
		</script>
		<style>
			* { font-family: Helvetica; margin:0; padding: 0; font-size: 4vw;}
			a { text-decoration: none; font-weight: bold; color :#333;}
			a:hover { text-decoration:underline;}
			body { font-size: 5vw; margin:0px; padding: 0px;}
			#website { width: 100%; background-color: #dddddd;}
			.section { width:100%; padding:1vw;}
			.section * { padding: 1vw;}
			.section.colour { background-color: #cabcab;}
			.section.darker { background-color: #ccc;}
			.recordType { width: 30%; overflow:hidden; display:inline-block; text-align:center;}
			.red {background-color: #edbbaa;}
			.green {background-color: #aaecaa;}
			.blue {background-color: #abbcee;}
			.section table { width:100%;}
			div.section.photos {
				height: 40vw;
				overflow-y:scroll;
			}
			table.photos td { width: 50%;}
			select {min-width: 30vw; }

		</style>
	</head>
	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>ARCHIVER v0.1</h1>
			</div>
			<form action="archive.php" method="post" name="archivalForm" enctype="multipart/form-data">
				<div class='section darker'>
					<h3>Hello <?php echo $user['screenName']; ?>!</h3>
					<!--Who are you? <select class='dropdown' name='user'><option>Will</option><option>James</option></select>-->
				</div>
				<div class='section'>
					<a style='float:left;' href='#'>&larr; Previous record</a>
					<a style='float:right; margin-right: 2vw;' href='#'>Exit &uarr;</a>
					<div style='clear:both; padding:0;'></div>
				</div>
				
				<div class='section darker'>
					<h3>Record</h3>
					<label for='recordTypeLP' class='recordType red'>
						<input id='recordTypeLP' type="radio" name="recordType" value="LP" onClick='showHideOptions($(this).val())' checked />
						LP
					</label>
					<label for='recordTypeSevenSingle' class='recordType green'>
						<input id='recordTypeSevenSingle' type="radio" name="recordType" value="Single" onClick='showHideOptions($(this).val())' />
						7" Single
					</label>
					<label for='recordTypeTwelveSingle' class='recordType blue'>
						<input id='recordTypeTwelveSingle' type="radio" name="recordType" value="Single" onClick='showHideOptions($(this).val())' />
						12" Single
					</label>
				</div>
				<div id='optionsForLP' class='options section'>
					<table>
						<tr>
							<td>
								John's LP index card Number:
							</td>
							<td>
								<input type='text' name='indexcard' value='0000' size=6 />
							</td>
						</tr>
					</table>
				</div>
				<div id='optionsForSingle' class='options section' style='display:none;'>
					<table>
						<tr>
							<td>
								Letter:
							</td>
							<td>
								<select class='dropdown'name='letter' onChange='$("#chosenLetter").html($(this).val());'>
									<option>A</option>
									<option>B</option>
									<option>C</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								JPA 7" ID:
							</td>
							<td>
								<span id='chosenLetter'>A</span>
								<input type='text' size=5 value='00001' />
							</td>
						</tr>
					</table>
				</div>
				<div class='section darker'>
					<h3>Condition</h3>
					<table>
						<tr>
							<td>Condition:</td>
							<td>
								<select name='condition'>
									<option>Mint</option>
									<option>Mint+</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>&nbsp;</td>
							<td>
								<input type='checkbox' name='attention' value='1' /> Special Attention needed
							</td>
						</tr>
					</table>
				</div>
				<div class='section'>
					<h3>Photos</h3>
				</div>
				<div class='section photos'>
					<table class='photos'>
						<tr>
							<td><label for='filename1'>Pic 1:</label></td>
							<td><input id="filename1" class="" type="file" size="30" name="pic1" /></td>
						</tr>
						<tr>
							<td><label for='filename2'>Pic 2:</label></td>
							<td><input id="filename2" class="" type="file" size="30" name="pic2" /></td>
						</tr>
						<tr>
							<td><label for='filename3'>Pic 3:</label></td>
							<td><input id="filename3" class="" type="file" size="30" name="pic3" /></td>
						</tr>
						<tr>
							<td><label for='filename4'>Pic 4:</label></td>
							<td><input id="filename4" class="" type="file" size="30" name="pic4" /></td>
						</tr>
						<tr>
							<td><label for='filename5'>Pic 5:</label></td>
							<td><input id="filename5" class="" type="file" size="30" name="pic5" /></td>
						</tr>
						<tr>
							<td><label for='filename6'>Pic 6:</label></td>
							<td><input id="filename6" class="" type="file" size="30" name="pic6" /></td>
						</tr>
						<tr>
							<td><label for='filename7'>Pic 7:</label></td>
							<td><input id="filename7" class="" type="file" size="30" name="pic7" /></td>
						</tr>
						<tr>
							<td><label for='filename8'>Pic 8:</label></td>
							<td><input id="filename8" class="" type="file" size="30" name="pic8" /></td>
						</tr>
						<tr>
							<td><label for='filename9'>Pic 9:</label></td>
							<td><input id="filename9" class="" type="file" size="30" name="pic9" /></td>
						</tr>
						<tr>
							<td><label for='filename10'>Pic 10:</label></td>
							<td><input id="filename10" class="" type="file" size="30" name="pic10" /></td>
						</tr>
					</table>
				</div>
				<div class='section darker'>
					<input style='float:right;' type="submit" name="submitBtn" value="Save + move to next record" />
					<div style='clear:both; margin:0px;'></div>
				</div>
			</form>
		</div> <!-- #website -->
	</body>
</html>