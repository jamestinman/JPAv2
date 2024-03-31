function initialiseGalleria() {
	console.log("Initialising Galleria...");
  Galleria.configure({imageCrop: true, transition: 'slide', width: 379, height: 425});
  Galleria.loadTheme('/js/libs/themes/classic/galleria.classic.min.js');
}

function prepareAlbumInfo(id) {
  if (typeof Raphael=="undefined") {
    records=allRecords;
    letter=id.charAt(0).toUpperCase();
  }
  var h="";
  if (id in records) {
    r=records[id];  
  } else {
    return false;
  }
  var ttl=r.artist+" - "+r.album;
  var description="John Peels copy of "+ttl;
  // Populate the pop-up
  $("#popTitle").html(ttl);
  $("#popCardNum").html(r.card);
  $("#popListen").html(doLinks(r,'listen','/albums/img/',"Listen"));
  $("#popInfo").html(doLinks(r,'info','/albums/img/',"Other Info"));
  $("#popVideos").html(doBoxVideos(r));
  // Wire up video hover
  $(".videoHover").hover(function(){ $(this).children('.videoBtn').fadeIn(); }, function(){ $(this).children('.videoBtn').fadeOut(); });
  // $("#popSessions").html(doSessions(r,'../img/'));
  $("#popCard").html("<img src='/albums/img/cards/"+letter+"/"+id+".jpg' />");
  // Sleeve Art Slider
  // $("#popCoverFlow").html("<img src='img/"+letter+"/"+id+"-"+lpad(r.card,'0',5)+"-1.jpg' />");

  var gallery=[];
  galleriaID='galleria'+id;
  var firstSleeveUrl=false, sleeveUrl;
  $("#popCoverFlow").html("<div id='"+galleriaID+"'>Loading...</div>");
  // Create big and thumb references for Galleria
  for (i=1; i<=r.numImages; i++) {
    sleeveUrl="/albums/img/"+letter+"/"+id+"-"+lpad(r.card,'0',5)+"-"+i+".jpg";
    if (!firstSleeveUrl) firstSleeveUrl=sleeveUrl;
    gallery[i-1]={'image':sleeveUrl, 'thumb':sleeveUrl, 'big':sleeveUrl, 'title': ttl};
    // h+="<img src='"+sleeveUrl+"' width='379' data-big='"+sleeveUrl+"' />";
  }
  setTimeout(function() {
		Galleria.run("#"+galleriaID, {dataSource: gallery, extend: function() { this.bind('image', function(e) { $(e.imageTarget).click(this.proxy(function() { this.openLightbox(); })); }); } }); // Extension adds pop-up on click functionality
		$("#"+galleriaID).click(function() { console.log("G1 Clicked"); });
	}, 1000);
	// if (flipped) setTimeout(function() { $('#galleria').data('galleria').next(); }, 1000);
  // History
  // history.pushState({jpID:curID}, ttl+" : John Peel Archive", "recordbox.html?jpID="+curID);
  if (typeof Raphael!="undefined") { // dont do history stuff on record box pages for now
    switchPage({jpID:curID},ttl+" : John Peel Archive","recordbox.html?jpID="+curID);
  }
  // Social Media
  //var rootUrl=window.location.href; // old
  var pathArray = window.location.href.split( '/' );
  var rootUrl= pathArray[0]+'//'+pathArray[2];
  //rootUrl=rootUrl.substr(0,rootUrl.lastIndexOf('/')); // old
  var imgUrl=rootUrl+firstSleeveUrl;
  // console.log("img url =["+imgUrl+"]");
  var me=rootUrl+"albums/recordbox.html?jpID="+id;
  // Snippet
  h='<div id="snippet" style="display:none">';
  h+='<span itemprop="name">'+ttl+'</span>';
  h+='<span itemprop="description">'+description+'</span>';
  h+='<img itemprop="image" src="'+imgUrl+'">';
  h+='</div>';
  // Social
  h+="<table><tr>";
  // Twitter
  h+='<td><a href="https://twitter.com/share" class="twitter-share-button" data-url="'+me+'" data-via="johnpeelarchive" data-text="'+description+' > '+me+'" data-hashtags="" data-lang="en">Tweet</a></td>';
  // G+
  h+='<td><g:plusone size="medium" href="'+me+'"></g:plusone></td>';
  // Facebook
  h+='<td><fb:like href="'+me+'" send="false" layout="button_count" width="50" show_faces="false" action="like" title="'+ttl+'"></fb:like></td>';
  // Pinterest
  h+='<td>&nbsp;&nbsp;&nbsp;<a href="http://pinterest.com/pin/create/button/?url='+me+'&media='+imgUrl+'&description='+description+'" class="pin-it-button pin-it-btn" count-layout="horizontal"><img border="0" src="//assets.pinterest.com/images/PinExt.png" title="Pin It" /></a></td>';
  // Direct link
  // h+="</tr></table><div>Direct link: <input type='text' id='directLink' name='directLink' size=35 value='"+me+"?jpID="+id+"' /></div>";
  $("#popSocial").html(h);
  twttr.widgets.load(); // Reload Twitter
  FB.XFBML.parse(); // Reload Facebook
  gapi.plusone.go(); // Reload G+
}

function popupAlbumInfo(whichID) {
  // this makes site use record pages instead, uncomment when we're ready.
  if (typeof jpaPlayer!="undefined") {
    // in boxes, direct using JS
    changeBox(false, whichID);
    return false;
  } else {
    // actually change page
    var newPath='/records/?jpID='+curID;
    window.location.replace(newPath);
    return false;
  }
  if (parseInt($(window).width())<880) return false; // lightbox goes wrong on small screens - dont show for now
  if (whichID) curID=whichID;
  hideTab();
  // use endID from touchend event rather than curID, if it is available
  prepareAlbumInfo(curID);
  if (curID in records) {
    r=records[curID];  
  } else {
    return false;
  }
  //r=records[curID];

  if (typeof Raphael=="undefined") { // this is for Joe Boyd box
    var minWidth=320;

    var minHeight=240;
    var margin=[25,25,25,25];
  } else { // defaults for old album boxes
    var minWidth=820;
    var minHeight=460;
    var margin=[75,50,50,50];
  }
  $.fancybox({ href:'#thePopUp', type:"inline", topRatio: 0, margin: margin, minWidth: minWidth, minHeight: minHeight, arrows: true, helpers: { overlay : { opacity : 0.20 } } } );
  $(".fancybox-skin").append("<div id='popTitle'>"+r.artist+" - "+r.album+"</div>");
  $(".fancybox-skin").append("<div><a id='prevBtn' class='arrow' onClick='goPrev();return false;'><img src='../img/arrow-left.png'></a></div>");
  $(".fancybox-skin").append("<div><a id='nextBtn' class='arrow' onClick='goNext();return false;'><img src='../img/arrow-right.png'></a></div>");
  showHidePrevNext();
  $.fancybox.update();
}

function popupSingleArt(whichID) {
  // this makes site use record pages instead, uncomment when we're ready.
  
  if (typeof jpaPlayer!="undefined") {//} && jpaPlayer.dynamicLoad==true) {
    changeBox(false,whichID);
    return false;
  }

  var letter;
  letter=whichID.charAt(0).toUpperCase();
  if (whichID in allSingles) {
    r=allSingles[whichID];
  } else {
    return false;
  }
  
  if (!allSingles || !r) return false;
  var sleeveUrl;

  var images=[];
  for (i=1; i<=r.numImages; i++) {
    sleeveUrl="/singles/img/"+letter+"/"+whichID+"-"+i+".jpg";
    images.push(sleeveUrl);
  }

  $.fancybox(images, {
      'padding'     : 0,
      'transitionIn'    : 'none',
      'transitionOut'   : 'none',
      'type'              : 'image',
      'changeFade'        : 0
    });
}

function hideTab() {
  $("#theTab").css("display","none");
}

