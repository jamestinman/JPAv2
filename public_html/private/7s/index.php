<?php
include_once('../../archiver/archiver.php');
stopSession();
$chunks = $DB->GetAll("SELECT * FROM chunks WHERE iA='7'");
$max = $DB->GetOne("SELECT SUM(chunkSize) FROM chunks WHERE iA='7'");
$total = $DB->GetOne("SELECT COUNT(1) FROM records WHERE iA='7'");

?>
<html>
	<head>
		<title>JPA Private</title>
		<script src="/js/libs/jquery-1.7.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
	</head>

	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>Private Area</h1>
				<h2>7" collection (approx <?php echo round(($total/$max)*100,1); ?>% archived)</h2>
        <div id='listCol'>
          <div id='iBlist'>
<?php
foreach ($chunks as $chunk) {
  echo " <a href='#".$chunk['iB']."'>".$chunk['iB']."</a> &nbsp;";
}
?>
            <span id='info' class='loading'>...loading...</span>
          </div>
          <table id='bigTable'>
<?php
foreach ($chunks as $chunk) {
  echo "<tr><td colspan=7 class='iBheader'><a id='".$chunk['iB']."'>".$chunk['iB']."</a></td></tr>";
  echo "
            <tr>
              <th>Ref</th>
              <th>Pic</th>
              <th>Artist</th>
              <th>Title</th>
              <th>Year</th>
              <th>Cat No</th>
              <th>Extra</th>
            </tr>
  ";
	foreach (getRecordsFromSQL("SELECT * FROM records WHERE iA=".fss($chunk['iA'])." AND iB=".fss($chunk['iB'])) as $rs) {
		displayRecord($rs);
	}
}
?>
          </table>
    		</div>
  		</div>
	</body>
  <script>
    $('#info').removeClass('loading').addClass('loaded').html('Ok');
  </script>
</html>

<?php

$totalCount=$totalPrice=0;

function displayRecord($rs) {
  global $DB, $inTheFamily, $totalCount, $totalPrice, $finalRecordID, $showValuations;
	$ref=$rs['ref'];
	// if ($ref=="LP01144") test($rs,2);
	for ($iD=1; $iD<=9; $iD++) {
		if ($rs['iD']==$iD) {
			$record=$rs; // The "root" record, should be iD=1
		} else if (isset($rs['versions'][$iD])) {
			$record=$rs['versions'][$iD];
		} else {
			$record=false; // This iD doesn't exist
		}
		if ($record) {
			$rowClass=$small=false;
			$recordID=getIfSet($record,'recordID',0);
			$disp=$ref;
			// if (getIfSet($record,'vaRef')) $disp.="<br /><span class='small'>VA:".$record['vaRef']."</span>";
			if (getIfSet($record,'iD')>1) {
				$disp="&star; alt version of ".$disp;
				$small=true;
			}
			if (getIfSet($record,'partOfPrevious')) {
				$disp="&rdsh;".$disp;
				$rowClass='grey';
				$small=true;
			}
			$hasPics=$hasInfo=$needsAttention=$attentionGiven=$hasPrice=false;
			if ($recordID>0) {
        $finalRecordID = $recordID;
				$hasPics=($record['numPics']>0);
				$hasInfo=notnull($record['artist']);
				$hasPrice=($record['price']>0);
				$needsAttention=notnull($record['attention']);
				$attentionGiven=notnull($record['attentionGiven']);
			}
			$thumb=false;
			if ($recordID>0) {
				// $disp="<a href='record.php?recordID=".$recordID."'>".$disp."</a>";
				if (isset($record['pics'][0]) && $record['pics'][0]['filename']) {
					$thumbFilename="/archiver/i/th_".$record['pics'][0]['filename'];
          $thumb="<img src='".$thumbFilename."' width=100 />";
				}
      }
			// Colour it in...
      /*
			if (!$rowClass) {
				if ($needsAttention && !$attentionGiven) {
					$rowClass='red';
        } else if ($hasInfo) {
					$rowClass="yellow";
				} else if ($hasPics) {
					$rowClass="orange";
        } else if ($hasPrice) {
          $rowClass="green";
        }
			}
      */

      $rowID = 'rec'.getIfSet($record,'recordID');
      echo "<tr id='".$rowID."' class='record ".$rowClass.(($small)?" small":"")."'>";
      echo "<td>".$disp."</td>";
      // Do we have this record?
      echo "<td>".$thumb."</td>";
      echo "<td>".getIfSet($record,'artist')."</td>";
      echo "<td>".getIfSet($record,'title')."</td>";
      echo "<td>".getIfSet($record,'recordYear')."</td>";
      echo "<td>".getIfSet($record,'catNo')."</td>";
      echo "<td>".getIfSet($record,'hasNote')."</td>";
      echo "</tr>";
		}
	}
}
?>
