// JS used to dynamically load recordbox content into page

// generic do-some-Ajax-shit function
function callServer(action, data, callback) {	
	data.action=action;
	$.ajax({url:"/ajax/rbAjax.php",'data':data,'success':function(result){
		callback($.parseJSON(result));
	}});
}

// function has multiple arguments for compatibility
// but we'd rather have a nice array in boxID instead
function changeBox(boxID,jpID,artistID) {
	conLog('changeBox ->');
	if (jpaPlayer.dynamicLoad==true && jpaPlayer.dynamicReady==false) {
		conLog('no box change, one already underway');
		return false; // stop multiple changes being triggered at once
	}
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	
	cleanUp();

	if (typeof boxID === 'object') { // new way
		var params=boxID;
		if (params.boxID) {
			getBoxes(params.boxID);
			return true;
		}
		if (params.recordID) {
			getRecordPage(false,params.recordID);
			return true;
		}
		if (params.artistID) {
			getArtistPage(params.artistID);
			return true;
		}
		if (params.tagID) {
			getTagPage(params.tagID);
			return true;
		}
		if (params.letterID)  {
			getLetterPage(params.letterID);
			return true;
		}
	} else { // old way
		if (boxID!==false) {
			getBoxes(boxID);	
		} else if (jpID!==false) {
			getRecordPage(jpID);
		} else if (artistID!==false) {
			getArtistPage(artistID);
		} else {
			conLog('no page found to change to');
			return false; // nothing found
		}
	}	
}

///////////////////////////////
// content fetching routines...
///////////////////////////////

function getBoxes(boxID) {
	conLog('getBoxes -> '+boxID);
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	callServer('getRecordBox',{'boxID':boxID},gotBoxes);
}

// these could all be combined to be fair...

function getRecordPage(jpID,recordID) {
	conLog('getRecordPage -> ');
	conLog(jpID);
	conLog(recordID);
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	if (jpID) {
		callServer('getRecordPage',{'jpID':jpID},gotPage);	
	} else {
		callServer('getRecordPage',{'recordID':recordID},gotPage);	
	}
	
}

function getArtistPage(artistID) {
	conLog('getArtistPage -> '+artistID);
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	callServer('getArtistPage',{'artistID':artistID},gotPage);
}

function getLetterPage(letterID) {
	conLog('getLetterPage -> '+letterID);
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	callServer('getLetterPage',{'letterID':letterID},gotPage);
}

function getTagPage(tagID) {
	conLog('getTagPage -> '+tagID);
	jpaPlayer.dynamicLoad=true;
	jpaPlayer.dynamicReady=false;
	callServer('getTagPage',{'tagID':tagID},gotPage);
}

/////////////////////////////
// content receiving routines
/////////////////////////////

function gotBoxes(data) {
	conLog('gotBoxes -> '+data.length);
	
	sCount=0; // reset sleeves count
	$('#ajaxContent').stop().css({'opacity':0}); // stop page erasing itself if still doing fade and destroy from getBoxes();
	$('#ajaxContent').append(data.recordBox.header);
	
	$('#ajaxContent').append(data.recordBox.video); // puts in placeholder if no video anyway
	var recordBits=data.recordBox.recordBits;
	
	for (i in recordBits) {
		var thisRecordBit=recordBits[i];
		$('#ajaxContent').append(thisRecordBit.content);
		doResize();
	}
	
	$('#headerimage').css({'opacity':1});
	$('#shareButtons').html(data.recordBox.socialButtons);
	$('#ajaxContent').animate({'opacity':1},1000);

	jpaBoxID=data.recordBox.identifier;
	if (data.recordBox.playlist) {
		playlist=data.recordBox.playlist;
	} else {
		playlist=false;
	}

	updateMetadata(data.recordBox);
	activateJSStuff(); // re-attach various events etc
	
	jpaPlayer.dynamicReady=true;
	initPlaylist(); // sort playlist out on page
	loadMainFilm(); // will sort out main film IF there is one
	
	// 3..2..1..
	var tmpTimeout=setTimeout(function() { start();  },250);
}

// all non-record boxes can be dealt with in one function actually...
function gotPage(data) {
	conLog('gotPage ->' );
	conLog(data);
	$('#ajaxContent').stop().css({'opacity':0}); // stop page erasing itself if still doing fade and destroy from getBoxes();
	$('#ajaxContent').append(data.page.recordBits);
	doResize();
	jpaBoxID=data.page.identifier;
	if (data.page.playlist) {
		playlist=data.page.playlist;
	} else {
		playlist=false;
	}
	activateJSStuff();

	updateMetadata(data.page);
	
	jpaPlayer.dynamicReady=true;
	$('#ajaxContent').animate({'opacity':1},1000);
	initPlaylist();

	var tmpTimeout=setTimeout(function() { start();  },250);
}

// pass this a record box or page etc and it will update social buttons and page data for seo etc
function updateMetadata(pageInfo) {
	history.pushState({}, pageInfo.title, origin+'/'+pageInfo.slug);
	document.title = pageInfo.title;
	$("meta[name=description]").attr("content", pageInfo.description);

	$('#shareButtons').html(pageInfo.socialButtons);
	
	$("meta[property=og\\:title]").attr("content", pageInfo.title);
	$("meta[property=og\\:url]").attr("content", origin+'/'+pageInfo.slug);
	$("meta[property=og\\:image]").attr("content", origin+pageInfo.image);

	$("meta[name=twitter\\:url]").attr("content", origin+'/'+pageInfo.slug);
	$("meta[name=twitter\\:title]").attr("content", pageInfo.title);
	$("meta[name=twitter\\:description]").attr("content", pageInfo.description);
	$("meta[name=twitter\\:image]").attr("content", origin+pageInfo.image);    
}

// clears up objects created for current page to save memory
// and ensure there are no duplicate items before loading new box
function cleanUp() {
	if (activePlaylist) {
		activePlaylist=useFallbackAudio(activePlaylist); // get rid of youtube videos
		if (activePlaylist.tracks[jpaPlayer.currentTrack]['type']=='YouTube' && activePlaylist.tracks[jpaPlayer.currentTrack]['status']==1) {
			jpaPlayer.currentTech=activePlaylist.tracks[jpaPlayer.currentTrack]['type']; // change
			if (activePlaylist.tracks.length>1) {
				nextContent(); // skip from a video because it's about to disappear
			} else {
				pauseContent();
			}
		}
	}

	$('#ajaxContent').html('');
	$(".sleeve3d").removeClass('css3transform').removeClass('jsTransform');
	
	window.scrollTo(0,0);
	$("html, body").css({ scrollTop: 0 });

	jp3d=[];
	for (i in jp3dRenderer) {
		jp3dRenderer[i].available=true; // don't want to wipe them as it will still count towards our renderer limit, just make them ready to be used again
	}
	
	if (mainFilm) {
		//conLog('get rid of video_js object...');
		mainFilm.dispose(); // erase video js object, ready for use in future
		mainFilm=false;
	}
	if (playlist && playlist.tracks) {
		for (i in playlist.tracks) {
			if ("YouTube" in playlist.tracks[i]) {
				if (playlist.tracks[i].YouTube.f!==null) { // errors if we destroy before it's been completely built
					playlist.tracks[i].YouTube.destroy(); // free up memory	
				}
				//conLog('destroyed YT.player for track '+i);
			}
		}
	}
}