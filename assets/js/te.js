(function(){
  var data = {
    path: location.pathname + location.search,
    lang: navigator.language || null,
    screen: { w: screen.width, h: screen.height, dpr: window.devicePixelRatio || 1 }
  };
  try {
    fetch((window.TE_ENDPOINT || '/track.php'), {
      method: 'POST', keepalive: true,
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
  } catch (e) {
    var img = new Image(1,1);
    img.src = (window.TE_ENDPOINT || '/track.php') + '?p=' + encodeURIComponent(data.path) + '&t=' + Date.now();
  }
})();
