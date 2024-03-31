<?php
include_once('archiver.php');
$finalRecordID = 0;
$showValuations = getIfSet($_SESSION,'showValuations',0);
// Chunk creator
if (p("action")=="createChunks") {
	foreach ($iAoptions as $iA3=>$title) {
		foreach (explode(',',$iBlookup[$iA3]) as $iB3) {
			$chunkID=$DB->doInsert("chunks",['iA'=>$iA3, 'iB'=>$iB3]);
		}
	}
	trace("<p><b>Chunks created!</b></p>");
}

// JSON loader
/*
if (p("action")=="loadExisting") {
	$count=0;
	$filename=dirname(__FILE__)."/js/cleanedAlbums.json";
	$json=file_get_contents($filename);
	$albums=json_decode($json,true);
	$filename=dirname(__FILE__)."/js/cleanedSingles.json";
	$json=file_get_contents($filename);
	$singles=json_decode($json,true);
	$type=p("type","albums");
	$loopWhat=($type=="albums")?$albums:$singles;
	$env=p("env","dev");
	if ($env=="dev") {
		$jpaRootDir="/Users/james/git/JPA/public_html/";
		$archiverRootDir="/Users/james/Sites/archiver/";
	} else {
		$jpaRootDir="/var/www/johnpeelarchive.com/public_html/";
		$archiverRootDir="/var/www/klikhome.co.uk/public_html/archiver/";
	}
	foreach ($loopWhat as $jpaID=>$theRecord) {
		// if (++$count==100) die;
		$card=lpad($theRecord['card'],5,'0');
		$letter=substr($jpaID,0,1);
		echo "<p>Processing [".$letter."] ".$jpaID." ".$theRecord['numImages']." x pics</p>";
		$d=[
			'iA'=>($type=="albums")?"LP":"7",
			'iB'=>substr($card,0,2),
			'iC'=>substr($card,2,3),
			'artist'=>getIfSet($theRecord,'artist'),
			'title'=>getIfSet($theRecord,'album')			
		];
		// Do we have an existing?
		$record=getRecord($d['iA'],$d['iB'],$d['iC']);
		if ($record['recordID']>0) {
			echo "<p>Record ".$card." already loaded as ".$record['recordID']."</p>";
			$recordID=$DB->doUpdate('records',$d,$record['recordID']);
		} else {
			$d['format']=map($iA,['LP'=>'LP','7'=>'7"'],false);
			$d['photoTakenByUserID']=$_SESSION['userID'];
			$d['photoTakenDate']=sqlNowTime();
			$recordID=$DB->doInsert('records',$d);
			// Upload existing pics
			$uploadCount=0;
			for ($n=1; $n<=$theRecord['numImages']; $n++) {
				$originalFilename=$jpaRootDir.$type."/img/".$letter."/".$jpaID.(($type=="albums")?"-".$card:"")."-".$n.".jpg";
				// Do we have a file in this slot?
				if ($originalFilename) {
					$ext=getExt($originalFilename);
					$picID=$DB->doInsert('pics',['recordID'=>$recordID, 'userID'=>$_SESSION['userID']]);
					$coreFilename=getRef($d['iA'],$d['iB'],$d['iC']).'picID'.$picID.".".$ext;
					$newFilename=$archiverRootDir.'i/'.$coreFilename;
					echo "<p>Copying pic: ".$originalFilename." to ".$newFilename."</p>";
					copy($originalFilename,$newFilename);
					$DB->doUpdate('pics',['filename'=>$coreFilename],$picID);
					$uploadCount++;
				}
			}
		}

	}
}
*/
?>
<html>
	<head>
		<title>ARCHIVER</title>
		<script src="jquery-1.11.2.min.js"></script>
		<link rel="stylesheet" href="/css/private.css">
	</head>

	<body>
		<div id='website'>
			<div class='section colour'>
				<h1>ARCHIVER - <?=$env?></h1>
				<div style='float: left; width: 500px;'>
					<h2 id='msg' class='msg' style='display:none;'><?=$msg?></h2>
					<p>
						<a href='mobile/index.php'>Switch to Mobile version</a>
<?php
if ($inTheFamily) {
	echo " | <a href='stats.php'>Score Board</a> | <a href='sums.php'>Totaliser</a>";
	if ($env=="localhost") {
		$syncInfo=getSyncInfo();
		echo " | <a href='sync.php'>Sync Control</a>";
		echo $syncInfo['changesText'];
	}
}
?>
					</p>
					<div class='section darker'>
						Logged in as: <b><?=$DB->GetOne("SELECT username FROM users WHERE userID=".$_SESSION['userID']); ?></b> <a href='login.php?clearSession=1'>Logout</a>
					</div>
				</div>
			</div>
			<div style='float: left; width: 500px;'>
				<form action='index.php' method='GET'>
					<p>
						Search: <input type='text' name='search' id='search' value='<?php echo p("search"); ?>' />
						<input type='submit' id='submitBtn' name='submitBtn' value='Search' />
						<?php if (p("search")) { echo "<input type='submit' id='submitBtn' name='submitBtn' value='Clear' onClick='$(\"#search\").val(\"\");' />"; } ?>
					</p>
			</div>
			<div style='clear:both'></div>
			<div id='leftCol'>
				<ul>
				<?php
				echo "<li><a href='index.php?attention=1'>Requiring Attention...</a> (".$DB->GetOne("SELECT COUNT(1) FROM records WHERE attention IS NOT NULL AND attentionGiven IS NULL").")</li>";
				foreach ($iAoptions as $iA2=>$title) {
					echo "<li".(($iA2==$_SESSION['iA'])?" class='selected'":"")."><a href='index.php?iA=".$iA2."&iB=clear'>".$title."</a> (".getIfSet($iAcounts,$iA2,0).")</li>\n";
				}
				echo "<li".((p("VA"))?" class='selected'":"")."><a href='index.php?VA=1'>V &amp; A loan</a></li>";
				?>
				</ul>
				<p><i class='small'>Note: the above totals include alternative versions &amp; duplicates</i></p>
			</div>
<?php
echo "<div id='listCol'>";
if (!p("search") && !p("VA")) {
	echo "
				<form action='index.php' method='post' name='archivalForm' enctype='multipart/form-data'>
          <input type='hidden' name='change' value='1' />
					&nbsp;&nbsp;Chunk:".getSelect(['name'=>'iB','options'=>$iBoptions,'selected'=>$iB,'autoSubmit'=>true])."
					&nbsp;&nbsp;&nbsp;&nbsp;
          ".(($inTheFamily)?"<input type='checkbox' value=1 name='showValuations' onClick='this.form.submit();' ".(($showValuations)?" checked":"")." /> Show valuations":"")."
					&nbsp;&nbsp;&nbsp;&nbsp;
          <span id='loading'>...loading...</span>
          <a id='quickAddLink' class='hidden' href='#' onClick='quickAdd(); return false;'>Quick add</a>
  ";
  /*
	$key=[
		'red'=>"Requires attention",
		'orange'=>"Missing info",
		'yellow'=>"Ready for valuation",
		'green'=>"Finished",
	];
	foreach ($key as $class=>$title) {
		echo "<span class='key ".$class."'><input type='radio' name='key' id='keyFor".$class."' onClick='$(\".record\").hide(300); $(\".".$class."\").show(300);' /> ".$title."</span>";
	}
	echo "<span class='key'><input type='radio' name='key' id='keyForAll' onClick='$(\".record\").show(300);' /> All</span>";
  */
	echo "
    </form>
  ";
}
echo "
	<table id='bigTable'>
		<tr>
			<th>Ref</th>
			<th>Pic</th>
			<th>Artist</th>
			<th>Title</th>
			<th>Year</th>
			<th>Cat No</th>
      <!--
			<th>Promo copy</th>
			<th>Annotations</th>
      -->
      <th>Extra</th>
			".(($showValuations)?"<th class='valuation'>Valuation</th>":"")."
    </tr>
  ";

if (p("attention")) {
	foreach (getRecordsFromSQL("SELECT * FROM records WHERE attention IS NOT NULL ORDER BY recordID DESC") as $rs) {
		displayRecord($rs);
	}
} else if (p("VA")) {
	foreach (getRecordsFromSQL("SELECT * FROM records WHERE va=1 ORDER BY vaRef,recordID DESC") as $rs) {
		displayRecord($rs);
	}
} else if (p("search")) {
	$search=p("search");
	foreach (getRecordsFromSQL("SELECT * FROM records WHERE artist LIKE '%".$search."%' OR title LIKE '%".$search."%'") as $rs) {
		displayRecord($rs);
	}
	
} else {
	// Loop over chunks
	foreach (explode(',',$iBlookup[$iA]) as $iB2) {
		if ($iB2==$iB) {
			// Load this chunk
			$records=getRecords($_SESSION['iA'],$iB);
			$chunk=$DB->GetRow("SELECT * FROM chunks WHERE iA=".fss($_SESSION['iA'])." AND iB=".fss($iB));
			$chunkSize=getIfSet($chunk,'chunkSize',999);
			// Loop over numbers within this chunk
			for ($iC=0; $iC<=$chunkSize; $iC++) {
				$ref=getRef($iA,$iB,$iC);
				$rs=getIfSet($records['records'],$iC);
				displayRecord($rs);
			}
		}
	}
}
displayTotals();
echo "
			</table>
		</div>
";
?>
		</div>
		<!--
		<script src="js/albums.json"></script>
		<script src="js/singles.json"></script>
		-->
		<script>
      var finalRecordID = <?php echo $finalRecordID; ?>;
      if ($('#msg').html()) $('#msg').show(300).delay(3000).hide(300);
      $('#loading').hide();      
      $('#quickAddLink').show();      

      // Quick Add 
      function quickAdd() {
        $('body').animate({
          scrollTop: $("#rec"+finalRecordID).offset().top
        }, 400);
      }

      $("input","#bigTable").on('focus', function() {
        $(this).parents('tr').addClass('edited').removeClass('saved');
      });

      $("input[name='artist']").on('focus',function() {
        var clickedid = $(this).parents('tr').attr('id');
        console.log('Clicked:',clickedid);
        // Save any unsaved previous line(s)
        $('.edited').each(function() {
          var id = $(this).attr('id');
          if (clickedid == id) return false; // Don't update freshly clicked!
          record = {
            id: id,
            artist: $("input[name='artist']",this).val(),
            title: $("input[name='title']",this).val(),
            recordYear: $("input[name='recordYear']",this).val(),
            catNo: $("input[name='catNo']",this).val(),
            hasNote: $("input[name='hasNote']",this).val()
            // price: $("input[name='price']",this),val();
          }
          if (record.artist.length > 0) {
            console.log('Saving:',record);
            data = {
              action: 'saveRecord',
              record: record
            }
            fetch('/archiver/ajax.php', {
              method: 'POST', // or 'PUT'
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify(data),
            })
            // .then(response => response.text())
            .then(response => response.json())
            .then(dat => {
              if (dat.rc) {
                console.log('API error:',dat);
                alert(dat.msg);
              } else {
                console.log('Record saved OK');
                $('#'+dat.id).removeClass('edited').addClass('saved');
                // Replace the link
                $('td:first','#'+dat.id).html("<a href='record.php?recordID="+dat.recordID+"'>"+dat.ref+"</a>");
              }
            })
            .catch((error) => {
              console.error('Fetch error:', error);
            });
          }
        });
      });

			// JSON Cleaning
			/*
			// Passes the contents of albums.json and singles.json to the server to create cleanedAlbums.json & cleanedSingles.json
			$().ready(function() {
				cleanJson(existingAlbums,'js/cleanedAlbums.json');
				cleanJson(existingSingles,'js/cleanedSingles.json');
			});
			var errorFn=function(jqXHR, textStatus) {
				alert('Error:'+textStatus);
				console.log(jqXHR);
			}
			function cleanJson(j,f) {
				$.ajax({
					'type': "POST",
					'url': "ajax.php",
					'data': {'action':"cleanJson",'f':f,'data':j},
					'error': errorFn,
					'xhrFields': { withCredentials: true },
					'success': function(dat) { console.log(dat) }
				});
			}
			*/
			</script>
	</body>
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
			if (getIfSet($record,'vaRef')) $disp.="<br /><span class='small'>VA:".$record['vaRef']."</span>";
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
				$disp="<a href='record.php?recordID=".$recordID."'>".$disp."</a>";
				if (isset($record['pics'][0]) && $record['pics'][0]['filename']) {
					$thumbFilename="i/th_".$record['pics'][0]['filename'];
					// Does this thumb exist yet?
					if (!file_exists($thumbFilename)) {
						$pics=getPics($recordID);
            if ($pics) {
							resizeImage("i/".$pics[0]['filename'],160,160,"i/th_".$pics[0]['filename']);
						}
					}
					if (file_exists($thumbFilename)) {
						$thumb="<img src='".$thumbFilename."' width=100 />";
					}
				}
      }
			// Colour it in...
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

      if ($recordID) {
        $rowID = 'rec'.getIfSet($record,'recordID');
        echo "<tr id='".$rowID."' class='record ".$rowClass.(($small)?" small":"")."'>";
        echo "<td>".$disp."</td>";
        // Do we have this record?
        echo "<td>".$thumb."</td>";
        echo "<td>".getIfSet($record,'artist')."</td>";
        echo "<td>".getIfSet($record,'title')."</td>";
        echo "<td>".getIfSet($record,'recordYear')."</td>";
        echo "<td>".getIfSet($record,'catNo')."</td>";
        /*
        echo "<td>".((getIfSet($record,'promo'))?"&#10003;":"")."</td>";
        echo "<td>".((getIfSet($record,'annotations'))?"&#10003;":"")."</td>";
        */
        echo "<td>".getIfSet($record,'hasNote')."</td>";
        if ($showValuations) {
          echo "<td>".((getIfSet($record,'numCopies',0)>1)?$record['numCopies']." x ":"")."&pound;".formatMoney(getIfSet($record,'price'))."</td>";
        }
        echo "</tr>";
        $totalPrice+=getIfSet($record,'price');
        $totalCount++;
      } else {
        // Editable row
        $rowID = 'ref'.$rs['ref'];
        echo "<tr id='".$rowID."' class='record new'>";
        echo "<td>".$rs['ref']."</td>";
        // Do we have this record?
        echo "<td class='blank'></td>";
        echo "<td><input name='artist' placeholder='Artist' value='".escHTML(getIfSet($record,'artist'))."' /></td>";
        echo "<td><input name='title' placeholder='Title' value='".escHTML(getIfSet($record,'title'))."' /></td>";
        echo "<td><input name='recordYear' size=4 placeholder='Year' value='".getIfSet($record,'recordYear')."' /></td>";
        echo "<td><input name='catNo' size=7 placeholder='Cat num' value='".getIfSet($record,'catNo')."' /></td>";
        echo "<td><input name='hasNote' placeholder='Extra' value='".getIfSet($record,'hasNote')."' /></td>";
        if ($showValuations) {
          echo "<td>&pound;<input name='price' placeholder='Valuation' size=4 value='".formatMoney(getIfSet($record,'price'))." ' /></td>";
        }
        echo "</tr>";
      }
		}
	}
}

function displayTotals() {
	global $DB, $totalCount, $totalPrice;
	echo "<tr class='record bold'>";
	echo "<td>".$totalCount."</td>";
	// Do we have this record?
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&pound;".formatMoney($totalPrice)."</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
	echo "<td>&nbsp;</td>";
  echo "<td>&nbsp;</td>";
	echo "</tr>";
}
?>
