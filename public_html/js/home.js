/* Author: Sam Parmenter, Tin @ S0c13ty.com */
// Detect touch-screen devices
var inTouch=('ontouchstart' in document.documentElement);
// Need a global var to store the initial centered containers negative margin.
var hiddenWidth = null;
var prevX=false, dx=false; // the difference in x between the last touch event and the current one
var mainViewWidth; // The width of the user's window
var contentBgWidth=1400; // The width of John's studio
var elbowRoom; // The difference between the two

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
  var curBgPos=parseInt($("#content").css('margin-left'),10);
  var newPos=Math.max(Math.min(curBgPos+dx,0),-1*elbowRoom);
  $("#content").css('margin-left', newPos+"px");
  // debug("touchmove ["+touchX+"] dx=["+dx+"] content is at ["+contentPos+"]");
}
var doTouchEnd=function(e) { dx=false; prevX=false; } // Note: x is not available to endtouch
var oldMarginLeft=0;
var targetMarginLeft=0;

// Mouse (desktop browsers)
function movemouse(e) {
	var hover = $('#mousecapture', this);
	// Overlap holds the amount that is hidden either side of the visible image.
	hiddenWidth = hiddenWidth || $("#content").css("margin-left");
	// Width of the active areas at either side of the page.
	var hoverWidth = hover.width();
	// Ratio is width of hidden area / 2 because we have 2 equally hidden sides.
	var ratio = -(parseInt(hiddenWidth) * 2) / hoverWidth;
	var offset = -e.clientX * ratio; // Mouse at far right (960px from left) * ratio of the two hidden areas to the width of the visible area gives us the negative left margin
	targetMarginLeft=parseInt(offset);
	// debug("Moving to "+targetMarginLeft+" (mouse is at "+e.clientX+")");
  moveContent();
  x = e.clientX; // Store current position
}

function moveContentAbsolute(newMarginLeft) {
  oldMarginLeft=newMarginLeft;
  $("#content").css('margin-left',parseInt(newMarginLeft)+'px');
}

// Move the content area directly towards it's target
function moveContent() {
  var gap=diff(targetMarginLeft, oldMarginLeft);
  var magnitude=(gap/100); // Speed can be controlled by adjusting how much magnitude is wrought from the num of pixels moved
  var direction=(targetMarginLeft>oldMarginLeft)?1:-1;
  var speed=(direction*magnitude); // Like Vector from DM...
  var newMarginLeft=oldMarginLeft+speed;
  // Are we going to overshoot?
  if ((newMarginLeft>=targetMarginLeft && targetMarginLeft>=oldMarginLeft) || (newMarginLeft<=targetMarginLeft && targetMarginLeft<=oldMarginLeft) || Math.abs(targetMarginLeft-newMarginLeft)<=1) {
    newMarginLeft=targetMarginLeft;
  }
  moveContentAbsolute(newMarginLeft);
	if (newMarginLeft==targetMarginLeft) {
	  // debug("Settled at "+newMarginLeft);
	} else {
	  setTimeout(function() { moveContent(targetMarginLeft); },25);
  }
}

function diff(a,b) { return (a>b)?(a-b):(b-a); }

/* end Mouse / touch handling */


var count=0;
function arrangePage() {
	var container = $("#main");
	mainViewWidth=container.width();
	// if container is large enough to accomodate the whole bg, set the container to contentBgWidth and center.
	if (mainViewWidth > contentBgWidth) {
		container.width(contentBgWidth);
		elbowRoom=0;
	} else {
		// Container width is auto normally but if the user has the screen at > contentBgWidth and then resizes, we need to reset the above static sizing.
		container.width('auto');
		// Add a negative margin to the background to center it due to the image being larger than the container.
		elbowRoom=contentBgWidth-mainViewWidth;
		var diff=-1*(elbowRoom/2);
		moveContentAbsolute(diff);
	}
	// check if this is the first time loading or perhaps someone changing browser width.
	if($("#content", container).is(":hidden")){ 
	  // Run once...
		$("#content", container).fadeIn(1000);
		$(window).resize(function() {	arrangePage(); });
	} else {
    // Run every time...
	}
	if (hiddenWidth && (hiddenWidth == $("#content").css("margin-left"))) return;
  hiddenWidth = $("#content").css("margin-left");
  if (inTouch) {
    $(document).bind('touchmove', false);
    // Start capturing touch events
    document.getElementById("main").addEventListener('touchstart', doTouchStart, false);
    document.getElementById("main").addEventListener('touchmove', doTouchMove, false);
    document.getElementById("main").addEventListener('touchend', doTouchEnd, false);
    // Annoying bug where iPad layout scales up when rotating from portrait to landcape. Keep the Markup scalable, then disable scalability with javascript until gesturestart
    var viewportmeta = document.querySelector('meta[name="viewport"]');
    window.addEventListener('orientationchange', function() { viewportmeta.content='width=device-width, minimum-scale=1.0, maximum-scale=1.0'; }, false);
    // Loose the address bar
    window.scrollTo(0, 1);
  } else {
  	$('#main').mousemove(movemouse);
  }
}

// Photo gallery
function popupGallery() {
  var p, d="img/photos/", gallery=[];
  // Create big and thumb references for Galleria
  for (i=0; i<jpData.photos.length; i++) {
    p=jpData.photos[i];
    gallery[i]={'image':d+p.image, 'thumb':d+"thumbs/"+p.image, 'big':d+"big/"+p.image}
    if (p.hasOwnProperty('title')) gallery[i].title=p.title;
    // h+="<a href='"+imgDir+photo.imgUrl+"'><img src='"+imgDir+"thumbs/"+photo.imgUrl+"' width='379' data-big='"+imgDir+"large/"+photo.imgUrl+"' "+((photo.hasOwnProperty('title'))?"data-description='"+photo.title+"'":"")+" /></a>";
  }
  Galleria.run('#popPhotos', {dataSource: gallery});
  $.fancybox({ href:'#photosPopUp', type:"inline", topRatio: 0, minWidth: 820, minHeight: 550, arrows: true } );
}

// ON-LOAD
$(document).ready(function(){
  arrangePage();
  // Animate in the overlays
  $('.fb').fancybox({height: '500px', width: '900px', autoSize: false  });
  $(".overlay").hover(function() { $('#'+$(this).attr('id')+'-h').fadeIn(750); }, function() { $('#'+$(this).attr('id')+'-h').fadeOut(750); });
  $(".overlay").each(function() { $(this).delay(Math.random() * 3500).animate({'opacity':1},3500); });
  // Load pop-up data from JSON data files...
  $("#popSessions").html(doSessions(jpData));
  $("#popSessionTracks").html(doSessionTracks(jpData));
  $("#popRadio").html(doRadio(jpData,'img/'));
  $("#popFeaturedVideos").html(doVideos(jpData,'featured'));
  $("#popHomeMovies").html(doVideos(jpData,'homeMovies'));
  $("#popFootball").html(doVideos(jpData,'football'));
  $("#popSessionTracks").html(doSessionTracks(jpData,'img/'));
  $(".playHover").hover(function(){ $(this).children('.playBtn').fadeIn(); }, function(){ $(this).children('.playBtn').fadeOut(); });
  $(".videoHover").hover(function(){ $(this).children('.videoBtn').fadeIn(); }, function(){ $(this).children('.videoBtn').fadeOut(); });
  // Configure photo gallery
  Galleria.configure({imageCrop: false,  width: 800, height: 500, transition: 'slide'});
  Galleria.loadTheme('js/libs/themes/classic/galleria.classic.min.js');
});

