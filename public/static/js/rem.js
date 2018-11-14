document.documentElement.style.fontSize = document.documentElement.clientWidth / 10+ 'px';
var evt = "onorientationchange" in window ? "orientationchange" : "resize";
window.addEventListener(evt, function() {
      document.documentElement.style.fontSize = document.documentElement.clientWidth / 10+ 'px';
}, false);