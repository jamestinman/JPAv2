// jpaStats.js - stat grabbing engine for JPA player.

var key='8c979e3f83c3c93068f7470a9ae6c78b';

var boxes=null;

function getBoxes(callback) {
	var data={'key':key,'action':'getBoxes'};
	$.ajax({url:"/ajax/jpAjax.php",'data':data,'success':function(result){
	    callback($.parseJSON(result));
	  }});
}

function gotBoxes(data) {
	boxes=data.boxes;
	var html='';
	var shade=false;
	for (i in boxes) {
		var thisBox=boxes[i];
		var boxID=thisBox.boxID;
		var boxTracks=data.tracks[boxID];
		html+='<div class="box" id="boxID'+boxID+'">';
		html+='<a href="'+thisBox.url+'"><h2>'+thisBox.title+'</h2></a>';
		html+='<h3>Released on '+thisBox.releaseDate+'</h3>';
		html+="<p onclick='$(\"#boxID"+boxID+" .tracks\").toggleClass(\"hiding\");''>HIDE / SHOW</p>";
		
		html+='<div class="tracks">';
		for (j in boxTracks) {
			html+='<div class="track '+((shade)?'shaded':'')+'" id="boxTrack'+boxTracks[j].trackID+'">';
			html+='<p class="artistName">'+boxTracks[j].artist+'</p>';
			html+='<p class="trackName">'+boxTracks[j].title+'</p>';
			html+='<div class="playBar" id="playBarB'+boxID+'T'+boxTracks[j].trackID+'"></div>';
			html+='</div>';
			shade=!shade;
		}
		html+='</div> <!-- end of .tracks -->';
		html+='</div> <!-- end of box -->';
	}
	$('#content').html(html);
	getStats(gotStats);
	var timer = setInterval(function() {
		getStats(gotStats);
	},5000);
	
}

function getStats(callback) {
	for (i in boxes) {
		var boxID=boxes[i].boxID;
		var data={'key':key,'action':'showPlays','boxID':boxID};
		$.ajax({url:"/ajax/jpAjax.php",'data':data,'success':function(result){
		    callback($.parseJSON(result));
		}});	
	}
}

// separate callback for each box
function gotStats(data) {
	var theseStats=data.data;
	var highest=0;
	for (p in theseStats) {
		if (parseInt(theseStats[p].plays)>parseInt(highest)) {
			highest=theseStats[p].plays;
		}
	}
	for (p in theseStats) {
		var thisTrack=theseStats[p];
		var unFin=thisTrack.plays-thisTrack.finPlays;
		var unFinWidth=(unFin/highest)*100;

		var fin=thisTrack.finPlays;
		var finWidth=(fin/highest)*100;

		var html='';
		if (fin>0) {
			html+='<div class="finPlays" style="width:'+finWidth+'%;"><p class="stats">'+fin+'</p></div>';	
		}
		if (unFin>0) {
			html+='<div class="unFinPlays" style="width:'+unFinWidth+'%;"><p class="stats">'+unFin+'</p></div>';	
		}
		
		$('#playBarB'+data.boxID+'T'+p).html(html);
	}
}

$().ready(function() {
	getBoxes(gotBoxes);
})