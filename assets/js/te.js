(function () {
  function getCookie(name) {
    var pattern = new RegExp(
      "(?:^|; )" +
        name.replace(/([.$?*|{}()[\]\\\/\+^])/g, "\\$1") +
        "=([^;]*)",
    );
    var m = document.cookie.match(pattern);
    return m ? decodeURIComponent(m[1]) : null;
  }
  function getConsentSignal() {
    var c = getCookie("te_consent");
    if (c) return c;
    try {
      return localStorage.getItem("te_consent");
    } catch (_) {
      return null;
    }
  }
  var consent = getConsentSignal();
  var data = {
    path: location.pathname + location.search,
    lang: navigator.language || null,
    consent: consent || null,
    screen: {
      w: screen.width,
      h: screen.height,
      dpr: window.devicePixelRatio || 1,
    },
  };
  try {
    fetch(window.TE_ENDPOINT || "/track.php", {
      method: "POST",
      keepalive: true,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });
  } catch (e) {
    var img = new Image(1, 1);
    img.src =
      (window.TE_ENDPOINT || "/track.php") +
      "?p=" +
      encodeURIComponent(data.path) +
      (consent ? "&consent=" + encodeURIComponent(consent) : "") +
      "&t=" +
      Date.now();
  }
})();
