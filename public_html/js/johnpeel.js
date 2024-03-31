/* Stop IE7 / IE8 erroring on date.now or console.log() (use $.error('OK'); instead) */
if (typeof console == "undefined") { this.console = {log: function() {}}; }
if (!Date.now) { Date.now = function() { return new Date().valueOf(); } }
function supportsHistory() { return !!(window.history && history.pushState); }
if (!supportsHistory()) { history.pushState=function(state,title,link) { document.title=title; return true; } } // Hack so older browsers don't fall over on history API calls
function switchPage(state,title,url) { if (supportsHistory()) history.pushState(state, title, url); document.title=title; }

/* extract the value of a subfield embedded in a field name */
function locate(subField,fullField) { 
  if (fullField.indexOf(subField)==-1) return false;
  startPos=fullField.indexOf(subField)+subField.length;
  id="";
  endPos=startPos;
  while (endPos<fullField.length && (!isNaN(fullField.charAt(endPos)) || fullField.charAt(endPos)=="-")) {
    id+=fullField.charAt(endPos++);
  }
  return (id=="")?true:id;
}

// URL get parameter based on Netlobo's gup
function p(name) {
  name=name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
  var regexS = "[\\?&]"+name+"=([^&#]*)";
  var regex = new RegExp( regexS );
  var results = regex.exec( window.location.href );
  if(results==null || results.length==0) return false;
  else return unescape(results[1]);
}

function getIfSet(obj,item,alt) {
  return ((obj[item]!==undefined)?obj[item]:((alt!==undefined)?alt:''));
}

function lpad(s,padChar,len) {
  str=""+s;
  while (str.length < len) str=padChar+str;
  return str;
}

// Data formatting routines
function doSessions(rec) {
  h="<h3>Collections</h3>";
  for (n=0; n<rec.sessions.collections.length; n++) {
    d=rec.sessions.collections[n];
    var title=(d.hasOwnProperty('title'))?d.title:"-";
    h+="<tr><td>"+title+"</td><td><a href='"+d.url+"' target='jpPopUp' class='playHover'><img src='img/sessions/"+d.artwork+"' /><img src='img/arrow-play.png' class='playBtn' /></a></td></tr>";
  }
  h="<table>"+h+"</table>";
  return h;
}
function doSessionTracks(rec) {
  h="<h3>Tracks</h3>";
  for (n=0; n<rec.sessions.tracks.length; n++) {
    d=rec.sessions.tracks[n];
    h+="<tr><td>"+d.sessionDate+"</td><td>"+d.title+"</td><td><a href='"+d.url+"' target='jpPopUp' class='playHover'><img src='img/sessions/"+d.artwork+"' /><img src='img/arrow-play.png' class='playBtn' /></a></td></tr>";
  }
  h="<table>"+h+"</table>";
  return h;
}
function doLinks(rec,key,imgDir,title) {
  if (!rec.hasOwnProperty(key) || rec[key].length==0) return "";
  var count=0;
  h="<h3>"+title+"</h3>";
  for (n=0; n<rec[key].length; n++) {
    d=rec[key][n];
    if (d.hasOwnProperty('url') && d.url!="") {
      count++;
      var ttl=(d.track!==undefined)?d.track:rec.album;
      var btn=(key=="listen")?"<a href='"+d.url+"' class='play' target='_blank'>PLAY_&#x25B6;</a>":"<a href='"+d.url+"' target='_blank'><img src='"+imgDir+"i.png' /></a>";
      h+="<tr><td>"+ttl+"</td><td>"+btn+"</td><td><a href='"+d.url+"' target='_blank'><img src='"+imgDir+d.service+".png' /></a></td></tr>";
    }
  }
  if (count==0) return "";
  h="<table><tr>"+h+"</tr></table>";
  return h;
}
function doVideos(rec,key) {
  if (!rec.hasOwnProperty('videos') || rec.videos.length==0) return "";
  h="";
  for (n=0; n<rec.videos[key].length; n++) {
    d=rec.videos[key][n];
    h+="<tr><td><a href='"+d.url+"' target='jpPopUp' class='videoHover'><img src='img/videos/"+d.thumb+"' /><img src='img/arrow-video.png' class='videoBtn' /></a></td><td>"+d.title+"</td></tr>";
  }
  h="<table>"+h+"</table>";
  return h;
}
function doBoxVideos(rec) {
  if (!rec.hasOwnProperty('videos') || rec.videos.length==0) return "";
  h="<h3>Video</h3>";
  for (n=0; n<rec.videos.length; n++) {
    d=rec.videos[n];
    h+="<tr><td><a href='"+d.url+"' target='jpPopUp' class='videoHover'><img src='../img/videos/"+d.thumb+"' /><img src='../img/arrow-video.png' class='videoBtn' /></a></td><td>"+d.title+"</td></tr>";
  }
  h="<table>"+h+"</table>";
  return h;
}
function doRadio(rec,imgDir) {
  var key='radio';
  if (!rec.hasOwnProperty(key) || rec[key].length==0) return "";
  h="<h3>Radio</h3>";
  for (n=0; n<rec[key].length; n++) {
    d=rec[key][n];
    h+="<tr><td>"+d.title+"</td><td><a href='"+d.url+"' class='play' target='_blank'>PLAY &#x25B6;</a></td><td><a href='"+d.url+"' target='_blank'><img src='"+imgDir+d.service+".png' /></a></td></tr>";
  }
  h="<table><tr>"+h+"</tr></table>";
  return h;
}
function debug(h) { $("#debug").css('display','block'); $("#debug").html(h); }
function isNum(n) { return !isNaN(parseFloat(n)) && isFinite(n); }
