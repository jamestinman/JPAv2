<?php
include_once('archiver.php');
if (buttonPressed("Save Chunk Sizes")) {
	$count=0;
	foreach ($DB->GetAll("SELECT * FROM chunks") as $chunk) {
		if (p("chunkID".$chunk['chunkID']."chunkSize")!==false) {
			$DB->doUpdate("chunks",['chunkSize'=>np("chunkID".$chunk['chunkID']."chunkSize")],$chunk['chunkID']);
			$count++;
		}
	}
	echo "<h3>Updated ".$count." chunkSizes OK</h3>";
}
$topOfThePops=np("topOfThePops",20);
$topFormat = p("topFormat");

// CHUNK TOTALS
$totals=[];
$c="
	<form action='sums.php' method='POST'>
		<table>
";
$c.="<tr><th>Chunk</th><th>Chunk size</th><th>Chunk Size<br />(inc. dupes, excl. dbls)</th><th>Photographed</th><th>Have data</th><th>Have Valuation</th><th>Total of valued</th><th>Avg record</th><th>Net Total (ex.dupes)</th><th>Dupe total</th><th>Predicted Total (inc. dupes)</th></tr>";
$started=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(1) FROM records GROUP BY chunkRef");
$haveData=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(1) FROM records WHERE artist IS NOT NULL GROUP BY chunkRef");
$haveValue=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(numCopies) FROM records WHERE price>0 GROUP BY chunkRef");
$prices=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, SUM(price*numCopies) FROM records WHERE price>0 GROUP BY chunkRef");
$dupes=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, SUM(price*(numCopies-1)) FROM records WHERE price>0 AND numCopies>1 GROUP BY chunkRef");
$promos=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(1) FROM records WHERE promo=1 GROUP BY chunkRef");
$annotations=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(1) FROM records WHERE annotations=1 GROUP BY chunkRef");
$notes=$DB->GetArr("SELECT CONCAT(iA,iB) chunkRef, COUNT(1) FROM records WHERE hasNote IS NOT NULL GROUP BY chunkRef");
foreach ($DB->GetAll("SELECT * FROM chunks") as $chunk) {
	$iA=$chunk['iA'];
	if (!isset($totals[$iA])) {
		$totals[$iA]=['numChunks'=>0, 'numRecords'=>0, 'chunksStarted'=>0, 'havePics'=>0, 'haveData'=>0, 'haveValue'=>0,'totalValue'=>0,'maxValue'=>0,'maxChunk'=>false,'predicted'=>0,'dupes'=>0,'promos'=>0,'annotations'=>0,'notes'=>0];
	}
	$totals[$iA]['numChunks']++;
	$chunkRef=$chunk['iA'].$chunk['iB'];
	$startedInChunk=getIfSet($started,$chunkRef,0);
	if ($startedInChunk) $totals[$iA]['chunksStarted']++;
	$dataCount=getIfSet($haveData,$chunkRef,0);
	$valueTotal=$predictedValue=0;
	$valueCount=getIfSet($haveValue,$chunkRef,0);
	$valueTotal=getIfSet($prices,$chunkRef,0);
	$dupeTotal=getIfSet($dupes,$chunkRef,0);
	$promosTotal=getIfSet($promos,$chunkRef,0);
	$annotationsTotal=getIfSet($annotations,$chunkRef,0);
	$notesTotal=getIfSet($notes,$chunkRef,0);
	$chunkSizeIncDupes=($chunk['chunkSize']-$startedInChunk)+$DB->GetOne("SELECT SUM(numCopies) FROM records WHERE iA=".fss($chunk['iA'])." AND iB=".fss($chunk['iB'])." AND partOfPrevious=0");
	if ($valueCount>0) {
		$avgValue=($valueTotal/$valueCount);
		$predictedTotal=$avgValue*$chunkSizeIncDupes;
	} else {
		$avgValue=0;
		$predictedTotal=0;
	}
	$netTotal=$predictedTotal-$dupeTotal;
	$totals[$iA]['numRecords']+=$chunkSizeIncDupes;
	$totals[$iA]['promos']+=$promosTotal;
	$totals[$iA]['annotations']+=$annotationsTotal;
	$totals[$iA]['notes']+=$notesTotal;
	$totals[$iA]['havePics']+=$startedInChunk;
	$totals[$iA]['haveData']+=$dataCount;
	$totals[$iA]['haveValue']+=$valueCount;
	$totals[$iA]['totalValue']+=$valueTotal;
	$totals[$iA]['predicted']+=$predictedTotal;
	$totals[$iA]['dupes']+=$dupeTotal;
	if ($predictedTotal>$totals[$iA]['maxValue']) {
		$totals[$iA]['maxValue']=$predictedTotal;
		$totals[$iA]['maxChunk']=$chunkRef;
	}
	$c.="
		<tr>
			<td>".$chunkRef."</td>
			<td><input name='chunkID".$chunk['chunkID']."chunkSize' value='".$chunk['chunkSize']."' size=4 /></td>
			<td>".$chunkSizeIncDupes."</td>
			<td>".$startedInChunk." <i>(".perc($startedInChunk,$chunkSizeIncDupes)."%)</i></td>
			<td>".$dataCount." <i>(".perc($dataCount,$chunkSizeIncDupes)."%)</i></td>
			<td>".$valueCount." <i>(".perc($valueCount,$chunkSizeIncDupes)."%)</i></td>
			<td>&pound;".formatMoney(round($valueTotal))."</td>
			<td>&pound;".formatMoney(round($avgValue))."</td>
			<td>&pound;".formatMoney(round($netTotal))."</td>
			<td>&pound;".formatMoney(round($dupeTotal))."</td>
			<td>&pound;".formatMoney(round($predictedTotal))."</td>
		</tr>
	";
}
$c.="
		</table>
		<input type='submit' name='submitBtn' value='Save Chunk Sizes' />
	</form>
";

// HIGH LEVEL STATS
$s="
		<table class='layout'>
			<tr><th>Area</th><th>Predicted total # records</th><th>Promo</th><th>Annotated</th><th>Contain Note</th><th># photographed</th><th># with data</th><th># valued</th><th>Total of valued</th><th>Most valuable chunk</th><th>Predicted (ex.dupes)</th><th>Dupe Total</th><th>Predicted Total</th></tr>
";
$overallStats=[];
foreach ($totals as $iA=>$stats) {
	if ($iA=='LP') {
		$stats['total']=$stats['predicted'];
	} else {
		// Use a straight average for non-LPs
		$stats['total']=(($stats['totalValue'])/($stats['haveValue']))*$stats['numRecords'];
	}
	$totals[$iA]['total']=$stats['total']; // 'Save' back the grand total
	$s.="
			<tr>
				<td><b>".getIfSet($iAoptions,$iA,$iA)."</b></td>
				<td>".formatNum($stats['numRecords'])."</td>
				<td>".perc($stats['promos'],$stats['haveData'])."%</td>
				<td>".perc($stats['annotations'],$stats['haveData'])."%</td>
				<td>".perc($stats['notes'],$stats['haveData'])."%</td>
				<td>".formatNum($stats['havePics'])."</td>
				<td>".formatNum($stats['haveData'])."</td>
				<td>".formatNum($stats['haveValue'])."</td>
				<td>&pound;".formatMoney(round($stats['totalValue']))."</td>
				<td>".$stats['maxChunk']." &pound;".formatMoney(round($stats['maxValue']))."</td>
				<td>&pound;".formatMoney(round($stats['total']-$stats['dupes']))."</td>
				<td>&pound;".formatMoney(round($stats['dupes']))."</td>
				<td>&pound;".formatMoney(round($stats['total']))."</td>
			</tr>
	";
	foreach ($stats as $var=>$val) {
		if ($var=="maxChunk" || $var=="maxValue") {
			if ($stats['maxValue']>getIfSet($overallStats,'maxValue',0)) {
				$overallStats['maxChunk']=$stats['maxChunk'];
				$overallStats['maxValue']=$stats['maxValue'];
			}
		} else {
			// Sum the chunk stats
			$overallStats[$var]=getIfSet($overallStats,$var,0)+$val;
		}
	}
}

// OVERALL TOTALS
$s.="
		<tr>
			<td class='overall'><b>OVERALL</b></td>
			<td class='overall'>".formatNum($overallStats['numRecords'])."</td>
			<td class='overall'>".perc($overallStats['promos'],$overallStats['haveData'])."%</td>
			<td class='overall'>".perc($overallStats['annotations'],$overallStats['haveData'])."%</td>
			<td class='overall'>".perc($overallStats['notes'],$overallStats['haveData'])."%</td>
			<td class='overall'>".formatNum($overallStats['havePics'])." <i>(".perc($overallStats['havePics'],$overallStats['numRecords'])."%)</i></td>
			<td class='overall'>".formatNum($overallStats['haveData'])." <i>(".perc($overallStats['haveData'],$overallStats['numRecords'])."%)</i></td>
			<td class='overall'>".formatNum($overallStats['haveValue'])." <i>(".perc($overallStats['haveValue'],$overallStats['numRecords'])."%)</i></td>
			<td class='overall'>&pound;".formatMoney(round($overallStats['totalValue']))."</td>
			<td class='overall'>".$overallStats['maxChunk']." &pound;".formatMoney(round($overallStats['maxValue']))."</td>
			<td class='overall'>&pound;".formatMoney(round($overallStats['total']-$overallStats['dupes']))."</td>
			<td class='overall'>&pound;".formatMoney(round($overallStats['dupes']))."</td>
			<td class='overall'>&pound;".formatMoney(round($overallStats['total']))."</td>
		</tr>
	</table>
";
// Keep a history of the overall totals
$DB->execute("DELETE FROM totalHistory WHERE dateComputed=DATE(NOW())");
foreach ($overallStats as $var=>$val) {
	$DB->doInsert("totalHistory",['dateComputed'=>sqlNow(), 'statName'=>$var,'statValue'=>$val]);
}

/*
// Total up the 7s & 12s as one chunk:
$avg7sand12s="";
$altTotal7s=(($totals['7']['totalValue'])/($totals['7']['haveValue']))*$totals['7']['numRecords'];
$altTotal7sNoSpecials=(($totals['7']['totalValue']-$prices['7Sp'])/($totals['7']['haveValue']-$haveValue['7Sp']))*$totals['7']['numRecords'];
$altTotal12s=($totals['12']['totalValue']/$totals['12']['haveValue'])*$totals['12']['numRecords'];
$altTotal12sNoSpecials=(($totals['12']['totalValue']-$prices['12Sp'])/($totals['12']['haveValue']-$haveValue['12Sp']))*$totals['12']['numRecords'];
$altPredicted=$altTotal7s+$altTotal12s+(($overallStats['predicted']-$totals['7']['predicted'])-$totals['12']['predicted']);

$avg7sand12s="
	<table>
		<tr><td class='goLeft'>Chunked 7s total:</td><td>&pound;".formatMoney(round($totals['7']['predicted']))."</td></tr>
    <tr><td class='goLeft'>Chunked 12s total:</td><td>&pound;".formatMoney(round($totals['12']['predicted']))."</td></tr>
    <tr><td class='goLeft'>7s as straight average:</td><td>&pound;".formatMoney(round($altTotal7s))."</td></tr>
    <tr><td class='goLeft'>7s as straight average (excluding specials):</td><td>&pound;".formatMoney(round($altTotal7sNoSpecials))."</td></tr>
    <tr><td class='goLeft'>12s as straight average:</td><td>&pound;".formatMoney(round($altTotal12s))."</td></tr>
    <tr><td class='goLeft'>12s as straight average (excluding specials):</td><td>&pound;".formatMoney(round($altTotal12sNoSpecials))."</td></tr>
    <tr><td class='goLeft'>Alt Total:</td><td>Between &pound;".formatMoney(round($altPredicted-($altTotal7s-$altTotal7sNoSpecials)-($altTotal12s-$altTotal12sNoSpecials)))." and &pound;".formatMoney(round($altPredicted))."</td></tr>
	</table>
";
*/

// Misc stats
$misc="
	<table class='goLeft'>
		<tr>
		</tr>
	</table>
";

// TOP 20
$top20="
	<table class='goLeft'>
";
$i=0;
$sql = "SELECT * FROM records";
if ($topFormat=="OTHER") {
  $sql.= " WHERE format NOT IN ('7\"','LP')";
} else if ($topFormat!="ALL") {
  $sql.= " WHERE format=".fss($topFormat);
}
$sql.=" ORDER BY price DESC LIMIT ".$topOfThePops;
foreach ($DB->GetAll($sql) as $record) {
	$i++;
	$record=getRecordInfo($record);
	if (isset($record['pics'][0])) {
		$thumbFilename="i/th_".$record['pics'][0]['filename'];
		$thumb=(file_exists($thumbFilename))?"<img src='".$thumbFilename."' width=250 />":false;
	}
	$top20.="
		<tr>
			<td>".$i."</td>
			<td>".$thumb."</td>
			<td><a href='record.php?recordID=".$record['recordID']."'>".getRef($record['iA'],$record['iB'],$record['iC'])."</a></td>
			<td>".$record['format']."</td>
			<td>".$record['artist']."</td>
			<td>".$record['title']."</td>
			<td>".$record['cond']."</td>
			<td><b>&pound;".formatMoney($record['price'])."</b></td>
			<td>".$record['recordYear']."</td>
			<td>".$record['hasNote']."</td>
			<td>".$record['hasInsert']."</td>
      <td>".$record['comment']."</td>
		</tr>
	";
}
$top20.="
	</table>
";

$bands=[
  [1,5],
  [5,10],
  [10,20],
  [20,50],
  [50,100],
  [100,200],
  [200,500],
  [500,1000],
  [1000,2000],
  [2000,999999]
];

$valBands="
	<table class='goLeft'>
		<tr>
			<th>&nbsp;</th>
			<th># records</th>
			<th>% of those with value</th>
			<th>Total value</th>
		</tr>
";
$i=0;
foreach ($bands as $band) {
	$min=$band[0];
	$max=$band[1];
	$stats=$DB->GetRow("SELECT SUM(price), COUNT(1) FROM records WHERE price BETWEEN ".$min." AND ".$max,2);
	if (!$stats || !isset($stats[1])) $stats=[0,0];
	$perc=100*($stats[1]/$overallStats['haveValue']);
	$valBands.="
		<tr>
			<td>&pound;".$min."-&pound;".$max."</td>
			<td>".$stats[1]."</td>
			<td>".round($perc,1)."%</td>
			<td>&pound;".formatMoney($stats[0])."</td>
		</tr>
	";
}
$valBands.="
	</table>
";

$overallStats['haveCountry']=$DB->GetOne("SELECT COUNT(1) FROM records WHERE price>0 AND country IS NOT NULL");
$countries="
	<table class='goLeft'>
		<tr>
			<th>Country</th>
			<th># records</th>
			<th>% of those with country</th>
			<th>Total value</th>
		</tr>
";
foreach ($DB->GetAll("SELECT country,SUM(price),COUNT(1) FROM records WHERE price>0 AND country IS NOT NULL GROUP BY country HAVING COUNT(1)>2 ORDER BY country",2) as $stats) {
	$perc=100*($stats[2]/$overallStats['haveCountry']);
	$countries.="
		<tr>
			<td>".$stats[0]."</td>
			<td>".$stats[2]."</td>
			<td>".round($perc,1)."%</td>
			<td>&pound;".formatMoney($stats[1])."</td>
		</tr>
	";
}
$countries.="
	</table>
";

$formatOptions = ['ALL'=>"ALL", 'OTHER'=>"OTHER"];
foreach ($DB->GetAll("SELECT format, COUNT(1) cnt FROM records GROUP BY format ORDER BY format") as $f) {
  $format = $f['format'];
  if ($format && $f['cnt'] && in($format,'LP,7",12')) {
    $formatOptions[$format] = $format ." (".$f['cnt'].")";
  }
}
?>
<html>
	<head>
		<title>ARCHIVER</title>
		<script src="jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
	</head>

	<body>
		<div id='website'>
      <?php if (!$topFormat) {?>
			<div class='section colour'>
				<h1>ARCHIVER TOTALISER - <?=$env?></h1>
				<p><a href='index.php'>back to ARCHIVER home</a></p>
			</div>
			<h2>Overview</h2>

			<?=$s?>

<!--
			<h3>Alt.Valuation (averaging 7s and 12s so far entered)</h3>
			<?=$avg7sand12s?>
-->
			<?=$misc?>

			<form action='sums.php'>
				<h2>Valuation Bands</h2>
				<?=$valBands?>
			</form>

			<form action='sums.php'>
				<h2>Countries (of those valued)</h2>
				<?=$countries?>
			</form>
      <?php } ?>
			<form action='sums.php'>
				<h2>
          Top
          <?=getSelect(['name'=>"topOfThePops", 'options'=>[10=>10,20=>20,30=>30,40=>40,50=>50,60=>60,100=>100,200=>200,500=>500], 'selected'=>$topOfThePops, 'autoSubmit'=>true]);?></select>
          <?=getSelect(['name'=>"topFormat", 'options'=>$formatOptions, 'selected'=>$topFormat, 'autoSubmit'=>true]);?></select>
        </h2>
			</form>
			<?=$top20?>

      <?php if (!$topFormat) {?>
			<h2>Chunks (in detail)</h2>

			<?=$c?>
      <?php } ?>

		</div>
	</body>
</html>
