var allowDebug=false;

function trace(h) { if (allowDebug) $("#dbugInner").append("<br />"+h); }
// Get percentage given element is through page. Returns value between 0 and 1
function getPercThroughPage(elm) {
	var docTop=$(document).scrollTop(); // docTop goes upwards from 0...
	var posTop=$(elm).offset().top;
	var imgHeight=$(elm).find("img").height();
	var topYpos=gPageHeight-(posTop-docTop); // 0=bottom of page
	var distance=gPageHeight;
	// if (posTop<gPageHeight) var distance=imgHeight+posTop;
	var distanceAlreadyCovered=docTop-(posTop-gPageHeight);
	var percThroughPage=distanceAlreadyCovered/distance;
	return percThroughPage;
}

// Globals
var gPageHeight=0;
var gPageWidth=0;
var gParallaxSpeed=0.5; // Tweak between 0 and 1 for slower/faster
var iPhone=false;
var phi=0
var jp3d={};
var jp3dRenderer=[];
var sCount=0;
var deviceType=0; // 1=modern desktop, 2=Android+Safari, 3=iOS, 0=Unknown/Blackberry etc
var oldIE=false; // notifies if we need to totally switch off nearly everything and run and hide
var threeJSLoaded=false; // dynamically loading this if it's going to be used (it's big, it causes errors) - this ensures we only add it once to get rid of any conflict nonsense etc
var transformClasses={0:"noTransform", 1:"jsTransform", 2:"css3transform", 3:"noTransform"}
var planeFragmentShader=false; // will get properly initialised in initialiseShader()
// JSON structure holding fade in / out points

// Setup
$(document).ready(function() {
		// ie fix...
		if(!window.console) {console={}; console.log = function(){};}

		// Determine the capability of the platform/browser
		$(window).resize(function() { doResize(); });
		if (/iPad|iPod/i.test(navigator.userAgent)) {
			deviceType=3;
		} else if (/iPhone/i.test(navigator.userAgent)) {
			deviceType=3;
			iPhone=true;
		} else if (/iMSIE|Trident/i.test(navigator.userAgent)) {
			deviceType=3;
			iPhone=true;
		} else if (/Android|webOS|IEMobile/i.test(navigator.userAgent)) {
			deviceType=2;
		} else if (/Chrome|Firefox|Trident\/7.0/i.test(navigator.userAgent)) { // IE11 (but no Safari)
			deviceType=1;
		} else if (/Safari/i.test(navigator.userAgent)) {
			deviceType=2;
		}

		if ($('html').hasClass('lt-ie7')) {
			oldIE=true;
			conLog('IE6');
		} else if ($('html').hasClass('lt-ie8')) {
			oldIE=true;
			conLog('IE7');
		} else if ($('html').hasClass('lt-ie9')) {
			oldIE=true;
			conLog('IE8');
		}

		if (!oldIE && deviceType==1 && threeJSLoaded==false) {
			loadThreeJS();
		}
		//deviceType=3; // for testing
		//console.log("deviceType is "+deviceType+": "+navigator.userAgent);

		//initialiseGalleria(); // can get rid of this once we start using record pages properly.
		activateJSStuff(); // moved to separate function below for re-use

		setupPlayer();

		// Create debug area?
		if (allowDebug) {
			$("#header").append("<div id='dbug'><div id='dbugInner'>OK - this is a debug area</div></div>");
			$("#dbug").css({position: 'fixed',top:0, right:0, width: '225px', height: '60px', 'background-color':'#ccc', 'z-index': 1100});
			$("#dbugInner").css({position: 'absolute',left:0, bottom:0, width: '125px',color: '#000', font: '12px Courier'});
		}

		// 3..2..1..
		var tmpTimeout=setTimeout(function() { start(); },250);
});

// three crashes IE, so let's only load if needed....
function loadThreeJS() {
	//threeJSLoaded=true;
	var src="/js/boxes/three.min.js";
	var s = document.createElement( 'script' );
	s.setAttribute( 'src', src );
	s.onload=function() {
		conLog('three.js is loaded now');
		threeJSLoaded=true;
		initialiseShader();
	}
	document.body.appendChild( s );

	var src="/js/boxes/ThreeCSG.js";
	var s = document.createElement( 'script' );
	s.setAttribute( 'src', src );
	document.body.appendChild( s );
}

// remove a bunch of events whilst reloading is happening to stop unusual behaviour as page is fed back in....
function decimateJSStuff() {
	$('.parallax').each(function() { });
	$(".record").scrollfire({ });
	$(".parallax").scrollfire({ });
	$(".fadeInOut").scrollfire({ });
	$(".css3transform").scrollfire({ });
	$(".css3transform").scrollfire({ });
}

// separated so this stuff can be re-activated on ajax reloads...
function activateJSStuff() {
	// Add classes to control type of 3D transform
	//console.log('activateJSStuff');
	$(".sleeve3d").addClass(transformClasses[deviceType]);
	if (threeJSLoaded) initialiseShader();

	// Kill parallax for iOS (it does not allow DOM updates mid-scroll)
	if (deviceType==3) {
		$(".parallax").each(function() {
			if ($(this).hasClass('recordHead')) {
				$('.recordHead.headimage').css({'height':'200px'});
				$('.recordHead.headimage img').css({'top':'-40%'});
				$('.recordHead.headimage').animate({'opacity':1},500);
			} else {
				img=$(this).find("img");
				$(this).removeClass("parallax");
				img.load(function() {
					var imgHeight=$(this).height();
					//console.log(imgHeight);
					$(this).parent().css({height:imgHeight*1});
				})
			}
		});
	}

	// run when the browser back button is pressed
	// asks DB what new URL is, if it's different, loads that content
	$(window).bind('popstate', function(){
		callServer("getJPABoxID", {"url":window.location.href}, function(dat) {
			if (dat.rc>0) {
				if (dat.jpaBoxID!==false && dat.jpaBoxID!=jpaBoxID) {
					conLog('Going back to different page, '+dat.jpaBoxID);
					changeBox(dat.pageDetails);
				} else {
					conLog('Already on page '+jpaBoxID);
				}
			}
		})
	});

	$(".record").scrollfire({
		onTopOut: function(elm, dY) {
			var jpID=$(elm).prop('id');
			// Dynamically change URL to reflect this record
			//history.pushState({'jpID':jpID}, "Record Box", "#"+jpID);
		}
	});

	// Hang some animations off classes, using scrollfire to trigger them
	$(".parallax").scrollfire({
		onScroll: function(elm, dY) {
			doParallax(elm);
		}
	});

	// Add class fadeInOut to an area to "AyresRock" it in and out
	$(".fadeInOut").scrollfire({
		onBottomIn: function( elm, dY ) {
			// Do lazy load here...
		},
		onScroll: function(elm, dY) {
			var percThroughPage=getPercThroughPage(elm);
			$(elm).css({'opacity':easings.easeAyers(percThroughPage)}); // Fade in and out like Ayers Rock
		}
		
	});

	$(".css3transform").scrollfire({
		onScroll: function(elm,dY) {
			var docTop=$(document).scrollTop(); // docTop goes upwards from 0...
			var topYpos=gPageHeight-($(elm).parent().offset().top-docTop); // 0=bottom of page
			var spinSpace=200;
			var start=(gPageHeight/2);
			var end=(gPageHeight/2+spinSpace);
			var deg=0;
			var left=0;
			if (topYpos>start && topYpos<=end) {
				deg=180*easings.easeIn((topYpos-start)/(end-start));
				var left=-90*easings.easeBell((topYpos-start)/(end-start));
			} else if (topYpos>end) {
				deg=180;
			}
			$(elm).css({'left':""+left+"px"});
			$(elm).find('.front').css({'transform':"rotateY("+deg+"deg)"});
			$(elm).find('.back').css({'transform':"rotateY("+(180-deg)+"deg)"});
		}
	});

	$(".jsTransform").scrollfire({
		onScroll: function(elm,dY) {
			// Initialise 3D gubbins if not already done...
			if (jp3d[elm.id]===undefined) {
				// $("canvas").remove();
				initialise3D(elm);
				render(0,elm.id);	
			}
			var docTop=$(document).scrollTop(); // docTop goes upwards from 0...
			var topYpos=gPageHeight-($(elm).parent().offset().top-docTop); // 0=bottom of page
			var spinSpace=250;
			var start=(gPageHeight/2);
			var end=(gPageHeight/2+spinSpace);
			var t=0;
			if (topYpos>start && topYpos<=end && !$(elm).hasClass('mouseSlide')) {
				t=easings.easeIn((topYpos-start)/(end-start));
			} else if ($(elm).hasClass('mouseSlide')) {
				t=$(elm).data('rotate');
				if (t==undefined) t=0;
			} else if (topYpos>end) {
				t=1;					
			}	
			render(t,elm.id);
		},
		onTopHidden: function(elm) {
			// trying to free up memory by removing as we go....
			//jp3d[elm.id]=false;
		},
		onBottomHidden: function(elm) {
			// trying to free up memory as we go...
			//jp3d[elm.id]=false;
		}
	});

	// sleeve picture viewer thing
	$('.pictureViewerSmall img').click(function() {
		var src=$(this).attr('src');
		var cls=$(this).attr('class');
		$('#'+cls+' .pictureViewerMain img').attr('src',src);
	})

	/*if (ie && deviceType==1) {
		Object.keys(THREE.ShaderLib).forEach(function (key) {
	    THREE.ShaderLib[key].fragmentShader =
	    THREE.ShaderLib[key].fragmentShader.replace('#extension GL_EXT_frag_depth : enable', '');
		});
	}*/
}

function fadeInHeader() {
	// recordBox header
	$('#headerimage').animate({'opacity':1},1000, function() { $('#introText').animate({'opacity':1, 'margin-top':0},1000); });
	// single record header
	$('.recordHead.parallax.headimage').animate({'opacity':1},1000);
}

function start() {
	doResize();
	fadeInHeader();
	// Add shields to sleeves...
	if (deviceType==1) {
		var shields="<div class='recordShield'><div class='fakeOpenBtn'></div></div>";
		shields+="<div class='audioShield'><div class='fakePlayBtn play'></div><div class='fakeAudioInfo'></div></div>";
		$(".sleeve").each(function() { $(this).append(shields); });
		// Make the record shields pop-up the record browser
		$(".fakeOpenBtn").click(function() { $(this).parent().parent().find('.playBtn').click();});
		// Make the audio shields click the real button...
		$(".fakePlayBtn").click(function() { $(this).parent().parent().find('.playBtn').click();});
	}
}

function doResize() {
	// Re-calculate global variables based on the new sizings
	gPageHeight=$(window).height();
	gPageWidth=$(window).width();
	// Fix the sizes of the "shrink" containers based on their initial img sizes (so that the initially expanded image does not also expand the box)
	$(".shrink").each(function() {
		var imgHeight=$(this).height();
		$(this).parent().css({'height':imgHeight, 'overflow': 'hidden'});
		$(this).css({'position':'absolute', 'bottom':0});
	});
	// Reduce the size of parallax containers
	$(".parallax").each(function() {
		doParallax(this);
	});
	$('.vid').each(function() {
		sizeVideoContainer(this);
	});
	if (!$('#headerimageContainer').hasClass('parallax')) {	
		$('#headerimageContainer').css({'height':$('#headerimageContainer img').height()*1});
	}
	// Pull the player up if screen becomes wide enough
	if (gPageWidth>768) { $('#player').animate({'bottom':'0px'}); $('#expandCollapseBtn').prop('src','/img/boxes/controls/player-min.png'); }
	// #videoContainer needs to be at the bottom of the main headerimage
	setupProgressBar(); // ensures progress bar in player scales correctly for new size
}

function doParallax(elm) {
	// Increase percThroughPage if img at top of page
	if (deviceType==3) return false;
	var imgHeight=$(elm).find("img").height();
	var percThroughPage=getPercThroughPage(elm);
	if ($(elm).hasClass('recordHead')) percThroughPage=(percThroughPage/3)+0.7;
	var posTop=$(elm).offset().top;
	var hole=gParallaxSpeed*imgHeight;

	// Increase size of hole if img at top of page
	if (posTop<gPageHeight) {
		// Already covered posTop
		var alreadyCovered=(gPageHeight-posTop)/(gPageHeight+imgHeight);
		hole=hole+((imgHeight-hole)*alreadyCovered);
	}
	if (hole!=$(elm).height()) { // Hole is the wrong size! (should only run once per window resize/load)
		// $(elm).animate({height:hole},500,'easeOutCubic');
		// if using on single record page, don't make massive....
		if (!$(elm).hasClass('recordHead')) $(elm).css({height:hole});
	}
	var imgHeight=$(elm).find("img").height();
	var bot=(imgHeight-hole)*percThroughPage;
	// Note: iOS will not apply any DOM changes until AFTER the touchmove event is done :(
	// trace("scrolling "+(++sCount)+" bot="+(-1*bot));
	$(elm).find("img").css({'bottom':-1*bot});
}

function sizeVideoContainer(elm) {
	var id=$(elm).prop('id');
	var flexWidth=$(elm).width(); // get container width (set by CSS)
	$('#'+id+'Player').width(flexWidth); // set embedded video object to width of container
	var newWidth=$('#'+id+'Player').width();
    var newHeight=$('#'+id+'Player').height();
    $('#'+id+' .inlineVideo').width(newWidth).height(newHeight+1);
    $('#'+id+'Shield').width(newWidth).height(newHeight+1);
    //console.log(newWidth+','+newHeight+' for '+id);
}

// three.js
// Pass in time (between 0 and 1) and id of record (e.g. 'piperSleeve')
var render=function (t,id) {
	if (deviceType!=1) return false;
	if (!(id in jp3d)) return false; // let's not assume it exists, yeah?
	if (jp3d[id]===undefined) return false; // this means it's been removed actually
	var deg=Math.abs((Math.PI)*Math.sin((Math.PI/2)*t));
	jp3d[id].record.rotation.y=deg;
	// jp3d[id].record.rotation.z=deg/2;
	var pz=250*Math.sin(Math.PI*t);
	var px=-60*Math.sin(Math.PI*t);
	// Push LPs (which have shadow-causing depth) into backplane
	if (jp3d[id].recordType=="album") pz=pz-1.5;
	jp3d[id].record.position.set(px,0,pz);
	// Change light y position opposite to scroll!
	jp3d[id].light.position.y=200*t-100;
	// light 2 comes on gradually as the record spins to add a "flare"
	light2.intensity=Math.sin(Math.PI*t);
	//jp3d[id].renderer.render(jp3d[id].scene, jp3d[id].camera);
	renderNo=jp3d[id].renderNo;
	jp3dRenderer[renderNo].render.render(jp3d[id].scene, jp3d[id].camera);
	//console.log(jp3dRenderer);
};

function initialiseShader() {
	if (deviceType==1) {
	// Background
	// Fragment Shader that allows plane material that is entirely transparent (you can see the web-page behind)
	// yet still accepts shadows. This is L33T shit. (thanks http://pastie.org/5088640 ;)
	planeFragmentShader = [
			"uniform vec3 diffuse;",
			"uniform float opacity;",
			THREE.ShaderChunk[ "color_pars_fragment" ],
			THREE.ShaderChunk[ "map_pars_fragment" ],
			THREE.ShaderChunk[ "lightmap_pars_fragment" ],
			THREE.ShaderChunk[ "envmap_pars_fragment" ],
			THREE.ShaderChunk[ "fog_pars_fragment" ],
			THREE.ShaderChunk[ "shadowmap_pars_fragment" ],
			THREE.ShaderChunk[ "specularmap_pars_fragment" ],
			"void main() {",
					"gl_FragColor = vec4( 1.0, 1.0, 1.0, 1.0 );",
					THREE.ShaderChunk[ "map_fragment" ],
					THREE.ShaderChunk[ "alphatest_fragment" ],
					THREE.ShaderChunk[ "specularmap_fragment" ],
					THREE.ShaderChunk[ "lightmap_fragment" ],
					THREE.ShaderChunk[ "color_fragment" ],
					THREE.ShaderChunk[ "envmap_fragment" ],
					THREE.ShaderChunk[ "shadowmap_fragment" ],
					THREE.ShaderChunk[ "linear_to_gamma_fragment" ],
					THREE.ShaderChunk[ "fog_fragment" ],
					"gl_FragColor = vec4( 0.0, 0.0, 0.0, 1.0 - shadowColor.x );",
			"}"
	].join("\n");

}

}

var size, canvasWidth, canvasHeight;
function initialise3D(elm) {
	if (jpaPlayer.dynamicLoad && !jpaPlayer.dynamicReady) return false;
	if (!threeJSLoaded) return false;
	conLog('initialise3d -> '+elm.id);

	if (!size) {
		size=$(elm).width();
		canvasWidth=size*2.85;
		canvasHeight=size*2.85;		
	}

	// Scene
	var scene = new THREE.Scene();

	var planeMaterial = new THREE.ShaderMaterial({
		uniforms: THREE.ShaderLib['basic'].uniforms,
		vertexShader: THREE.ShaderLib['basic'].vertexShader,
		fragmentShader: planeFragmentShader,
		color: 0x0000FF
	});

	var backPlane = new THREE.Mesh(
		new THREE.PlaneGeometry(750, 750, 10, 10),
		planeMaterial
	);
	backPlane.receiveShadow = true;
	scene.add(backPlane);

	// Pick up facets of record...
	var recordType="album", offsetX=0, offsetY=0; // until overridden...
	var classList=$(elm).prop('class').split(/ /);		
	for (var i in classList) {
		if (classList[i]=="single" || classList[i]=="twelve") recordType=classList[i];
		var oX=locate('offsetX',classList[i]);
		if (oX) offsetX=parseInt(oX);
		var oY=locate('offsetY',classList[i]);
		if (oY) offsetY=parseInt(oY);
	}
	var recordType=($(elm).hasClass('single'))?"single":(($(elm).hasClass('twelve'))?"twelve":"album");
	var frontUrl=$(elm).find(".front").prop('src');
	var backUrl=$(elm).find(".back").prop('src');
	var spineUrl=$(elm).find(".spine").prop('src');
	if (!spineUrl) spineUrl=backUrl;

	// Record textures
	var mFront=new THREE.MeshPhongMaterial( { emissive:0x333333, map: THREE.ImageUtils.loadTexture(frontUrl) } );
	var mBack= new THREE.MeshPhongMaterial( { emissive:0x333333, map: THREE.ImageUtils.loadTexture(backUrl) } );
	var mSpine=new THREE.MeshPhongMaterial( { emissive:0x333333, map: THREE.ImageUtils.loadTexture(spineUrl) } );

	// Record geometry
	if (recordType=="album") {
		// 12" album (no hole) 3D Model
		var recordWidth=313,recordHeight=313,recordDepth=3;
		// Texture map a Box with the front, back and spines
		var gBox=new THREE.BoxGeometry(recordWidth,recordHeight,recordDepth);
		var materials=[mSpine, mSpine, mSpine, mSpine, mFront, mBack, mFront];
		var mFaces=new THREE.MeshFaceMaterial( materials );
		var record=new THREE.Mesh(gBox, mFaces);
		record.castShadow=true;
		scene.add(record);
	} else {
		if (recordType=="twelve") {
			// 12" single (with hole) 3D Model
			var recordWidth=313,recordHeight=313,recordDepth=3;
			var holeSize=recordWidth*0.01;
			offsetX=offsetX+(holeSize/2);
			offsetY=offsetY-(holeSize/2);
		} else {
			// 7" single 3D Model (NB: 7"s are not quite square)
			var recordWidth=313,recordHeight=300,recordDepth=3;
			var holeSize=($(elm).hasClass('jukebox'))?recordWidth*0.1:recordWidth*0.02;
			offsetX=offsetX+1;
			offsetY=offsetY-7;
		}
		// Creating a hole through the 7" model is complicated since ThreeCSG (which must be used as Three.js cannot do substractive geometry combination)
		// cannot handle multiple textures in the source model (e.g. the front and back)
		// We therefore create 2 boxes - one each for front and back - and sandwich them together.
		var gBox=new THREE.BoxGeometry(recordWidth,recordHeight,1);
		var bspFrontBox=new ThreeBSP(gBox);
		var bspBackBox=new ThreeBSP(gBox);
		var gHole=new THREE.CylinderGeometry(holeSize, holeSize, recordDepth, 128, 1, 1);
		// Spin the hole to face front
		gHole.applyMatrix(new THREE.Matrix4().makeRotationX(-1*Math.PI/2));
		// Position the hole centrally
		gHole.applyMatrix(new THREE.Matrix4().makeTranslation(offsetX,offsetY,0));
		// Use BSP to merge the geometry of the hole cylinder with the sleeve boxes
		var bHole=new ThreeBSP( gHole );
		var bspFrontBoxWithHole = bspFrontBox.subtract( bHole );
		var bspBackBoxWithHole = bspBackBox.subtract( bHole );
		var r1=bspFrontBoxWithHole.toMesh();
		r1.applyMatrix(new THREE.Matrix4().makeTranslation(0,0,0.1));
		var r2=bspFrontBoxWithHole.toMesh();
		r2.applyMatrix(new THREE.Matrix4().makeTranslation(0,0,-0.1));
		// Combine the front and back slices into the whole loaf which is the single
		var gRecord=new THREE.Geometry();
		gRecord.merge(r1.geometry,r1.matrix,0);
		gRecord.merge(r2.geometry,r2.matrix,1);
		var record=new THREE.Mesh(gRecord, new THREE.MeshFaceMaterial([mFront,mBack]));
		record.castShadow=true;
		scene.add(record);
		// r1.geometry.computeVertexNormals();
	}

	// Lights
  scene.add(new THREE.AmbientLight(0x222222));
	light = new THREE.SpotLight(0xaaaaaa, 1);
	light.position.set(-300, 0, 950);
	light.castShadow = true;
	scene.add(light);

	light2 = new THREE.SpotLight(0xaaaaaa, 0);
	light2.position.set(250, 0, 950);
	// light2.rotation.y=-1*Math.PI/2;
	light2.castShadow;
	scene.add(light2);

	// Camera
	var camera=new THREE.PerspectiveCamera(75, canvasWidth/canvasHeight, 1, 10000);
	camera.position.set(0,0,585);
	// camera.lookAt(record.position);

	// action!
	// to avoid building a renderer for every single sleeve
	// and to avoid potentially having multiple sleeves on screen
	// we make a pool and flag when they are available...

	if (!jp3dRenderer || jp3dRenderer.length==0) {
		// need to create our pool of renderers...
		for (b = 0; b < 5; b++ ) {
			jp3dRenderer[b]={};
			jp3dRenderer[b].available=true;
			jp3dRenderer[b].render=new THREE.WebGLRenderer({ alpha: true });
			jp3dRenderer[b].render.setSize(canvasWidth, canvasHeight);
			jp3dRenderer[b].render.shadowMapEnabled = true;
			jp3dRenderer[b].render.shadowMapSoft = true;
		}
	}
	found=false;
	// this finds an available renderer...
	for (i in jp3dRenderer) {
		if (jp3dRenderer[i].available==true && !found) {
			renderNo=i;
			jp3dRenderer[i].available=false;
			elm.appendChild( jp3dRenderer[renderNo].render.domElement );
			found=true;
		}
	}
	
	$(elm).find("img").hide();
	
	var shiftAmt=-1*(size*2.85/2)+(size/2);
	$(jp3dRenderer[renderNo].render.domElement).css({'top':shiftAmt, 'left':shiftAmt, 'z-index':12 });
	var id=elm.id;
	sCount++;
	// jp3d[id].scene, camera, light, record
	jp3d[id]={'scene':scene, 'light':light, 'camera':camera, 'renderNo':renderNo, 'record':record, 'recordType':recordType,'sleeveNumber':sCount}	
	if (sCount>4) {
		var lowest=10000;
		var sleeveNo=0;
		for (sleeve in jp3d) {
			if (jp3d[sleeve] !=undefined && jp3d[sleeve].sleeveNumber<lowest) {
				lowest=jp3d[sleeve].sleeveNumber;
				sleeveNo=sleeve;
			}
		}
		if (sleeveNo!=0) {
			renderNo=jp3d[sleeveNo].renderNo;
			jp3dRenderer[renderNo].available=true; // mark renderer as available
			jp3d[sleeveNo]=undefined;
		}
	}
}

// These functions are redundant for popup sleeves in this context
function goPrev() { }
function goNext() { }
function showHidePrevNext() { $("#prevBtn").hide(); $("#nextBtn").hide(); }
