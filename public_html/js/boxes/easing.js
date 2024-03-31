// Much respect and thanks to Robert Penner http://robertpenner.com/easing/ and danro https://github.com/danro/jquery-easing/blob/master/jquery.easing.js#L136
// Note: easing functions shouldn’t have to care about the start/end values, only the percentage matters
// Give a percentage in terms of time (between 0 and 1) and get back a percentage in terms of distance (between 0 and 1)
// This handy function then multiplies that up for whatever situation you're using it in
// e.g.: percAlong=easings.easeOut(percTime);
// or if you can't be arsed to calculate your own percentages pass start/end values to the ease() function below:
//   x=ease(100,300,percTime,'easeOut');
function ease(start,end,t,funcName) {
	funcName=(funcName || 'easeOut');
	return (end-start)*easings[funcName](t)+start;
}

var easings={
	linear: function(t) { return t; },
	easeIn: function(t,p) {
		p=(p || 3); // Default to cubic
		return Math.pow(t,p);
	},
	easeOut: function(t,p) {
		p=(p || 3); // Default to cubic
		return 1-easings.easeIn(1-t,p);
	},
	easeInOut: function (t,p) {
		return (t<0.5)?easings.easeIn(t*2,p)/2:easings.easeOut(t*2,p)/2;
	},
	easeInBounce: function (t,bounces) {
		return 1-easings.easeOutBounce(1-t,bounces);
	},
	easeOutBounce: function (t,bounces) {
		var pow2, bounces=(bounces || 4);
		while (t<((pow2=Math.pow(2,--bounces))-1)/11) {}
		return 1/Math.pow(4,3-bounces)-7.5625*Math.pow((pow2*3-2)/22-t,2);
	},
	easeInOutBounce: function (t,bounces) {
		return (t<0.5)?easings.easeInBounce(t*2,bounces)/2:easings.easeOutBounce(t*-2,bounces)/2;
	},
	easeInBack: function (t) {
		var s=1.70158;
		return 1*(t/=1)*t*((s+1)*t-s);
	},
	easeOutBack: function (t) {
		return -1*easings.easeInBack(1-t);
	},
	easeInOutBack: function (t) {
		return (t<0.5)?easings.easeInBack(t*2)/2:easings.easeOutBack(t*-2+2)/2;
	},
	easeInSine: function(t) {
		return 1-Math.cos(t*Math.PI/2);
	},
	easeOutSine: function(t) {
		return -1*easings.easeInSine(1-t);
	},
	easeInCirc: function(t) {
		return 1-Math.sqrt(1-t*t);
	},
	easeOutCirc: function(t) {
		return -1*easings.easeInCirc(1-t);
	},
	easeInElastic: function(t) {
		return t===0 || t===1?t:
			-Math.pow(2,8*(t-1))*Math.sin(((t-1)*80-7.5)*Math.PI/15);
	},
	easeOutElastic: function(t) {
		return -1*easings.easeInElastic(1-t);
	},
	easeBell: function(t) {
		return (Math.sin(2*Math.PI*(t-1/4))+1)/2;
	},
	easeAyers: function(t,steepness) {
		steepness=(steepness || 0.25);
		if (t<steepness) return easings.easeInOut(t/steepness);
		if (t>(1-steepness)) return easings.easeInOut(1-(t-(1-steepness))/steepness);
		return 1;
	}
}
