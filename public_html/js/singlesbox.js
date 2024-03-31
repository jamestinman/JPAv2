var boxSpeed=0;
var reverse=true;
var navRecords={}
var colour="#f6d404";
var tracing=false, svgPath="", mX=0, mY=0, traceCount=0, boxIsAnimating=0, suppressHandleScroll=0;
var oLookup=[];
var curID=0, oldID=0;
var firstID=false, lastID=false; // first and last in this box
var numIDs=0;
// Detect touch-screen devices
var inTouch=('ontouchstart' in document.documentElement);

// FUNCTIONS
// Touch ! TBC !
/*
var doTouchStart=function(e) {
  var touchX=e.touches[0].pageX-this.offsetLeft;
  var touchY=e.touches[0].pageY-this.offsetTop; 
  if (touchX<=140 && touchY<=36) document.location.href="index.html";
  startID=getIDfromX(touchX);
  showTab(startID);
  if (touchX) { }
}
var doTouchMove=function(e) {
  e.preventDefault();
  var touchX=e.touches[0].pageX-this.offsetLeft;
  var id=getIDfromX(e.touches[0].pageX);
  if (id && id!=startID && id!=oldID) showTab(id);
}
var doTouchEnd=function(e) { popupAlbumInfo(); } // Note: x is not available to endtouch
*/
var handleBoxNavMouseMove=function(e) {
  var animationStyle='direct';
  mX=e.pageX-this.offsetLeft;
  mY=e.pageY-this.offsetTop;
  if (p("allowTrace")) $('#debug').html(mX +', '+ mY);
  if (boxIsAnimating) return false; // If already animating, do nothing more, the box will head for the new mY value
  if (animationStyle=='direct') {
    // Find the closest record to the mouse position
    var goingDown=(mY>(records[curID].v+200))?true:false; // http://www.youtube.com/watch?v=h3Yrhv33Zb8
    var goingUp=(mY<records[curID].v)?true:false;
    // console.log("Mouse is at "+mY+" and current record span is "+records[curID].v+" to "+(records[curID].v+200)+" so we are "+((goingUp)?"going up":((goingDown)?"going down":"staying still")));
    if (!goingUp && !goingDown) return false; // We are still within range for the current record
    var newID=false; var firstID=false;
    for (var id in navRecords) {
      if (!firstID) firstID=id;
      if (goingUp && records[id].v<mY && !newID) newID=id; // When going up, the FIRST record in range gets picked
      if (goingDown && records[id].v+200>mY) newID=id; // When going down, the LAST matching record in range gets picked
    }
    if (newID) {
      curID=newID;
    } else if (goingUp) {
      // Choose the top record
      curID=id;
    } else if (goingDown) {
      // Choose the first record
      curID=firstID;
    }
    moveTo(true);
  } else {
    boxIsAnimating=1;
    animateBoxNav();
  }
}

var animateBoxNav=function() {
  var v1=records[curID].v;
  var v2=v1+200;
  // Are we heading up, down or nowhere?
  if (mY<v1) {
    goNext(true);
  } else if (mY>v2) {
    goPrev(true);
  } else {
    boxIsAnimating=false; // stop animating now
  }
  // Throttle the animation
  if (boxIsAnimating) window.setTimeout(animateBoxNav,boxSpeed);
}

function goPrev() {
  var cutPoint=curID.indexOf('_7_')+3;
  var i=curID.substr(cutPoint); // BOWIE_7_5 => 5
  // console.log("curID="+curID+" whereas i="+i);
  oldID=curID;
  if (--i<1) { i=1; boxIsAnimating=false; console.log("Stopped animating at bottom ["+curID+"]!"); }
  curID=curID.substr(0,cutPoint)+i;
  moveTo(true);
}

function goNext() {
  var cutPoint=curID.indexOf('_7_')+3;
  var i=curID.substr(cutPoint); // BOWIE_7_5 => 5
  // console.log("curID="+curID+" whereas i="+i);
  oldID=curID;
  if (++i>=numIDs) { i=numIDs; boxIsAnimating=false; console.log("Stopped animating at top ["+curID+"]!"); }
  curID=curID.substr(0,cutPoint)+i; // Grab the next 7...
  moveTo(true);
}

// In reverse box, firstID = e.g. 57
function resetFirst(doScroll) {
  curID=firstID;
  $("#"+curID+"nav").show();
  // history.pushState({jpID:curID},"John Peel Singles Archive", "recordbox.html"+((reverse)?"?jpID="+curID:""));
  if (doScroll) $("#boxInfo7").scrollTo(0);
}

// In reverse box, lastID = 1
function resetLast(doScroll) {
  curID=lastID;
  $("#"+curID+"nav").show();
  // history.pushState({jpID:curID},"John Peel Singles Archive", "recordbox.html"+((!reverse)?"?jpID="+curID:""));
  if (doScroll) $("#boxInfo7").scrollTo("#"+lastID);
}

// Move directly to an ID
function moveTo(doScroll) {
  // Left nav
  $("#"+curID+"nav").show(); // Switch new nav in first
  if (oldID && oldID!=curID) $("#"+oldID+"nav").hide();
  oldID=curID;
  if (curID==firstID) return resetFirst(doScroll);
  if (curID==lastID) return resetLast(doScroll);
  // history.pushState({jpID:curID}, curID+" : John Peel Singles Archive", "recordbox.html?jpID="+curID);
  $('#navFrame').css({'top':records[curID].v+'px'});
  // Main content
  if (doScroll) $("#boxInfo7").scrollTo("#"+curID,boxSpeed,{easing:"easeInOutCubic"});
}

var handleScroll=function(e) {
  if (boxIsAnimating || suppressHandleScroll) {
    // console.log('avoiding scroll - still animating. mY='+mY+' and curID '+curID+' is between '+records[curID].v+' and '+(records[curID].v+200));
    return false;
  }
  // Loop over single7s to determine closest content to current html page top
  var topID=false;
  $('.single7').each(function() {
    var single7 = $(this);
    var position = single7.position().top;
    if (position >= -25 && !topID) {
      // This one!
      topID=single7.attr('id');
      // single7.addClass('selected');
      console.log("Scrolled to "+topID+" which is at "+position);
    } else {
      // single7.removeClass('selected');
    }
  });
  curID=topID;
  if (topID) moveTo(false);
}

// ON-LOAD
$(document).ready(function(){
  // Pick up information about what we are displaying from the URL params
  if (p("jpID")) {
    curID=p("jpID");
    letter=curID.charAt(0);
  } else if (p("letter")) {
    letter=p("letter");
    document.title="7-inch singles starting with '"+letter+"' : John Peel Archive";
  } else {
    letter="B";
  }
  // Load the data for this part of the collection
  datafile="../data/singles/"+letter+".js";
  // Once the specific data js file has loaded, kick off initialise()
  $.getScript(datafile).done(function(){ initialise(); }).fail(function() { unavailable(); });
});

function getHeading() {
  var c="";
  c+="<h1>DAVID BOWIE SINGLES COLLECTION</h1>";
  c+='<div>';
  c+="<!-- Social --><div class='social' style='margin: -20px 0 0 392px;'>";
  c+='<table><tr>';
  c+='<td><a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.johnpeelarchive.com/singles/recordbox.html" data-via="johnpeelarchive" data-text="John Peel\'s Bowie 7s" data-hashtags="#jpa" data-lang="en">Tweet</a></td>';
  c+='<td><g:plusone size="medium" href="http://www.johnpeelarchive.com/singles/recordbox.html"></g:plusone></td>';
  c+='<td><fb:like href="http://www.johnpeelarchive.com/singles/recordbox.html" send="false" layout="button_count" width="50" show_faces="false" action="like" title="John Peel\'s Bowie 7s"></fb:like></td>';
  c+='</tr></table>';
  c+='</div>';
  return c;
}
// Fired once the data has loaded
function initialise() {
  if (p("allowTrace")) $('body').append("<button id='traceBtn' onClick='runTrace();'>Start Trace</button><div id='thePath'></div><div id='debug'></div>");
  if (p("reverse")) reverse=(p("reverse")=="Y")?true:false;
  navRecords=records;
  // Capture mouse movement x & y pos on the singles nav
  $("#boxNav7").mousemove(handleBoxNavMouseMove);
  $("#boxNav7").mouseover(function() {suppressHandleScroll=1; });
  $("#boxNav7").mouseout(function() {suppressHandleScroll=0; });
  $("#boxInfo7").mouseover(function() {suppressHandleScroll=0; });
  $("#boxInfo7").scroll(handleScroll);
  // Configure Galleria
  Galleria.configure({imageCrop: true, transition: 'slide', width: 325, height: 345});
  Galleria.loadTheme('../js/libs/themes/classic/galleria.classic.min.js');
  // GENERATE CONTENT
  $("#boxInfo7").append("<h1>DAVID BOWIE SINGLES COLLECTION</h1>");
  // Load the blurb, stack the nav & count the records
  firstID=lastID=numIDs=0;
  var n="";
  if (reverse) {
    var tmpStack=[];
    var i=0;
    for (var id in records) { tmpStack[i++]={'id':id, 'records': records[id]} }
    records={} // Clear out records
    for (j=i-1; j>=0; j--) { records[tmpStack[j].id]=tmpStack[j].records; }
  }
  for (var id in records) {
    numIDs++;
    var r=records[id];
    // Stack the nav
    n+="<img id='"+id+"nav' src='img/boxes/"+letter+"/"+id+".png' class='nav7Img' />";
    // Create the blurb
    if (!firstID) {
      firstID=id;
      h="";
    } else {
      h="<hr />";
    }
    h+="<div id='"+id+"' class='single7'>";
    h+="<h2>"+r.title+" <span class='white'>"+getIfSet(r,'release')+"</span></h2>";
    // Load up the galleria
    // h+="<p style='text-align: center'><img src='img/"+letter+"/"+id+"-1.png' height='450' /></p>";
    h+="<div id='"+id+"galleria' class='singleGallery'>";
    var firstSleeveUrl=false, sleeveUrl;
    for (i=1; i<=r.numImages; i++) {
      thUrl="img/"+letter+"/th/"+id+"-"+i+".jpg";
      sleeveUrl="img/"+letter+"/"+id+"-"+i+".jpg";
      if (!firstSleeveUrl) firstSleeveUrl=sleeveUrl;
      // <img src="/img/pic1.jpg" data-title="My title" data-description="My description">
      h+="<img src='"+thUrl+"' data-big='"+sleeveUrl+"' />";
    }
    // Add big pop-up to the nav
    // $('#navFrame').click(function() { $.fancybox({ href:"img/"+letter+"/"+curID+"-1.jpg"}); });
    h+="</div>";
    h+="<div class='singleBlurb'>";
    h+="<div class='A-side'><b class='yellow'>A:</b> <b>"+r.A+"</b></div>";
    h+="<div class='B-side'><b class='yellow'>B:</b> "+r.B+"</div>";
    h+="<div class='release'>"+getIfSet(r,'label')+" "+getIfSet(r,'release')+"</div>";
    h+=r.blurb;
    h+="</div>";
    h+=doLinks(r,'listen','../img/',"Listen");
    h+=doLinks(r,'info','../img/',"Other Info");
    h+=doBoxVideos(r);
    var me="http://www.johnpeelarchive.com/singles/recordbox.html?jpID="+id;
    var description="See "+r.title+" as it is in John Peel's collection";
    // Social
    /*
    h+="<div class='social'><table><tr>";
    // Pinterest
    h+='<td>&nbsp;&nbsp;&nbsp;<a href="http://pinterest.com/pin/create/button/?url='+me+'&media='+firstSleeveUrl+'&description='+description+'" class="pin-it-button pin-it-btn" count-layout="horizontal"><img border="0" src="//assets.pinterest.com/images/PinExt.png" title="Pin It" /></a></td>';
    h+='</table></div>';  
    h+="</div>";
    */
    $("#boxInfo7").append(h);
    Galleria.run('#'+id+'galleria', { extend: function(options) { this.bind('image', function(e) { $(e.imageTarget).click(this.proxy(function() { this.openLightbox(); })); }); } }); // Extension adds pop-up on click functionality
  }
  h="<div>";
  // h+="<div class='singleGallery'><img src='../img/logo.png' /></div>";
  // h+="<div class='singleBlurb'>More information can be found at:</div>";
  if (reverse) h+=getHeading();
  if (reverse) h+="<h2>&#8657; Browse the box</h2>";
  h+="</div>";
  $("#boxInfo7").append(h);
  $("#boxNav7").append(n);

  lastID=id;
  // Automatically move to requested record
  if (!p("jpID") || p("jpID")==firstID) {
    if (reverse) resetLast(true); else resetFirst(true);
  } else {
    curID=p("jpID");
    moveTo(true); // Load the first box / go to the relevant blurb
  }
  $('#topLogo').stop(true,false).stop(true,false).animate({'top':0},1000);
  /*
  // Touch events
  if (inTouch) {
    // Start capturing touch events
    $(document).bind('touchmove', false);
    document.getElementById("boxNav7").addEventListener('touchstart', doTouchStart, false);
    document.getElementById("boxNav7").addEventListener('touchmove', doTouchMove, false);
    document.getElementById("boxNav7").addEventListener('touchend', doTouchEnd, false);
    // Annoying bug where iPad layout scales up when rotating from portrait to landcape. Keep the Markup scalable, then disable scalability with javascript until gesturestart
    var viewportmeta = document.querySelector('meta[name="viewport"]');
    window.addEventListener('orientationchange', function() { viewportmeta.content='width=device-width, minimum-scale=1.0, maximum-scale=1.0'; }, false);
    // Loose the address bar
    window.scrollTo(0, 1);
  }
  */
  // Kick off social
  twttr.widgets.load(); // Reload Twitter
  FB.XFBML.parse(); // Reload Facebook
  gapi.plusone.go(); // Reload G+
}

// Fired if the requested data was not available (means that this letter is currently out-of-bounds (we haven't actually photographed it yet most likely!) so loads the empty box background)
function unavailable() {
  var msg="";
  msg="'"+letter+"' is not currently available";
  msg+="<br /><span class='small'>This box is no longer available<br /><a href='index.html?letter="+letter+"'>&larr; back</a></span>";
  // Set the background image
  $("#bigMessage").html(msg);
  $("#bigMessage").fadeIn(2000);
  $('#topLogo').stop(true,false).stop(true,false).animate({'top':0},1000);
}
