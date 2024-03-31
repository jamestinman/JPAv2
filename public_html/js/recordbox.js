if (supportsHistory()) {
  // Enable back/fwd btns without changing page 
  $(window).bind("popstate", function(evt) { // history API fires 'popstate's on back-btn press
    var state=evt.originalEvent.state;
    if (state) {
      console.log("Popping state (back from "+curID+" to "+state.jpID+")");
      if (state.jpID!=curID) { 
        curID=state.jpID; //doAlbumPop();
      }
    }
  });
}

var overlay;
var colour="#f6d404";
var tracing=false, svgPath="", mX=0, mY=0, traceCount=0;
var oLookup=[];
var oldID=false, startID=false, curID=false, clickID=false, endID=false; // touchevent tracking vars
var firstID=false, lastID=false; // first and last in this box
// Detect touch-screen devices
var inTouch=('ontouchstart' in document.documentElement);
var availability={
  'A': {d:'available', title:"1st May 2012"},
  'B': {d:'available', title:"8th May 2012"},
  'C': {d:'available', title: "15th May 2012"},
  'D': {d:'available', title: "22nd May 2012"},
  'E': {d:'available', title: "29th May 2012"},
  'F': {d:'available', title: "5th June 2012"},
  'G': {d:'available', title: "12th June 2012"},
  'H': {d:'available', title: "19th June 2012"},
  'I': {d:'available', title: "26th June 2012"},
  'J': {d:'available', title: "3rd July 2012"},
  'K': {d:'available', title: "10th July 2012"},
  'L': {d:'available', title: "17th July 2012"},
  'M': {d:'available', title: "24th July 2012"},
  'N': {d:'available', title: "31st July 2012"},
  'O': {d:'available', title: "7th August 2012"},
  'P': {d:'available', title: "13th August 2012"},
  'Q': {d:'available', title: "21st August 2012"},
  'R': {d:'available', title: "28th August 2012"},
  'S': {d:'available', title: "3rd September 2012"},
  'T': {d:'available', title: "11th September 2012"},
  'U': {d:'available', title: "17th September 2012"},
  'V': {d:'available', title: "24th September 2012"},
  'W': {d:'available', title: "1st October 2012"},
  'X': {d:'available', title: "8th October 2012"},
  'Y': {d:'available', title: "15th October 2012"},
  'Z': {d:'available', title: "22nd October 2012"},
  '1': {d:'available', title: "22nd February 2012"}
}
// FUNCTIONS
// Touch
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
var doTouchEnd=function(e) { doAlbumPop(); } // Note: x is not available to endtouch

// Find which overlay to highlight based on the x position of the touch event
// Each 100 spines has entry in oLookup array, with x pos & id, e.g. oLookup[50]={x:456,id:A048}
function getIDfromX(x) {
  // Hunting can be simply sequential (bombing out when it finds the first entry with an x bigger than where the finger is)
  // or using a quicksearch style recursive algorithm for the rest of the pack
  var allowSequential=false;
  if (allowSequential && x<480) { // Use sequential search for left hand side?
    for(i in oLookup) { if (oLookup[i].x>x) return oLookup[i].id; }
    return false;
  } else {
    var i=findClosestOverlay(x-50,1,100);
    return oLookup[i].id;
  }
}

// Quick search algorithm recursively homes in on closest target
function findClosestOverlay(x,a,b) {
  if (a>=b) return a;
  var mid=Math.round(a+((b-a)/2));
  if (oLookup[mid].x==x) return mid;
  else if (x>oLookup[mid].x) return findClosestOverlay(x,mid+1,b);       
  else return findClosestOverlay(x,a,mid-1);
}

// get time in ms since the page was opened/reset
function createOverlays() {
  overlay.clear(); // Clear the paper
  // Create overlays
  n=0;
  for (var id in records) {
    if (!firstID) firstID=id;
    records[id].raph=overlay.path(records[id].highlightPath).attr({'stroke': '#000', 'stroke-width': 0, 'opacity': 0, 'fill': colour});
    records[id].raph.id=id;
    if (inTouch) {
      // Touch devices annoyingly do not report the correct x position in touchstart/touchend events if they happen quickly after each other (and trigger and click event instead)
      // records[id].raph.node.addEventListener('click', doTouchClick, false);
    } else {
      records[id].raph.click(function() { doAlbumPop(); });
      records[id].raph.mouseover(function() { if (!tracing) showTab(this.id); });
    }
    var x=records[id].highlightPath.slice(1,records[id].highlightPath.indexOf(','));
    oLookup[++n]={x:x,id:id};
  }
  lastID=id;
}

function showTab(id) {
  curID=id;
  highlightSpine(id);
  r=records[id];
  var x=r.highlightPath.substr(1,r.highlightPath.indexOf(',',1)-1);
  // Populate the tab
  $("#theTab").css("display","block");
  // $("#theTab").css("top",y+"px");
  if (x<600) {
    $("#theTab").css("left",x+"px");
    $("#tabArtist").css({"left":"20px","right":""});
    $("#yellowTriangle").css({"left":"1px", 'background-image': "url(../img/yellow-triangle.png)"}); 
  } else {
    $("#theTab").css("left","");
    $("#theTab").css("right",(960-x)+"px");
    $("#tabArtist").css({"left":"","right":"16px"});
    $("#yellowTriangle").css({"left":"","right":"-3px", 'background-image': "url(../img/yellow-tright.png)"}); 
  }
  $("#tabArtist").html(r.artist.toUpperCase());
  $("#tabAlbum").html(r.album);
  $("#tabCardNum").html(id+"<br />Card No. "+r.card);
  var thumbUrl="img/"+letter+"/thumbs/"+id+"-"+lpad(r.card,'0',5)+"-1.jpg"
  $("#tabThumb").attr("src",thumbUrl);
  // $("#theTab").html("<h2>"+records[id].artist+"</h2>");
  // $("#theTab").show();
}

// Highlight the spine (and un-highlight the old spine)
function highlightSpine(id) {
  if (typeof Raphael=="undefined") return false;
  if (oldID) records[oldID].raph.attr({'opacity':0});
  // console.log("switching "+id);
  records[id].raph.attr({'opacity':1});
  oldID=id;
}

function triggerOverlay(x) {
  
}

// DEBUG / BUILD FUNCTIONS
// Build an SVG path out of mouse clicks
function runTrace() {
  if (tracing) {
    tracing=false;
    traceCount=0;
    if (svgPath.length>0) svgPath+="z";
    $('#thePath').html(svgPath);
    $('#topLogo').stop(true,false).stop(true,false).animate({'top':0},1000);
    $('#traceBtn').html('Start trace');
  } else {
    svgPath="";
    $('#thePath').html("");
    $('#traceBtn').html('Stop trace');
    tracing=true;
    hideTab();
    $('#topLogo').stop(true,false).stop(true,false).animate({'top':-36},1000);
    $('#theLetterShelf').bind({'click': function() { if (tracing) { svgPath+=((svgPath.length==0)?"M":"L")+mX+","+mY; $('#thePath').html(svgPath); if (++traceCount>3) runTrace(); }}});
  }
}

// ON-LOAD
$(document).ready(function(){
  // Pick up information about what we are displaying from the URL params
  if (p("jpID")) {
    curID=p("jpID");
    letter=curID.charAt(0);
  } else if (p("letter")) {
    letter=p("letter");
    document.title="First 100 Vinyl LPs starting with '"+letter+"' : John Peel Archive";
  } else {
    letter="A";
  }
  var available=true;
  // Is this letter available yet?
  if (!availability.hasOwnProperty(letter)) {
    available=false;
  } else if (availability[letter].d!='available') {
    var dNow=new Date();
    var dNum=dNow.getFullYear()+""+lpad(dNow.getMonth()+1,'0',2)+""+lpad(dNow.getDay()+1,'0',2);
    if (parseInt(dNum,10)<parseInt(availability[letter].d,10)) available=false;
  }
  if (available) {
    // Load the data for this part of the collection
    datafile="../data/records/"+letter+".js";
    $.getScript(datafile).done(function(){ initialise(); }).fail(function() { unavailable(); });
  } else {
    unavailable();
  }
  
});

// Fired once the data has loaded
function initialise() {
  // Set the background image
  $("#theLetterShelf").css('background-image','url(img/boxes/'+letter+'.jpg)');
  // RAPHAEL: Create the Raphael overlay
  // this part removed when Raphael not found so that this file can be used by Record Boxes also
  if (typeof Raphael!="undefined") {
    overlay=Raphael('theLetterShelf',960,640);  
    createOverlays();
  }
  if (p("allowTrace")) $('body').append("<button id='traceBtn' onClick='runTrace();'>Start Trace</button><div id='thePath'></div>");
  // Capture mouse movement x & y pos
  $("#theLetterShelf").mousemove(function(e){
    mX=e.pageX-this.offsetLeft;
    mY=e.pageY-this.offsetTop;
    if (p("allowTrace")) $('#debug').html(mX +', '+ mY);
  });
  $('#topLogo').stop(true,false).stop(true,false).animate({'top':0},1000);
	initialiseGalleria();
	// Auto pop-up requested record - don't do this now we're going to record pages
  //if (p("jpID")) doAlbumPop();
  // Touch events
  if (inTouch) {
    // Start capturing touch events
    $(document).bind('touchmove', false);
    document.getElementById("theLetterShelf").addEventListener('touchstart', doTouchStart, false);
    document.getElementById("theLetterShelf").addEventListener('touchmove', doTouchMove, false);
    document.getElementById("theLetterShelf").addEventListener('touchend', doTouchEnd, false);
    // Annoying bug where iPad layout scales up when rotating from portrait to landcape. Keep the Markup scalable, then disable scalability with javascript until gesturestart
    var viewportmeta = document.querySelector('meta[name="viewport"]');
    window.addEventListener('orientationchange', function() { viewportmeta.content='width=device-width, minimum-scale=1.0, maximum-scale=1.0'; }, false);
    // Loose the address bar
    window.scrollTo(0, 1);
  }
}

// Fired if the requested data was not available (means that this letter is currently out-of-bounds (we haven't actually photographed it yet most likely!) so loads the empty box background)
function unavailable() {
  var msg="";
  if (availability.hasOwnProperty(letter)) {
    msg="Letter '"+letter+"' will be available on "+availability[letter].title;
  } else {
    msg="'"+letter+"' is not currently available";
  }
  msg+="<br /><span class='small'>The first 100 of each letter of John's collection will be released one a week from 1st May 2012 to 1st Oct 2012<br /><a href='index.html?letter="+letter+"'>&larr; back</a></span>";
  // Set the background image
  $("#theLetterShelf").css('background-image','url(img/boxes/empty.jpg)');
  $("#bigMessage").html(msg);
  $("#bigMessage").fadeIn(2000);
  $('#topLogo').stop(true,false).stop(true,false).animate({'top':0},1000);
}

function showHidePrevNext() {
  if (curID==firstID) { $("#prevBtn").hide(); } else { $("#prevBtn").show(); }
  if (curID==lastID) { $("#nextBtn").hide(); } else { $("#nextBtn").show(); }
}

function goPrev() {
  // var i=isNaN(curID)?curID.replace(/\D/g,''):curID;
  var i=curID.substr(1); // D001 => 001
  console.log("curID="+curID+" whereas i="+i);
  oldID=curID;
  if (--i<1) i=1;
  curID=letter+lpad(i,'0',3);
  prepareAlbumInfo(curID);
  highlightSpine(curID);
  showHidePrevNext();
}

function goNext() {
  // var i=isNaN(curID)?curID.replace(/\D/g,''):curID;
  var i=curID.substr(1);
  oldID=curID;
  if (++i>100) i=100;
  curID=letter+lpad(i,'0',3);
  prepareAlbumInfo(curID);
  highlightSpine(curID);
  showHidePrevNext();
}

function doAlbumPop() {
  console.log('doAlbumPop');
  highlightSpine(curID);
  popupAlbumInfo();
}
