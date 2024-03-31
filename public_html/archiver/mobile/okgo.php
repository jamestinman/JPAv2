<?php
include_once('../archiver.php');

$msg=$msg1=$msg2=false;

// Handle uploads
if (buttonPressed("Skip") && !buttonPressed("part of prev")) {
	$msg1=getRef($iA,$iB,$iC,$iD)." Skipped";
	$iC++;
	$iD=1;
} else if (buttonPressed("Save") || buttonPressed("part of prev")) {
	// Note: iA, iB, iC and iD are setup by archiver.php
	$data=[
		'iA'=>$iA,
		'iB'=>$iB,
		'iC'=>$iC,
		'iD'=>$iD,
		'cond'=>p("cond"),
		'numCopies'=>np("numCopies"),
		'partOfPrevious'=>((buttonPressed("part of prev"))?1:0)
	];
	if (notnull(p("barcode"))) $data['barcode']=p("barcode");
	// Do we already have this record?
	$record=getRecord($iA,$iB,$iC,$iD);
	// Save record of record
	if ($record['recordID']>0) {
		$recordID=$DB->doUpdate('records',$data,$record['recordID']);
		logEvent("Photos Added");
	} else {
		$data['format']=map($iA,['LP'=>'LP','7'=>'7"'],false);
		$data['photoTakenByUserID']=$_SESSION['userID'];
		$data['photoTakenDate']=sqlNowTime();
		$recordID=$DB->doInsert('records',$data);
		logEvent("Record Created");
	}
	// Upload pics
	$uploadCount=0;
	for ($n=1; $n<=3; $n++) {
		$originalFilename=getIfSet(getIfSet($_FILES,'pic'.$n),'name');
		// Do we have a file in this slot?
		if ($originalFilename) {
			$ext=getExt($originalFilename);
			$picID=$DB->doInsert('pics',['recordID'=>$recordID, 'userID'=>$_SESSION['userID']]);
			$newFilename=uploadFile('pic'.$n,'../i/',getRef($iA,$iB,$iC,$iD).'picID'.$picID.".".$ext);
			$DB->doUpdate('pics',['filename'=>$newFilename],$picID);
			$uploadCount++;
		}
	}
	// Refresh our record record
	$record=getRecord($iA,$iB,$iC,$iD);
	$msg1="#".$record['recordID']." Saved ".(($uploadCount>0)?" (uploaded ".$uploadCount." OK)":"")." ".getDesc($record);
	if (buttonPressed("add another version")) {
		$iD++;
	} else {
		// Move to the next record?
		if ($iC==999) {
			$msg2="ALL DONE!";
		} else {
			$iC++;
			$iD=1;
		}
	}
}

$record=getRecord($iA,$iB,$iC,$iD);
if ($record['recordID']>0) {
	$msg2="Warning - record has already been created (though you can still change condition and upload more pics): ".$record['ref'];
}
?>
<html>
	<head>
		<title>ARCHIVER MOBILE</title>
		<script src="../jquery-1.11.2.min.js"></script>
		<script type="text/javascript" src="../JOB.js"></script><!-- Javascript Only Barcode Scanner thx to https://github.com/EddieLa/JOB -->
		<link rel="stylesheet" href="mobile.css">
	</head>

	<body>
		<div id='website'>
			<form action="okgo.php" method="post" name="archivalForm" enctype="multipart/form-data">
				<div class='section'>
					<a style='float:left;' href='index.php'><h1>ARCHIVER MOBILE</h1></a>
					<div style='clear:both; padding:0;'></div>
				</div>
				<?=(($msg1)?"<div class='section green msg'>".$msg1."</div>":""); ?>
				<?=(($msg2)?"<div class='section red msg'>".$msg2."</div>":""); ?>
				<div class='section darker'>
					<h3>Current Record:</h3>
					<!-- <span id='iA'><?=$iA;?></span> <span id='iB'><?=$iB;?></span> -->
					<?=getSelect(['name'=>'iC', 'options'=>$iCoptions, 'selected'=>$iC, 'js'=>"onChange='this.form.submit();'"]); ?><br />
					<?php
					if ($record['numVersions']>1 || $iD>1) {
						echo getSelect(['name'=>'iD', 'options'=>$iDoptions, 'selected'=>$iD, 'js'=>"onChange='this.form.submit();'"]);
					} else {
						echo "<input type='hidden' name='iD' value='1' />";
					}
					?>
				<div class='section'>
					<input type="submit" name="submitBtn" value="Save" />
					<input type='submit' name='submitBtn' value='Skip' />
					<input type='submit' name='submitBtn' value='Skip (part of previous)' />
				<input type="submit" name="submitBtn" value="Save + add another version" />
				</div>
<?php
if ($record['numPics']>0) {
	echo "<div class='gallery'>";
	foreach ($record['pics'] as $pic) {
		echo "<img src='../i/".$pic['filename']."' width=300 onClick='if ($(this).attr(\"width\")==300) { $(this).attr(\"width\",\"600\"); } else { $(this).attr(\"width\",\"300\"); } '/>";
	}
	echo "</div>";
}
?>
				<div class='section darker'>
					<table class='photos'>
						<tr>
							<td colspan=2>Condition:</td>
						</tr>
						<tr>
							<td colspan=2>
								<div style='margin: 20px; font-size: 1.6em;'>
							<?php
								$conditionOptions=str_replace(",F,P","",$conditionOptions);
								$count=0;
								$remainingOptions=[];
								foreach (explode(',',$conditionOptions) as $condition) {
									if (++$count<=9) {
										echo "<input type='radio' name='cond' id='cond".$condition."' value='".$condition."'".((getIfSet($record,'cond')==$condition)?" checked":"")." /> <label for='cond".$condition."'>".$condition."</label> ";
									} else {
										$remainingOptions[$condition]=$condition;
									}
								}
								echo "</div>";
								if (sizeOf($remainingOptions)>0) {
									echo getSelect(['name'=>"othercond", 'options'=>mergeOptions($remainingOptions,[""=>'-']), 'selected'=>getIfSet($record,'cond')]);
								}
							?>
							</td>
						</tr>						<tr><td>Pic 1:</td><td><input id="filename" class="" type="file" size="30" name="pic1" /></td></tr>
						<tr><td>Pic 2:</td><td><input id="filename" class="" type="file" size="30" name="pic2" /></td></tr>
						<tr><td>Pic 3:</td><td><input id="filename" class="" type="file" size="30" name="pic3" /></td></tr>
						<tr><td>Barcode:</td><td><input id="Take-Picture" type="file" accept="image/*;capture=camera" /></td></tr>
						<tr id='barcodeArea' style='display:none;'>
							<td>&nbsp;</td>
							<td>
								<p><input name='barcode' id='barcode' value='<?=getIfSet($record,'barcode');?>' /></p>
								<canvas width="320" height="240" id="picture"></canvas>
								<p style='color: #d22; display:none;' id="textbit"></p>
							</td>
						</tr>
						<tr><td>Num identical copies:</td><td><?=getSelect(['name'=>"numCopies",'options'=>"1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20",'selected'=>getIfSet($record,'numCopies',1)])?></td></tr>
					</table>
				</div>
			</form>
		<script>
			setTimeout(function() { $('.section.msg').hide(300); }, 1000);

			// JOB JS Only Barcode code:
			var takePicture = document.querySelector("#Take-Picture"),
			showPicture = document.createElement("img");
			Result = document.querySelector("#textbit");
			var canvas =document.getElementById("picture");
			var ctx = canvas.getContext("2d");
			JOB.Init();
			JOB.SetImageCallback(function(theBarcode) {
				$('#barcodeArea').show(300);
				if(theBarcode.length > 0){
					var tempArray = [];
					for(var i = 0; i < theBarcode.length; i++) {
						tempArray.push(theBarcode[i].Format+" : "+theBarcode[i].Value);
						$('#barcode').val(theBarcode[i].Value); // Uses the last barcode found
					}
					Result.innerHTML=tempArray.join("<br />");
				}else{
					if(result.length === 0) {
						$('#msg').html("Barcode decoding failed.").show(300);
					}
				}
			});
			JOB.PostOrientation = true;
			JOB.OrientationCallback = function(result) {
				canvas.width = result.width;
				canvas.height = result.height;
				var data = ctx.getImageData(0,0,canvas.width,canvas.height);
				for(var i = 0; i < data.data.length; i++) {
					data.data[i] = result.data[i];
				}
				ctx.putImageData(data,0,0);
			};
			JOB.SwitchLocalizationFeedback(true);
			JOB.SetLocalizationCallback(function(result) {
				ctx.beginPath();
				ctx.lineWIdth = "2";
				ctx.strokeStyle="red";
				for(var i = 0; i < result.length; i++) {
					ctx.rect(result[i].x,result[i].y,result[i].width,result[i].height); 
				}
				ctx.stroke();
			});
			if(takePicture && showPicture) {
				takePicture.onchange = function (event) {
					var files = event.target.files;
					if (files && files.length > 0) {
						file = files[0];
						try {
							var URL = window.URL || window.webkitURL;
							showPicture.onload = function(event) {
								Result.innerHTML="";
								JOB.DecodeImage(showPicture);
								URL.revokeObjectURL(showPicture.src);
							};
							showPicture.src = URL.createObjectURL(file);
						}
						catch (e) {
							try {
								var fileReader = new FileReader();
								fileReader.onload = function (event) {
									showPicture.onload = function(event) {
										Result.innerHTML="";
										JOB.DecodeImage(showPicture);
									};
									showPicture.src = event.target.result;
								};
								fileReader.readAsDataURL(file);
							}
							catch (e) {
								$('#msg').html("Neither createObjectURL or FileReader are supported").show(300);
							}
						}
					}
				};
			}

		</script>
	</body>
</html>
