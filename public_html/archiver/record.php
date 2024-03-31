<?php
include_once('archiver.php');
$recordID=np("recordID",0);

if (p("a")=="remove") {
	$DB->execute("DELETE FROM pics WHERE picID=".np("picID"));
}

$record=getRecordInfo($recordID);

if ($env=="localhost") {
	// Find out whether a master copy of this record exists on the server
	$syncInfo=getSyncInfo();
	if ($syncInfo['maxRecordID']>$recordID) {
		echo "
			<div class='recordSyncInfo'>
				<h2>THIS RECORD'S MASTER COPY IS ON THE LIVE SERVER</h2>
				<p><a href='http://www.johnpeelarchive.com/archiver/record.php?recordID=".$recordID."' target='live'>Update it there instead</a></p>
			</div>
		";
	}
}

if (in(p("a"),"twist,twistagain")) {
	$pic=$DB->GetRow("SELECT * FROM pics WHERE picID=".np("picID"));
	if ($pic) {
		$ff=dirname(__FILE__)."/i/".$filename=$pic['filename'];
		rotateFile($ff);
		if (p("a")=="twistagain") rotateFile($ff);
	}
}

$kf=new Form('records');
$attentionOptions=[''=>"NO"];
foreach ($DB->GetAll("SELECT attention,COUNT(1) cnt FROM records GROUP BY attention ORDER BY COUNT(1)") as $att) {
	$attentionOptions[$att['attention']]=$att['attention'];
}
$kf->def([
	'iA'=>['title'=>"Record type:",'options'=>crushOut($iAoptions,-1)],
	'iB'=>['title'=>"Chunk:",'options'=>$iBoptions],
	'iC'=>['title'=>"Chunk index:",'options'=>$iCoptions],
	'iD'=>['title'=>"Version:",'options'=>$iDoptions],
	'cond'=>['title'=>"Condition:", 'options'=>$conditionOptions],
	'format'=>['coltype'=>'selectoverride','options'=>$DB->GetKeys("SELECT format,COUNT(1) FROM records GROUP BY format ORDER BY COUNT(1) DESC")],
	'hasInsert'=>['size'=>44, 'title'=>"Has insert:<br /><span style='font-size:0.8em;'>(other than press release)</span>"],
	'hasNote'=>['size'=>44],
	'comment'=>['cols'=>44, 'rows'=>4],
	'country'=>['coltype'=>'selectoverride','options'=>$DB->GetKeys("SELECT country,COUNT(1) FROM records GROUP BY country ORDER BY COUNT(1) DESC")],
	'stereo'=>['options'=>[0=>'Unknown / not relevant to value',1=>'Stereo',2=>'Mono']],
	'attention'=>['title'=>"Requires attention?",'coltype'=>"selectoverride",'options'=>$attentionOptions],
	'attentionGiven'=>['title'=>"Attention response"],
	'numCopies'=>['options'=>"1,2,3,4,5,6,7,8,9"],
	'partOfPrevious'=>['title'=>"Extension of previous record"],
	'va'=>['title'=>"V &amp; A Loan", 'coltype'=>"checkbox"],
	'vaRef'=>['title'=>"V &amp; A Ref", 'coltype'=>"text"]
]);
$kf->ignore("photoTakenByUserID,photoTakenDate,infoEnteredByUserID,infoEnteredDate");
if (!$inTheFamily) $kf->ignore("price");
$kf->addToSection("JPA Index","iA,iB,iC,iD,numCopies,partOfPrevious,attention,attentionGiven");
$kf->addToSection("Catalog","catNo,artist,title,format,barcode");
$kf->addToSection("Catalog 2","country,recordYear,stereo,label");
$kf->addToSection("Loans","va,vaRef");
$kf->addToSection("JPA Specific","annotations,promo,pressRelease,hasInsert,hasNote,cond,price");

$redirUrl='index.php';

if (buttonPressed("move to next data")) {
	$nextRecord=$DB->GetRow("SELECT * FROM records WHERE iA='".$record['iA']."' AND iB='".$record['iB']."' AND ((iC=".$record['iC']." AND iD>".$record['iD'].") OR iC>".$record['iC'].") AND recordID!=".$recordID." AND (title IS NULL OR artist IS NULL) AND photoTakenByUserID>0 ORDER BY iC,iD LIMIT 1");

} else if (buttonPressed("move to next valuation")) {
	$nextRecord=$DB->GetRow("SELECT * FROM records WHERE iA='".$record['iA']."' AND iB='".$record['iB']."' AND ((iC=".$record['iC']." AND iD>".$record['iD'].") OR iC>".$record['iC'].") AND recordID!=".$recordID." AND title IS NOT NULL AND artist IS NOT NULL AND (price IS NULL OR price=0) ORDER BY iC,iD LIMIT 1");

} else {
	$nextRecord=$DB->GetRow("SELECT * FROM records WHERE iA='".$record['iA']."' AND iB='".$record['iB']."' AND ((iC=".$record['iC']." AND iD>".$record['iD'].") OR iC>".$record['iC'].") AND recordID!=".$recordID." ORDER BY iC,iD LIMIT 1");
}
if (!$nextRecord) {
	$redirUrl='index.php';
} else {
	$redirUrl="record.php?recordID=".$nextRecord['recordID'];
}

if (buttonPressed("save")) {
	$recordID=$kf->handleSave();
	logEvent("Record Saved",$recordID);
	if (isnull(getIfSet($record,'title')) && isnull(getIfSet($record,'artist')) && p("title") && p("artist")) {
		// Pay people!
		$DB->doUpdate('records',['infoEnteredByUserID'=>$_SESSION['userID'], 'infoEnteredDate'=>sqlNowTime()], $recordID, 'recordID');
	}
	redir($redirUrl);
} else if (buttonPressed("delete")) {
	$kf->handleDelete();
	logEvent("Record Deleted",$recordID);
	redir($redirUrl);
} else if (buttonPressed("cancel")) {
	redir('index.php');	
} else {
	logEvent("Viewed",$recordID);
}
$kf->addSubmit("Save, Save + move to next data, Save + move to next valuation");
$kf->addHTML("<input type='submit' name='submitBtn' id='submitBtn' value='Delete' onClick='return confirm(\"Are you sure?\");'");
?>
<html>
	<head>
		<title>RECORD</title>
		<script src="jquery-1.11.2.min.js"></script>
		<script>
			var discogsToken="skbfOHOnSMVeWaStBHgOFenwFzGwbjmTqUzVnHEF"; // leon's token: "iqaCXbUuFAEqakpLelrDBOLFiUlbOWgxFwSLZaca";
			var records=[];
		</script>
		<link rel="stylesheet" href="/css/private.css">
		<style>
			#ksCatalog {background-color: #bbf;}
		</style>
		<script>

			// Discog Lookup Search Stuff...
			var searchCallback=function(dat, successStatus) {
				records=dat.data.results;
				if (records.length<1) {
					alert("Discogs could not find on Discogs :(");
					return false;
				}
				// if (records.length==1) useRecord(records[0]); // Auto-use in case of 1 return
				// Multiple results - display these to be picked
				var h="<div id='recordChooser'>";
				for (var i in records) {
					var record=records[i];
					console.log(record);
					h+="<div class='recordToSelect'><a href='#' onClick='useRecord(records["+i+"]); return false;'><img src='"+record.thumb+"' /><br />Cat. <b>"+record.catno+"</b></a> : "+record.format[1]+" "+record.country+" "+record.year+"<br /><a href='http://www.discogs.com"+record.uri+"' target='discogs'>"+record.title+"</a></div>";
				}
				h+="</div>";
				$('#barcodeTd').html($('#barcodeTd').html()+h);
			}

			var useRecord=function(record) {
				$('#recordChooser').remove();
				console.log(record);
				var artist=record.title.substr(0,record.title.indexOf(' - '));
				var title=record.title.substr(record.title.indexOf(' - ')+3,record.title.length);
				$('#artist').val(artist);
				$('#title').val(title);
				$('#recordYear').val(record.year);
				$('#catNo').val(record.catno);
				$('#country').val(record.country);
				$('#format').val(record.format[1]);
				$('#newcountry').val(record.country);
				$('#newformat').val(record.format[1]);
				$('#barcodeTd').html($('#barcodeTd').html()+"<br /><div id='chosenRecord'><img src='"+record.thumb+"' /></div>");
				// Look up a price
				// e.g. https://api.discogs.com/marketplace/price_suggestions?token=skbfOHOnSMVeWaStBHgOFenwFzGwbjmTqUzVnHEF&release_id=4576439
				/*
				postData={'token':discogsToken,'release_id':record.id}
				$.ajax({
					'type': "POST",
					'crossDomain': true,
					'url': "https://api.discogs.com/marketplace/price_suggestions",
					'data': postData,
					'dataType': "jsonp",
					'error': errorFn,
					'xhrFields': { withCredentials: true },
					'success': priceCallback
				});
				*/
			}

			var priceCallback=function(dat, successStatus) {
				return false; // $('#price').val((dat.min+dat.max)/2);
			}

			var errorFn=function(jqXHR, textStatus) {
				alert('Error searching discogs :( '+textStatus+'. Check console');
				console.log(jqXHR);
			}
			function searchDiscogs() {
				$('#recordChooser').remove();
				$('#chosenRecord').remove();
				var postData={
					'token':discogsToken,
					'barcode':$('#barcode').val(),
					'catno':$('#catNo').val(),
					'artist':$('#artist').val(),
					'release_title':$('#title').val(),
					'format':$('format').val()
				}
				$.ajax({
					'type': "POST",
					'crossDomain': true,
					'url': "https://api.discogs.com/database/search",
					'data': postData,
					'dataType': "jsonp",
					'error': errorFn,
					'xhrFields': { withCredentials: true },
					'success': searchCallback
				});
			}

			// document load stuff...
			$().ready(function() {
				// Bang search button next to artist/title/barcode
				var h="<br /><button onClick='searchDiscogs(); return false;'>Search Discogs</button>";
				// h+="<button onClick='$(\"#barcode\").val($(\"#barcode\").val().substr(1,$(\"#barcode\").val().length-2)); searchDiscogs({\"barcode\":$(\"#barcode\").val()}); return false;'>Search with shortened barcode</button>";
				$('#barcodeTitleTd').append(h);

			});

		</script>
	</head>

	<body>
<?php
echo "
		<a href='index.php'>Archiver</a>
		<h2><a href='record.php?recordID=".$recordID."'>".nvl($record['desc'],$record['ref'])."</a></h2>
		<div class='history'>
			<p>Photos taken by: ".getIfSet($users,getIfSet($record,'photoTakenByUserID'))." @ ".getIfSet($record,'photoTakenDate')."</p>
			<p>Info entered by: ".getIfSet($users,getIfSet($record,'infoEnteredByUserID'))." @ ".getIfSet($record,'infoEnteredDate')."</p>
		</div>
		<div id='FormDiv' style='float: left; width: 30%;'>
";
// Dump the Form table into the page
echo $kf->get();
echo "
		</div>
		<div id='picsDiv' style='float: left; width: 70%'>
";
if ($record['numPics']>0) {
	echo "<div class='gallery'>";
	foreach ($record['pics'] as $pic) {
		echo "
			<div style='float: left;' class='pic'>
				<img src='i/".$pic['filename']."?salt=".p("a")."' width=300 onClick='if ($(this).attr(\"width\")==300) { $(this).attr(\"width\",\"100%\"); } else { $(this).attr(\"width\",\"300\"); } '/><br />
				Twist <a href='record.php?a=twist&recordID=".$recordID."&picID=".$pic['picID']."'>&orarr;</a> | <a href='record.php?a=twistagain&recordID=".$recordID."&picID=".$pic['picID']."'>&orarr;&orarr;</a> | <a href='#s' onClick='if (confirm(\"REALLY?\")) document.location.href=\"record.php?a=remove&recordID=".$recordID."&picID=".$pic['picID']."\";'>Remove</a>
			</div>
		";
	}
	echo "</div>";
}
echo "
		</div>
";
?>
	</body>
</html>
