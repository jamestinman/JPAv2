// Detect touch-screen devices
var inTouch=('ontouchstart' in document.documentElement);
// Internal vars
var shelves=[];
var shelfNum=0;
var sleeveCounter1=0; sleeveCounter2=0;
var shelf={active: false, duration:2000, direction:1 };
var numShelves=0;
var overlays=[]; // Contains the current overlaid DOM elements
var sleeves=[]; // Contains the current pull-out records
var sleeveFPS=10;
var start=Date.now();  
var fadeCount=0;
var prevX=false, dx=false; // the difference in x between the last touch event and the current one
var tracing=false, svgPath="", x=0, y=0, traceCount=0; // Debug vars...
var viewWidth=960;
var viewHeight=640;
var aspect=viewWidth/viewHeight;
var overlayPaper,c,shelfImg;
var y, fullWidth, fullHeight, xScale, finalWidth, finalLeft;
var tweens={}

// FUNCTIONS
// Touch (mobile browsers)
var doTouchStart=function(e) {
  var touchX=e.touches[0].pageX-this.offsetLeft;
  prevX=touchX;
}
var doTouchMove=function(e) {
  e.preventDefault();
  var touchX=e.touches[0].pageX-this.offsetLeft;
  dx=touchX-prevX;
  prevX=touchX;
}
var doTouchEnd=function(e) { if (dx>0) backward(); else forward(); dx=false; prevX=false; } // Note: x is not available to endtouch
// get time in ms since the page was opened/reset
function getT() { return Date.now()-start; }
// get the Relative Time an object has been animating for since startT
function getRT(startT) { return (getT()-startT); }

// Kick off and control the forward animation (moving the shelves to the left)
function forward() {
  if (shelfNum>=numShelves-1) return false;
  // 1. Kick off fade
  fadeOutOverlay();
  // 2. Switch out 960x640 for animatable image
  activate("#theShelf"+shelf.shelfType);
  // 3. move hidden image to starting position ready to swap to at end of transition
  renderShelf('#theShelf'+shelf.otherShelfType,0,1);
  shelfNum++;
  shelf.direction=1;
  if (!inTouch) {
    $('#leftNav').css('display','block');
    if (shelfNum>=numShelves-1) $('#rightNav').css('display','none');
  }
  // 4. Shelf animation automatically kicked off as part of fadeOutOverlay (once all have faded)
  // 5. build overlays, 5. fade-in letters, 6. switch to hidden image

  // Dynamically change URL
  history.pushState({'shelfNum':shelfNum}, "A-Z Vinyl LPs: John Peel Archive", "index.html?shelfNum="+shelfNum);

}

function backward() {
  if (shelfNum<=0) return false; 
  fadeOutOverlay();
  shelfNum--;
  shelf.direction=-1;
  switchShelf();
  activate("#theShelf"+shelf.shelfType);
  if (!inTouch) {
    if (shelfNum==0) $('#leftNav').css('display','none');
    $('#rightNav').css('display','block');
  }
  // Shelf animation automatically kicked off as part of fadeOutOverlay (once all have faded)

  // Dynamically change URL
  history.pushState({'shelfNum':shelfNum}, "A-Z Vinyl LPs: John Peel Archive", "index.html?shelfNum="+shelfNum);

}

function switchShelf() { // Switches active shelf to the current shelfNum and swaps active image
  shelf.shelfType=shelves[shelfNum].shelfType;
  shelf.otherShelfType=(shelf.shelfType=='A')?'B':'A';
}
var possibleShelves=["#theShelfStaticA","#theShelfStaticB","#theShelfA","#theShelfB"];
function activate(whichShelf) {
  for (i in possibleShelves) {
    shelfID=possibleShelves[i];
    var setting=((whichShelf==shelfID)?"block":"none");
    $(shelfID).css('display',setting);
  }
}
function pullOutSleeve(i) {
  // Is this sleeve already being pulled out?
  if (!shelf.active) {
    sleeves[i].startT=getT();
    sleeves[i].direction=1;
    goAnimate();
  }
}
function pushInSleeve(i) {
  // Is this sleeve already slotted back in?
  sleeves[i].startT=getT();
  sleeves[i].direction=-1; // Make sure it's going in the right direction (even if it's already active)
  goAnimate();
}

function fadeOutOverlay() {
  // Fade the letters and, once all faded, kick off the scroll animation
  fadeCount=0;
  for (var letter in overlays) {
    fadeCount++;
    overlays[letter].raph.txt.attr({'opacity':1}).stop(true,false).animate({'opacity':0}, 1000, '<', function() {if (--fadeCount<=0) { overlayPaper.clear; startAnimate(); }});
  }
}
function getSleeveFrame(i) {
  var rt=getRT(sleeves[i].startT);
  var f=Math.min(Math.max(Math.floor((rt*sleeveFPS)/1000)+1,0),4);
  f=(sleeves[i].direction==1)?f:4-f;
  return f;
}

function highlightLetter(letter,on) {
  overlays[letter].raph.attr({'opacity':((on)?0.2:0)}); overlays[letter].raph.txt.attr({'opacity':((on)?1:0.7)});
}
function fadeInOverlay() {
  overlayPaper.clear(); // Clear the paper
  // Create overlays
  overlays=shelves[shelfNum].letters;
  sleeves=shelves[shelfNum].sleeves;
  for (var letter in overlays) {
    overlays[letter].raph=overlayPaper.path(overlays[letter].path).attr({'stroke': '#000', 'stroke-width': 0, 'opacity': 0, 'fill': overlays[letter].colour});
    // Letters - this line also animates opacity to fade them in
    overlayFont=(inTouch)?'Arial':'Arial Black';
    overlays[letter].raph.txt=overlayPaper.text(overlays[letter].letterPos.x,overlays[letter].letterPos.y,letter).attr({'font-family':overlayFont,'fill':'#ffed00', 'font-size':overlays[letter].fontSize,'opacity':0}).stop(true,false).animate({'opacity':0.7}, 500+(2500*Math.random()), '<');
    overlays[letter].raph.letter=letter;
    overlays[letter].raph.txt.letter=letter;
    overlays[letter].raph.txt.attr({'cursor':'pointer'});
    var linkToLetter=function() { document.location.href='recordbox.html?letter='+this.letter; }
    overlays[letter].raph.hover(function() { highlightLetter(this.letter, true); }, function() { highlightLetter(this.letter, false); } );
    overlays[letter].raph.click( linkToLetter );
    overlays[letter].raph.txt.hover(function() { highlightLetter(this.letter, true); }, function() { highlightLetter(this.letter, false); } );
    overlays[letter].raph.txt.click( linkToLetter );
  }
  // Create the pull-out sleeves
  if (sleeves) {
    for (var i=0; i<sleeves.length; i++) {
      s=sleeves[i];
      sleeves[i].startT=0;
      sleeves[i].raph=overlayPaper.image('img/blank1x1.png',s.left,s.top,1,1);
      sleeves[i].raph.i=i;
      sleeves[i].raph.letter=sleeves[i].letter; // For future reference
      sleeves[i].raph.jpID=s.jpID;
      sleeves[i].raph.hover(function() { highlightLetter(this.letter, true); }, function() { highlightLetter(this.letter, false); } );
      sleeves[i].raph.click( function() { document.location.href='recordbox.html?jpID='+this.jpID; } );
      var x=s.left+s.width-82;
      var y=s.top+33;
      var xOff=x-s.triggerOffsetX;
      sleeves[i].o=overlayPaper.path("M"+(xOff)+","+(y)+"L"+(x)+","+(y)+"L"+(x)+","+(y+s.height-60)+"L"+(xOff)+","+(y+s.height-60)+"z").attr({'stroke': '#000', 'stroke-width': 1, 'opacity': 0, 'fill': '#fca'});
      sleeves[i].o.i=i;
      sleeves[i].o.letter=sleeves[i].letter;
      sleeves[i].o.jpID=s.jpID;
      sleeves[i].o.hover(function() { highlightLetter(this.letter, true); pullOutSleeve(this.i); }, function() { highlightLetter(this.letter, false); pushInSleeve(this.i); } );
      sleeves[i].o.click( function() { document.location.href='recordbox.html?jpID='+this.jpID; } );
    }
  }
}

function startAnimate() {
  shelf.active=true;
  shelf.startT=getT();
  goAnimate();
}
// Control animation using time rather than browser timeout vagaries
// ANIMATION LOOP
// --------------
function goAnimate() {
  var retrigger=false; // Assume we're stopping. Anything still running will keep it going
  var f, shelfPos; // The frame for this animation and position of the shelf at that frame
  // SLEEVE Animation
  // Loop over sleeves currently being animated
  for (var i=0; i<sleeves.length; i++) {
    if (sleeves[i].direction!=0) {
      // Which frame is this sleeve up to?
      f=getSleeveFrame(i);
      if (sleeves[i].direction==-1 && f<=0) {
        // Now closed
        sleeves[i].direction=0;
      } else if (sleeves[i].direction==1 && f>=4) {
        // Fully open, stop it.
        sleeves[i].direction=0;
      } else if (f>0 && f<4) {
        retrigger=true;
      }
      renderSleeve(i,f);
    }
  }
  // SHELF Animation
  if (shelf.active) { // only allow shelf to animate once all sleeves are put back in
    f=getRT(shelf.startT);
    renderShelf('#theShelf'+shelf.shelfType,f,shelf.direction);
    if (f>=0 && f<=shelf.duration) {
      retrigger=true;
    } else {
      // END-OF-ANIMATION
      finishAnimate(f);
    }
  }
  if (retrigger) setTimeout(goAnimate,50);
}
// END of animation - fade in letters and tidy up
function finishAnimate() {
  shelf.active=false;
  // 1. fade-in text (and build overlays)
  fadeInOverlay();
  if (shelf.direction==1) {
    // If we've gone forward switch to the alt image (which should already be at position 0)
    switchShelf();
  } else {
    // If we've gone backward, get the other shelf image ready for the next run (make it large)
    renderShelf('#theShelf'+shelf.otherShelfType,shelf.duration,1);
  }
  // Switch in the static 960x640 shelf image to prevent iPad from downgrading quality of transition image
  activate("#theShelfStatic"+shelf.shelfType);
}

// Easing maths functions
var easeInOutQuad = function (x, start, end, duration) {
  var change=end-start;
  x /= duration/2;
  if (x < 1) return change/2*x*x + start;
  x--;
  return -change/2 * (x*(x-2) - 1) + start;
};
/*
  fakeT[0]=f;
  fakeT[1]=((f/duration)*(f/duration))*duration; // follow square trajectory e.g. fT=t*t if t went from 0=>1
  fakeT[2]=Math.sqrt(f/duration)*duration; // follow inverse square trajectory e.g. fT=root(t) if t went from 0=>1
*/
// Render the given shelf at the given frame
function renderShelf(shelfID,f,direction) {
  var duration=shelf.duration; // the duration of a full transition (in frames)
  var fakeT=[];
  f=Math.max(Math.min(duration,f),0);
  if (f<=duration) {
    var val=0;
    var x=0;
    for (var attrib in tweens) {
      tween=tweens[attrib];
      if (direction==1) {
        val=easeInOutQuad(f,tween.start,tween.end,duration);
      } else {
        val=easeInOutQuad(f,tween.end,tween.start,duration);
      }
      if (attrib=='width') x=val;
      // shelfImg.attr(attrib,val);
      $(shelfID).css(attrib,''+(val)+'px');
    }
  }
}

// PULL-OUT SLEEVES
// Render a given sleeve (i) at frame (f 1->4)
function renderSleeve(i,f) {
  if (f==0) {
    sleeves[i].raph.attr('src','img/blank1x1.png');
    sleeves[i].raph.attr('width',1);
    sleeves[i].raph.attr('height',1);
  } else {
    sleeves[i].raph.attr('src',sleeves[i].startOfUrl+f+'.png');
    sleeves[i].raph.attr('width',sleeves[i].width);
    sleeves[i].raph.attr('height',sleeves[i].height);
  }
  /*
  if (f==0) $('#s'+i).css({'background-image':'url()'});
  if (f<=4) $('#s'+i).css({'background-image':'url('+sleeves[i].startOfUrl+f+'.png)'});
  */
}

// DEBUG / BUILD FUNCTIONS
// Build an SVG path out of mouse clicks
function runTrace() {
  if (tracing) {
    tracing=false;
    traceCount=0;
    if (svgPath.length>0) svgPath+="z";
    $('#thePath').html(svgPath);
    $('#topLogo').stop(true,false).animate({'top':0},1000);
    $('#traceBtn').html('Start trace');
    fadeInOverlay();
    $('#leftNav').css('display','block');
    $('#rightNav').css('display','block');
  } else {
    svgPath="";
    $('#thePath').html("");
    $('#traceBtn').html('Stop trace');
    tracing=true;
    overlayPaper.clear();
    $('#topLogo').stop(true,false).animate({'top':-36},1000);
    $('#theShelves').bind({'click': function() { if (tracing) { svgPath+=((svgPath.length==0)?"M":"L")+x+","+y; $('#thePath').html(svgPath); }}});
    $('#leftNav').css('display','none');
    $('#rightNav').css('display','none');
  }
}

// ON-LOAD
$(document).ready(function(){

  // Note: shelfType should be defined in the shelf html file
  shelves=shelfDef[shelfType]; // All (4) shelves
  for (var shelfI in shelves) numShelves++; // .length does not work for JSON objects (only arrays)

  // Have we been asked to start at a particular shelf (or letter)?
  if (p("shelfNum")) {
    shelfNum=parseInt(p("shelfNum"));
  } else if (!inTouch && p("letter")) {
    for (tmpShelfNum in shelves) {
      for (tmpLetter in shelves[tmpShelfNum]['letters']) {
        if (tmpLetter==p("letter")) { shelfNum=parseInt(tmpShelfNum); }
      }
    }
  }
  shelf.shelfType=shelves[shelfNum].shelfType;
  shelf.otherShelfType=(shelf.shelfType=='A')?'B':'A';

  y=(shelfType=='12s')?743:0; // the distance between the top of the image and the top of the first shelf
  if (shelfType=='12s') {
    fullWidth=4270; // 5100;
    fullHeight=2207; // 2826;
    xScale=((fullWidth-viewWidth)/viewWidth); // e.g. 4.3125
    finalWidth=fullWidth/xScale;
    finalLeft=viewWidth/xScale;
  } else {
    fullWidth=1920;
    finalWidth=1920;
    fullHeight=viewHeight;
    xScale=1; // No 3D effect for 7" shelves
    finalLeft=viewWidth;
  }

  // Tween type 0=linear, 1=slow-in (square), 2=slow-out (sqrt)
  tweens={
    'top': { start: -y, end: 0 }, // e.g. top goes from -y to 0
    'left': { start: 0, end: -1*finalLeft}, // e.g. left goes from 0 to -222
    'height': { start: fullHeight, end: viewHeight },
    'width': { start: fullWidth, end: finalWidth }
  }


  // RAPHAEL: Create the Raphael overlay
  overlayPaper=Raphael('theOverlay',960,640);
  // Load the shelf animations in the background
  $("#theShelf").append("<img id='theShelfStaticA' src='img/shelfA.jpg' />");
  $("#theShelf").append("<img id='theShelfStaticB' src='img/shelfB.jpg' />");
  $("#theShelf").append("<img id='theShelfA' class='aShelf' src='img/Small-1_Large-2.jpg' />");
  $("#theShelf").append("<img id='theShelfB' class='aShelf' src='img/Small-2_Large-1.jpg' />");
  renderShelf('#theShelf'+shelf.shelfType,0,1); // set a starting position
  renderShelf('#theShelf'+shelf.otherShelfType,shelf.duration,1); // set a starting position
  // switchShelf();
  activate("#theShelfStatic"+shelf.shelfType);
  fadeInOverlay();
  if (p("allowTrace")) {
    $('body').append("<button id='traceBtn' onClick='runTrace();'>Start Trace</button><div id='thePath'></div>");
    $("#theShelves").mousemove(function(e){
      x = e.pageX - this.offsetLeft;
      y = e.pageY - this.offsetTop;
      debug(x +', '+ y);
    });
  }
  // Touch events
  if (inTouch) {
    // Start capturing touch events
    $(document).bind('touchmove', false);
    document.getElementById("theShelves").addEventListener('touchstart', doTouchStart, false);
    document.getElementById("theShelves").addEventListener('touchmove', doTouchMove, false);
    document.getElementById("theShelves").addEventListener('touchend', doTouchEnd, false);
    // Annoying bug where iPad layout scales up when rotating from portrait to landcape. Keep the Markup scalable, then disable scalability with javascript until gesturestart
    var viewportmeta = document.querySelector('meta[name="viewport"]');
    window.addEventListener('orientationchange', function() { viewportmeta.content='width=device-width, minimum-scale=1.0, maximum-scale=1.0'; }, false);
    // Loose the address bar
    window.scrollTo(0, 1);
  } else {
    // Add left / right navs...
    $("#nav").append("<div id='leftNav' class='hoverAppear'></div><div id='rightNav' class='hoverAppear'></div>");
    if (shelfNum==0) { $('#leftNav').css('display','none'); } else { $('#leftNav').css('display','block'); }
    if (shelfNum>=numShelves-1) { $('#rightNav').css('display','none'); } else { $('#rightNav').css('display','block'); }
    $('.hoverAppear').hover(function() { $(this).stop(true,false).animate({"opacity": 1}); }, function() { $(this).stop(true,false).animate({"opacity": 0.25}); });
    $("#leftNav").click(function() {backward();});
    $("#rightNav").click(function() {forward();});
    $('#topLogo').stop(true,false).animate({'top':0},2000);
  }
  // Cheekily peek first part of sleeve out of shelf
  sleeveCounter1=0; sleeveCounter2=0;
  window.setTimeout('chainSleevesOut()',500);
  // if (inTouch) window.setTimeout('cheekyPop()',3500);
});

function chainSleevesOut() { pullOutSleeve(sleeveCounter1);  if (++sleeveCounter1<sleeves.length) { setTimeout('chainSleevesOut()',50); } else { setTimeout('chainSleevesIn()',1000); } }
function chainSleevesIn() { pushInSleeve(sleeveCounter2);  if (++sleeveCounter2<sleeves.length) setTimeout('chainSleevesIn()',250); }
function pullOutSleeves() { for (var i=0; i<sleeves.length; i++) { pullOutSleeve(i); } }
function pushInSleeves() { for (var i=0; i<sleeves.length; i++) { pushInSleeve(i); } }
function cheekyPop() { for (var i=0; i<sleeves.length; i++) { renderSleeve(i,1); } }
