var jpaPlayer={
	'currentTrack':0,
	'currentBoxID':0,
	'currentTech':null,
	'currentHandle':null,
	'state':null, // playing, paused, loading etc
	'totalTracks':null,
	'tech':[],
	'volume':1,
	'muted':false,
	'dynamicLoad':false,
	'dynamicReady':false
}

var progressBar={
	'width':230,
	'left':0
}

// playlist will hold the currently open page's playlist and is used to hook up youtube clips, events, etc
// activePlaylist will hold the currently PLAYING playlist, that may be left over from a previous page
// to-do: when changing to a new page but leaving the old one running, failover all old video clips to audio fallbacks so that everything doesn't die.

var playlist;
var activePlaylist;

var mainFilm=false; // holds handler for main video
var mainFilmSeeking=false;

var loopHandle=null;
var looping=false;
var currentPlayer=false;
var soundCloudAPIKey='575a432ad95a9bc7c16262c925fd131b';

var audioPath='http://www.johnpeelarchive.com/audio/';
var origin = window.location.protocol + '//' + window.location.host;
audioPath=origin+'/audio/'; // so that the www matches

var devMode=true; // devMode on makes all conLog() calls output to console.log

var rdioCallback={};

function conLog(msg) {
	if (devMode) {
		console.log(msg);
	}
}

// called externally to begin sorting out page
function setupPlayer() {
	//if (jpaPlayer.dynamicLoad && !jpaPlayer.dynamicReady) return false;
	conLog('setupPlayer ->');
	//getBoxes(); // replacing with loading from PHP for first time
	insertYouTube();
	setupSoundManager();
	updatePlayer();
	initButtons();
	if (!jpaPlayer.dynamicLoad) loadMainFilm(); // sorted by page loader otherwise
}

function insertYouTube() {
	var src='http://www.youtube.com/iframe_api';
	var s = document.createElement( 'script' );
	s.setAttribute( 'src', src );
	document.body.appendChild( s );
}

function startLoop() {
	if (!looping) {
		looping=true;
		loopHandle = setInterval(function() {checkingLoop()}, 200); // infinite jest
	}
}

function stopLoop() {
	if (looping) {
		clearInterval(loopHandle);
		looping=false;
	}
}

function initButtons() {
	$('#jpaPlay, #fakeJpaPlay').unbind().click(function() {
		playContent();
	});
	
	$('#jpaPause, #fakeJpaPause').unbind().click(function() {
		pauseContent();
	});

	$('#jpaPrev, #fakeJpaPrev').unbind().click(function() {
		prevContent();
	});
	$('#jpaNext, #fakeJpaNext').unbind().click(function() {
		nextContent();
	});

	$('#jpaProgressBar').unbind().mousedown(function(e) { // only seek along video
		var progress=(e.pageX-progressBar.left)/progressBar.width
		$('#jpaProgressBar').mouseup(function(e) { // complete seeking
			var progress=(e.pageX-progressBar.left)/progressBar.width;
			seekContent(progress,true);
		})
	});
	
	$('#jpaMute').unbind().click(function() {
		$(this).toggleClass('on');
		if ($(this).hasClass('on')) {
			jpaPlayer.muted=true;
		} else {
			jpaPlayer.muted=false;
		}
	});

	$(window).resize(function() {
		setupProgressBar();
	});
	$(window).scroll(function() {
		setupProgressBar();
	})
	setupProgressBar();
}

function setupProgressBar() {
	if ($('#jpaProgressBar').length) {
		progressBar.width=$('#jpaProgressBar').width();
		var offset=$('#jpaProgressBar').offset();
		progressBar.left=offset.left;	
	}
}

////////////////////////////
// GENERIC jpaPlayer SHIT
////////////////////////////

// go through content but don't necessarily play
// amount is between 0 and 1
// dropIn is true if decided (mouseup) or false if just looking (mousedown,mousemove)
function seekContent(amount,dropIn) {
	conLog('seekContent() -> '+amount);
    var trackLength=activePlaylist.tracks[jpaPlayer.currentTrack].trackLength;
	var position=trackLength*amount;
	if (isNaN(position)) { position=0;}
    if (jpaPlayer.currentTech=='YouTube') { // commands change depending on tech....
    	currentPlayer=getCurrentPlayer();
    	if (activePlaylist.tracks[jpaPlayer.currentTrack].ready) {currentPlayer.seekTo(position,dropIn);}
    } else if (jpaPlayer.currentTech=='SoundCloud') {
    	//jpaPlayer.tech.soundCloud.setPosition(position*1000);
    } else if (jpaPlayer.currentTech=='Rdio') {
    	//jpaPlayer.tech.rdio.rdio_seek(position);
    } else if (jpaPlayer.currentTech=='SoundManager') {
    	jpaPlayer.tech.soundManager.setPosition(position*1000);
    }
}

function getCurrentPlayer() {
	currentPlayer=activePlaylist.tracks[jpaPlayer.currentTrack].YouTube;
	return currentPlayer;
}

// our commands are only used to send states to the players - we rely on feedback from the players to work out if stuff is actually paused/playing etc
function pauseContent() {
	$('#jpaPause').hide();
	$('#fakeJpaPause').hide();
	$('#jpaPlay').show();
	$('#fakeJpaPlay').show();
	$('.playBtn').addClass('play');
	conLog('pauseContent -->');
	if (!activePlaylist) return false; // nothing to do
	if (activePlaylist.tracks[jpaPlayer.currentTrack].ready) {
		if (jpaPlayer.currentTech=='YouTube') { // commands change depending on tech....
			currentPlayer=getCurrentPlayer();
	    	if (currentPlayer && currentPlayer.pauseVideo!==undefined) currentPlayer.pauseVideo();
	    	showShield(jpaPlayer.currentHandle);
	    } else if (jpaPlayer.currentTech=='SoundCloud') {
			//jpaPlayer.tech.soundCloud.pause();
	    } else if (jpaPlayer.currentTech=='Rdio') {
	    	//jpaPlayer.tech.rdio.rdio_pause();
	    } else if (jpaPlayer.currentTech='SoundManager') {
	    	jpaPlayer.tech.soundManager.pause();
	    }	
	}    
    stopLoop();
}

function jumpToContent(trackNo, newBoxID) {
	conLog('jump to track '+trackNo);
	conLog('jump to box '+newBoxID);
	conLog('current box is '+jpaBoxID);
	conLog('current player is '+jpaPlayer.currentBoxID);
	// pause current track BEFORE switching playlist
	pauseContent();

	if (newBoxID!=jpaPlayer.currentBoxID || !activePlaylist) initActivePlaylist();
	jpaPlayer.currentTrack=parseInt(trackNo);
	//initActivePlaylist();
	updatePlayer();
	resetTrackToStart();
	playContent();
}

function prevContent() {
	conLog('prevContent() ->');
	logPlay(0);
	pauseContent();
	jpaPlayer.currentTrack-=1;
	if (jpaPlayer.currentTrack<0) {
		jpaPlayer.currentTrack=jpaPlayer.totalTracks-1; // back from first goes to last (?)
	}
	updatePlayer();
	resetTrackToStart();
	playContent();	
	
}

function nextContent() {
	conLog('nextContent() ->');
	logPlay(0);
	pauseContent();
	jpaPlayer.currentTrack+=1;
	if (jpaPlayer.currentTrack>=jpaPlayer.totalTracks) {
		jpaPlayer.currentTrack=0; // back to start
	}
	updatePlayer();
	resetTrackToStart();
	playContent();
}

// used when a track is skipped to, but not when playing after pausing...
// checks that player objects exist first to stop Big Fat Errors
function resetTrackToStart() {
	conLog('resetTrackToStart -->');
	var currentTech=jpaPlayer.currentTech;
	if (currentTech=='SoundManager' && typeof jpaPlayer.tech.soundManager==='object') {
		seekContent(0,true);
	} else if (currentTech=='YouTube' && jpaPlayer.tech.youTube) {
		seekContent(0,true);
	} else {
		// do nothing
	}
}

// our commands are only used to send states to the players
function playContent() {
	conLog('playContent() ->');	
	showPlayerControls();
	pauseMainFilm();
	$('#jpaPlay').hide();
	$('#fakeJpaPlay').hide();
	$('#jpaPause').show();
	$('#fakeJpaPause').show();
	var playerName=activePlaylist.tracks[jpaPlayer.currentTrack].outputTo;
	$('.playBtn').addClass('play');
	$('#'+playerName+' .playBtn').removeClass('play');

	if (jpaPlayer.currentTech=='YouTube') {
		currentPlayer=getCurrentPlayer();
    	currentPlayer.playVideo();
    	hideShield(jpaPlayer.currentHandle);
    } else if (jpaPlayer.currentTech=='SoundCloud') {
		jpaPlayer.tech.soundCloud.play({'whileplaying': function() {
	    	soundCloudPlayingCallback(this);
	    }});
	} else if (jpaPlayer.currentTech=='SoundManager') {
		soundManagerLoadTrack(jpaPlayer.currentTrack,true); // if not loaded, load and autoplay
    } else if (jpaPlayer.currentTech=='Rdio') {
    	jpaPlayer.tech.rdio.rdio_play(activePlaylist.tracks[jpaPlayer.currentTrack].source); // rdio_play(rdiocode);
    }	
    startLoop();
}

// fetch playlist from JSON in playlist.js - only used by Joe Boyd book chapter
function getCurrentPlaylist() {
	conLog('getCurrentPlaylist ->');
	//return false; // skip out of here
	app.conLog('jpaBoxID');
	if (jpaBoxID in allPlaylists) {
		return allPlaylists[jpaBoxID];
	} else {
		return false;
	}
}

// loads jpaPlayer info from newly chosen current track
// on tech like soundcloud will also pre-load new song
function updatePlayer() {
	//if (jpaPlayer.dynamicLoad && !jpaPlayer.dynamicReady) return false; // don't do this stuff whilst we're loading because errors
	if (!activePlaylist) return false; // nothing to do yet
	conLog('updatePlayer ->');
	//conLog('updatePlayer() -> track '+jpaPlayer.currentTrack);
	$('#jpaPlaylist .playlistitem').each(function() {
		$(this).removeClass('playing');
	});
	$('.playlistitem').click(function() {
		var id=$(this).attr('id');
		var numb = parseInt(id.match(/(\d+)$/)[0], 10);
		jumpToContent(numb,jpaPlayer.currentBoxID);
	})
	$('#jpaTrackName'+jpaPlayer.currentTrack).addClass('playing');
	var currentTrack=activePlaylist.tracks[jpaPlayer.currentTrack];
	if (activePlaylist.tracks[jpaPlayer.currentTrack].icon) {
		if (activePlaylist.dynamic==false) {
			var folder='img/';
		} else {
			if (activePlaylist.folder.indexOf('/')==-1) { // this is a record box
				var folder='/content/'+activePlaylist.folder+'/';
			} else { // this is a record
				var folder=activePlaylist.folder;
			}
		}
		$('#jpaPlayerThumb').attr({'src':folder+activePlaylist.tracks[jpaPlayer.currentTrack].icon});
	}
	jpaPlayer.currentTech=currentTrack.type;
	jpaPlayer.currentHandle=currentTrack.outputTo; //only used by YouTube - if this is not the case may need to change this
	jpaPlayer.state='unknown';	
	var title=currentTrack.title+'<span> - </span> '+currentTrack.artist;
	$('#jpaTrackInfo').html(title);
	if (jpaPlayer.currentTech=='SoundCloud') {
		soundCloudLoadSong();
	} else if (jpaPlayer.currentTech=='SoundManager') {
		//soundManagerLoadTrack(jpaPlayer.currentTrack);
	} else if (jpaPlayer.currentTech=='YouTube') {
		seekContent(0,true); // put this back to start for next time first....
	}
}

function showPlayerControls() {
	conLog('showPlayerControls');
	if ($('#player').hasClass('on')) return true;
	var height=parseInt($('#player').height());
	var bot=0;
	if (gPageWidth<768) {
		var bot=-86;
	}
	$('#player').addClass('on').css({'bottom':'-'+height+'px'}).show().animate({'bottom':bot},1000);
	setupProgressBar();
}

// volume level between 0 and 1
function changeVolume(volumeLevel) {
	if (jpaPlayer.muted) return false;
	if (jpaPlayer.currentTech=='YouTube') {
		if (typeof currentPlayer != "undefined") {currentPlayer.setVolume(volumeLevel*100);}
	} else if (jpaPlayer.currentTech=='SoundCloud') {
		jpaPlayer.tech.soundCloud.setVolume(volumeLevel*100);
	} else if (jpaPlayer.currentTech=='SoundManager') {
		jpaPlayer.tech.soundManager.setVolume(volumeLevel*100);
	} else if (jpaPlayer.currentTech=='Rdio') {
		jpaPlayer.tech.rdio.rdio_setVolume(volumeLevel);
	}
}

// true for muted, false for not muted
function changeMuted(muted) {
	if (jpaPlayer.currentTech=='YouTube') {
		currentPlayer=getCurrentPlayer();
		if (typeof currentPlayer != "undefined") {
			if (muted) {
				currentPlayer.mute();
			} else {
				currentPlayer.unMute();
			}	
		}
	} else if (jpaPlayer.currentTech=='SoundCloud') {
		jpaPlayer.tech.soundCloud.toggleMute(); //not ideal - requires us to keep track
	} else if (jpaPlayer.currentTech=='SoundManager') {
		jpaPlayer.tech.soundManager.toggleMute(); //not ideal - requires us to keep track
	} else if (jpaPlayer.currentTech=='Rdio') {
		jpaPlayer.tech.rdio.rdio_setMute(muted);
	}
}

// this is used to hook up an onscreen item so used playlist NOT activePlaylist
function createAudioPlayer(trackNo) {
	var playerName=playlist.tracks[trackNo].outputTo;
	var thisTrack=playlist.tracks[trackNo];
	conLog('createAudioPlayer -> '+trackNo);

	$('#'+playerName+' .playBtn').click(function(event) {
		conLog('click');
		event.stopPropagation();
		var trackNo=getTrackNumberFromPlayerName(playerName);
		//initActivePlaylist(); // pull this new playlist into being
		if ($(this).hasClass('play')) {
			$('.playBtn').addClass('play');
			$('#'+playerName+' .playBtn').removeClass('play');
			jumpToContent(trackNo,playlist.playlistID);
		} else {
			$('#'+playerName+' .playBtn').addClass('play');
			pauseContent();	
		}		
	});
}

function logPlay(ended) {
	//console.log('LOG PLAY '+ended);
	var filename=activePlaylist.tracks[jpaPlayer.currentTrack].source;
	var currentTrack=activePlaylist.tracks[jpaPlayer.currentTrack];
	var playedSeconds=parseInt(activePlaylist.tracks[jpaPlayer.currentTrack].currentTime);
	if (isNaN(playedSeconds)) playedSeconds=0;
	conLog('logPlay '+filename);

	var data={'filename':filename,'key':'8c979e3f83c3c93068f7470a9ae6c78b','action':'logPlay','ended':ended,'playedSeconds':playedSeconds,'boxID':jpaPlayer.currentBoxID};
	//console.log(data);
	$.ajax({url:"/ajax/jpAjax.php",'data':data,'success':function(result){
	    //console.log(result);
	  }});
}

////////////////////////////
// YOUTUBE SHIT
////////////////////////////

// called when iFrame YouTube ready for stuff
function onYouTubeIframeAPIReady() {
	console.log('youtube iframe api ready');
	jpaPlayer.tech.youTube=true; // flag that youTube is ready for 'action'
	if (!jpaPlayer.dynamicLoad || jpaPlayer.dynamicReady) initPlaylist(); // static pages load playlist immediately, others wait
	
}

// this is used for hooking up events so uses playlist NOT activePlaylist
function initYouTube(playerName) {
	conLog('initYouTube -> '+playerName);
	if (!jpaPlayer.tech.youTube) return false; // cannot set up yet
	var trackNo=getTrackNumberFromPlayerName(playerName);
	
	$('#'+playerName).addClass('videoContainer');
	var code='<div id="'+playerName+'Player"></div>'; // actual jpaPlayer container
	code+='<div id="'+playerName+'Shield" class="videoShield"></div>'; // video shield/play button
	$('#'+playerName+' .inlineVideo').append(code);
	var width=$('#'+playerName).width();
	var ratio=playlist.tracks[trackNo].ratio;
	if (!ratio) ratio=0.75;
	var height=parseInt(width*ratio);

	playerName=playerName+'Player';
    playlist.tracks[trackNo].YouTube= new YT.Player(playerName, {
		'height': height,
		'width': width,
		'playerVars':{
			'controls':0,
			'modestbranding':1
		},
		'events': {
		'onReady': onYouTubePlayerReady,
		'onStateChange': onYouTubePlayerStateChange
		}
	});
}

// called directly by YouTube API - name must stay the same
function onYouTubePlayerReady(obj) {
    conLog('onYouTubePlayerReady ->');
    conLog(obj);
    playerName=obj.target.f.id; // changed from obj.target.d.id to meet new inexplicable YT changes
    conLog(playerName);
    var trackNo=getTrackNumberFromPlayerName(playerName);
    if (!trackNo) { return false; } // fail
    playlist.tracks[trackNo].ready=true;
    var source=playlist.tracks[trackNo].source;
    //var currentPlayer= document.getElementById(playerName);
    playlist.tracks[trackNo].YouTube.cueVideoById({'videoId':source, 'startSeconds':0, 'endSeconds':0, 'suggestedQuality':'default'});
    createYouTubeShield(playerName);
}

function onYouTubePlayerStateChange(event) {
	//conLog('onYouTubePlayerStateChange ->');
	//console.log(event);
	playerName=event.target.f.id;
	var trackNo=getTrackNumberFromPlayerName(playerName);
	if (event.data==0) {
		nextContent(); //auto shuffle to next
	}
	if (event.data==2&&trackNo==jpaPlayer.currentTrack) { // means player has been paused from elsewhere
		pauseContent();
	}
}

// this works with currently onscreen shit so uses playlist NOT activePlaylist
function createYouTubeShield(playerName) {
	//conLog('createYouTubeShield -> '+playerName);
	if (playerName.indexOf('Player')!=-1) {
		playerName=playerName.slice(0,-6);
	}
	//if (playlist.tracks[getTrackNumberFromPlayerName(playerName)].offScreen!=true) {
		var width=$('#'+playerName+' .inlineVideo').width();
		if (width==null) { return false;}
		var height=$('#'+playerName+' .inlineVideo').height();
		$('#'+playerName+'Shield').css({'top':'0px','right':'0px','width':width+'px','height':height+'px','z-index':10,'opacity':0});
		$('#'+playerName+' .playBtn').click(function(event) {
			
			var trackNo=getTrackNumberFromPlayerName(playerName);
			event.stopPropagation();
			if ($(this).hasClass('play')) {
				$('.playBtn').addClass('play');
				$('#'+playerName+' .playBtn').removeClass('play');
				jumpToContent(trackNo,playlist.playlistID); 
			} else {
				var trackNo=getTrackNumberFromPlayerName(playerName);
				$('#'+playerName+' .playBtn').addClass('play');
				pauseContent();
			}		
		})
		if (playlist.dynamic==false) {
			var folder='img/';
		} else {
			if (playlist.folder.indexOf('/')==-1) { // this is a record box
				var folder='/content/'+playlist.folder+'/';
			} else { // this is a record
				var folder=playlist.folder;
			}
			
		}
		var splash=playlist.tracks[getTrackNumberFromPlayerName(playerName)].splashImage;
		if (!splash || splash.length==0) {
			splash="/img/boxes/default-video-overlay.jpg";
		} else {
			splash=folder+splash;
		}
		if (splash!=undefined) {
			var path='url("'+splash+'")';
			$('#'+playerName+'Shield').css({'background-image':path,'background-size':'contain','background-repeat':'no-repeat','background-position':'center','opacity':1});
		} else {
			
		}
		doResize();
		$('.videoContainer').animate({'opacity':1},500);
	//}
}

// again, page rather than playlist-centric
function hideShield(playerName) {
	//conLog('hideShield -> '+playerName);
	if (!playlist.tracks[getTrackNumberFromPlayerName(playerName)].offScreen==true) {
		$('#'+playerName+'Shield').animate({'opacity':0},500).css({'display':'none'});
	}
}

// again // again, page rather than playlist-centric
function showShield(playerName) {
	//conLog('showShield -> '+playerName);
	if (!playlist.tracks[getTrackNumberFromPlayerName(playerName)].offScreen==true) {
		$('#'+playerName+'Shield').css({'display':'block'}).animate({'opacity':1},500);
	}
}

////////////////////////////
// SOUND MANAGER SHIT
////////////////////////////

function setupSoundManager() {
	//conLog('setupSoundManager...');

	soundManager.setup({
	  'flashVersion': 8,
	  'preferFlash': false,
	  'debugFlash':false,
	  'debugMode':false,
	  'consoleOnly':true,
	  'url': origin+'/js/boxes/soundmanager/swf/',
	  'waitForWindowLoad':true,
	  onready: function() {
	    jpaPlayer.tech.soundManager=true;
	  },
	  ontimeout: function() {
	    jpaPlayer.tech.soundManager=false;
	    //conLog('soundmanager timed out');
	  },
	  defaultOptions: {
	    // set global default volume for all sound objects
	    'volume': 50
	  }
	});
}

function soundManagerLoadTrack(trackNo,autoplay) {
	conLog('soundmanager load track '+trackNo+' autoplay is '+autoplay);
	//conLog(autoplay);
	if (jpaPlayer.tech.soundManager) {
		conLog('SoundManager load track '+trackNo);
		if (autoplay==undefined) autoplay=false;
		if (autoplay) {
			activePlaylist.tracks[trackNo].ready=true;
		}

		var thisTrack=activePlaylist.tracks[trackNo];
		conLog(thisTrack);
		var extension='.mp3';
		var path=audioPath+'mp3/'+thisTrack.source+extension;
		conLog(path);
		if(!soundManager.canPlayURL(path)) {
			extension='.ogg';
			path=audioPath+'ogg/'+thisTrack.source+extension;
			if(!soundManager.canPlayURL(path)) {
				extension='.wav';
				path=audioPath+'wav/'+thisTrack.source+extension;
			}
		}
		jpaPlayer.tech.soundManager = soundManager.createSound({
			'id': thisTrack.outputTo,
			'url': path,
			onfinish: function() {
				console.log('SM FINISH');
		  		logPlay(1);
		  		nextContent();
			}
		});
		if (autoplay) {
			//logPlay(0);
			jpaPlayer.tech.soundManager.play({'whileplaying': function() {
				soundCloudPlayingCallback(jpaPlayer.tech.soundManager);
			}})
		}
	} else {
		//conLog('SoundManager track '+trackNo+' not loaded as SoundManager not ready yet');
		activePlaylist.tracks[trackNo].ready=false;
	}
	
}

////////////////////////////
// SOUNDCLOUD SHIT
////////////////////////////

function initSoundCloud() {
	SC.initialize({ 'client_id': soundCloudAPIKey});
	SC.whenStreamingReady(function(sound) { jpaPlayer.tech.soundCloud=true; conLog('SC')});
}

// received when track is set up streaming, stores handle in jpaPlayer.tech for access
function onSoundCloudReady(obj) {
	jpaPlayer.tech.soundCloud=obj; // stores handler so we can access later
}

function soundCloudLoadSong() {
	SC.stream("/tracks/"+activePlaylist.tracks[jpaPlayer.currentTrack].source,{
		'autoPlay':false,
		'streaming':true,
		'onfinish': function() { nextContent(); }
		},
		function(obj){ onSoundCloudReady(obj); })
}

function soundCloudPlayingCallback(obj) {
	var i = jpaPlayer.currentTrack; //without this, all the code is nonsense
	if (obj.paused) {
		activePlaylist.tracks[i].status='paused';
	} else if (obj.playState==1) {
		activePlaylist.tracks[i].status='playing';
	}
	activePlaylist.tracks[i].currentTime=obj.position/1000; //from ms to secs
	activePlaylist.tracks[i].trackLength=obj.durationEstimate/1000; // from ms to secs
	activePlaylist.tracks[i].muted=obj.muted; // true for muted
	activePlaylist.tracks[i].volume=obj.volume/100; // integer between 0 and 100 for SoundCloud
	activePlaylist.tracks[i].loaded=0; //leave obj for now, way complicated on SC
}

////////////////////////////
// PLAYLIST SHIT
////////////////////////////

function getTrackNumberFromPlayerName(playerName) {
	for (i in playlist.tracks) {
		if (playerName==playlist.tracks[i].outputTo) {
			return i;
		} else if (playlist.tracks[i].type=='YouTube'&&playerName==playlist.tracks[i].outputTo+'Player') {
			return i;
		}
	}
	return false;
}

// this gets all the tracks on the current page (as opposed to current playlist) and sets them up etc
// called when both youtube is ready AND when dynamic pages are ready, once both are, we get to work
function initPlaylist() {
	conLog('initPlaylist ->');
	if (!jpaPlayer.tech.youTube) {
		conLog('youTube not ready yet...');
		return false; //if youtube is not ready, don't do this
	}
	if (jpaPlayer.dynamicLoad && !jpaPlayer.dynamicReady) {
		conLog('page not loaded yet...');
		return false; // if dynamic page, and it is not ready
	}
	//iPhone=true;//dev check
	conLog('initPlaylist ready to go...');
	conLog(jpaPlayer);


	//if (!jpaPlayer.dynamicLoad) playlist=getCurrentPlaylist();
	// this is hacked in to make the Joe Boyd Book Chapter work, as it is not in the CMS properly and gets it's playlist from the old system in playlist.js
	currentPath=window.location.href;
	if (jpaPlayer.dynamicLoad==false && currentPath.indexOf('joe-boyd-book-chapter')!=-1) { 
		playlist=allPlaylists[5];
	}
	if (!playlist) return false;

	if (iPhone) playlist=useFallbackAudio(playlist); // replace youtube videos with fallback
	for (i in playlist.tracks) {
		thisTech=playlist.tracks[i].type;
		if (thisTech=='YouTube') {
			if (!playlist.tracks[i].outputTo) { // if no defined handle for video, create and store one.
				playlist.tracks[i].outputTo='jpaYouTube'+i;
			}
			initYouTube(playlist.tracks[i].outputTo);
		} else if (thisTech=='SoundManager') {
			createAudioPlayer(i);
		}
	}
	conLog(playlist);
	initButtons(); // re-attach events
}

function useFallbackAudio(thisPlaylist) {
	conLog('useFallbackAudio ->');
	//conLog(thisPlaylist);
	for (i in thisPlaylist.tracks) {
		//console.log(thisPlaylist.tracks[i]);
		if (thisPlaylist.tracks[i].type=='YouTube' && thisPlaylist.tracks[i].fallback) { // ignore YouTube videos - use Soundmanager clips instead
			thisPlaylist.tracks[i]=thisPlaylist.tracks[i].fallback;
			$('#'+thisPlaylist.tracks[i].outputTo+' .video').css({'margin-top':'20px'}); // horrible css hack
		}
	}
	return thisPlaylist;
}

// called before a player is started, sets up list of tracks etc
function initActivePlaylist(force) {
	conLog('initActivePlaylist ->');
	//if (jpaBoxID==jpaPlayer.currentBoxID) return false; // playlist is already up to speed
	conLog('setting new activePlaylist...');
	jpaPlayer.currentBoxID=playlist.playlistID;//jpaBoxID; // start playing from current page
	activePlaylist=playlist;
	jpaPlayer.totalTracks=0;
	$('#jpaPlaylist').html(''); // clear playlist
	for (i in activePlaylist.tracks) {
		jpaPlayer.totalTracks++;
		thisTech=activePlaylist.tracks[i].type;
		var displayNo= +i +1;
		var code='<div class="playlistitem" id="jpaTrackName'+i+'">'+displayNo+'. '+activePlaylist.tracks[i].title+'<br><span>'+activePlaylist.tracks[i].artist+'<span></div>';
		$('#jpaPlaylist').append(code);
	}
}


function updateProgressBar() {
	// normal bar
	var length=activePlaylist.tracks[jpaPlayer.currentTrack].trackLength;
	if (length>0) {
		var current=activePlaylist.tracks[jpaPlayer.currentTrack].currentTime;
		var position = (current/length)*progressBar.width;
		$('#jpaProgressMade').css({'width':parseInt(position)+'px'});
	} else {
		// maybe hide progress bar too?
		$('#jpaProgressMade').css({'width':'0px'});
	}
	//loaded bar
	if (activePlaylist.tracks[jpaPlayer.currentTrack].loaded>0) {
		var loaded=activePlaylist.tracks[jpaPlayer.currentTrack].loaded;
		var position = loaded*progressBar.width;
		$('#jpaLoadedBar').css({'width':parseInt(position)+'px'});
	
	} else {
		// maybe hide progress bar too?
		$('#jpaLoadedBar').css({'width':'0px'});
	}
	var time=parseInt(activePlaylist.tracks[jpaPlayer.currentTrack].trackLength);
	if (time===parseInt(time)) {
		var minutes = Math.floor(time / 60);
		var seconds = time - minutes * 60;
		$('#jpaPlayerTotal').html(pad(minutes,2)+':'+pad(seconds,2));	
	} else {
		$('#jpaPlayerTotal').html('00:00');
	}
	
	var time=parseInt(activePlaylist.tracks[jpaPlayer.currentTrack].currentTime);
	if (time===parseInt(time)) {
		var minutes = Math.floor(time / 60);
		var seconds = time - minutes * 60;
		$('#jpaPlayerProgress').html(pad(minutes,2)+':'+pad(seconds,2));
	} else {
		$('#jpaPlayerProgress').html('00:00');	
	}
}

function pad(num, size) {
    var s = "000000000" + num;
    return s.substr(s.length-size);
}


function checkingLoop() {
	//conLog('checkingLoop() - >');
	var i=jpaPlayer.currentTrack;
	if (!activePlaylist) return false
	if (!(i in activePlaylist.tracks)) return false;
	var thisTrack=activePlaylist.tracks[i];
	if (thisTrack.type=='YouTube') { // different status checking code depending on each tech
		var currentPlayer= getCurrentPlayer();
		if (currentPlayer&&thisTrack.ready) { // if not loaded yet or whatever will error
			activePlaylist.tracks[i].status=currentPlayer.getPlayerState();
			if (currentPlayer.getPlayerState()==2) {
				pauseContent(); // it's already paused by YouTube - our app needs to respond
			}
			activePlaylist.tracks[i].currentTime=currentPlayer.getCurrentTime(); // current time in seconds
			activePlaylist.tracks[i].trackLength=currentPlayer.getDuration(); // duration in seconds
			activePlaylist.tracks[i].muted=currentPlayer.isMuted(); // true for muted
			activePlaylist.tracks[i].volume=currentPlayer.getVolume()/100; // integer between 0 and 100 for YouTube
			activePlaylist.tracks[i].loaded=currentPlayer.getVideoLoadedFraction(); // between 0 and 1
			if (activePlaylist.tracks[i].status==0) {
				nextContent();
			}
		}
	}
	updateProgressBar();
}

function onMainPlayerStateChange(info) {
	//conLog('onMainPlayerStateChange...');
	if (info.data==0 || info.data==2) { // if player has been paused or stopped
		pauseMainFilm();
	}
}

// used as old IE freaks out with video js
function removeMainFilm() {
	conLog('removeMainFilm ->')
	$('#mainVideo').hide();
}

function loadMainFilm(forceEmbed) {
	conLog('loadMainFilm ->');
	
	if (jpaPlayer.dynamicLoad && !jpaPlayer.dynamicReady) return false;
	if (playlist.hasVideo==false) return false;
	if (oldIE) {
		removeMainFilm();
		return false;
	}

	if (deviceType==3) { //iOS devices have a weird problem with this.
		$('.mainVideoShield').addClass('cantSeeThis').animate({'opacity':0},1000,function() {
			$(this).hide();
			$('.mainVideoShield').html(' ');
		});
		$('.mainVideoControls').animate({'opacity':0,'bottom':'-100px'},800,function() {
			$(this).hide();
			$('.mainVideoControls').html(' ');
		});
	}
	if ($('#mainFilmJS').length==0) return false;
	
	videojs.options.flash.swf = origin+"/js/video-js/video-js.swf";

	videojs("mainFilmJS", {}, function(){
	 	mainFilm=this;
	 	mainFilm.on("play",function() { mainFilmSeeking=false;})
	 	mainFilm.on("ended",function() {pauseMainFilm()}); // DOUBLE QUOTES ARE FRUSTRATINGLY IMPORTANT HERE
	 	mainFilm.on("pause",function() { pauseMainFilm()}); // DOUBLE QUOTES ARE FRUSTRATINGLY IMPORTANT HERE
	 	mainFilm.on("seeking",function(){ mainFilmSeeking=true;})
	});
	
	/*
	var playerName='mainFilmPlayer';
	if (deviceType==3 || deviceType==2 || forceEmbed==true) { // for iOS, just replace top stuff with plain embed.
		var embed='<iframe width="auto" height="auto" src="//www.youtube.com/embed/Gt_xLLDvKAQ?rel=0" frameborder="0" allowfullscreen></iframe>';
		$('#mainFilm').html(embed);
	} else {
		mainFilm= new YT.Player(playerName, {
			'height': 'auto',//parseInt($('#mainFilm').height()),
			'width': 'auto',//parseInt($('#mainFilm').width()),
			'events': {
				'onReady': loadedMainFilm,
				'onStateChange': onMainPlayerStateChange,
				'onError': onMainPlayerError
			},
			'playerVars':{
				'controls':1
			},
		});	
	}	
	*/
}

function onMainPlayerError() {
	//conLog('onMainPlayerError');
	//loadMainFilm(true); // reload film as normal embed without manual controls
}

function loadedMainFilm() {
	//conLog('loadedMainFilm...');
	//var source='Gt_xLLDvKAQ';
    ////var currentPlayer= document.getElementById(playerName);
    //mainFilm.cueVideoById({'videoId':source, 'startSeconds':0, 'endSeconds':0, 'suggestedQuality':'default'});
}

// used to play main video, also pauses jpaPlayer
function playMainFilm() {
	if (!mainFilm) return false;
	//conLog(mainFilm);
	mainFilm.play(); //videojs command
	//mainFilm.playVideo(); // youtube command
	if (!$('#mainFilm').hasClass('playedOnce')) {
		//mainFilm.seekTo(0,false);
		$('#mainFilm').addClass('playedOnce');
	}
	$('.mainVideoShield').addClass('cantSeeThis').animate({'opacity':0},1000,function() {
		$(this).hide();
	});
	$('.mainVideoControls').animate({'opacity':0,'bottom':'-100px'},800,function() {
		$(this).hide();
	});
	pauseContent();
}

// called by jpaPlayer
function pauseMainFilm() {
	if (!mainFilm || mainFilmSeeking) return false;
	//conLog('pauseMainFilm');
	if (mainFilm.isFullscreen()) {
		mainFilm.exitFullscreen(); // if paused, close full screen
	}
	
	mainFilm.pause();
	//mainFilm.pauseVideo(); //YT command
	if (deviceType!=3) {
		$('.mainVideoShield').removeClass('cantSeeThis').css({'opacity':0}).show().animate({'opacity':1},800);
		$('.mainVideoControls').css({'opacity':0}).show().animate({'opacity':1,'bottom':'0px'},800);	
	}
}
