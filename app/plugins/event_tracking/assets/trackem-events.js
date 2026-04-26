(function () {
  function currentScript() {
    return document.currentScript || (function () {
      var scripts = document.getElementsByTagName("script");
      return scripts[scripts.length - 1] || null;
    })();
  }

  function endpointUrl() {
    var script = currentScript();
    if (!script || !script.src) {
      return null;
    }
    var url = new URL(script.src, window.location.href);
    url.searchParams.delete("file");
    url.searchParams.set("p", "event_tracking.collect");
    return url.toString();
  }

  var collectUrl = endpointUrl();
  if (!collectUrl) {
    return;
  }

  function currentPath() {
    return window.location.pathname + window.location.search;
  }

  function sanitizeMeta(meta) {
    if (!meta || typeof meta !== "object" || Array.isArray(meta)) {
      return {};
    }
    var out = {};
    var keys = Object.keys(meta);
    for (var i = 0; i < keys.length; i++) {
      var key = String(keys[i] || "").toLowerCase().replace(/[^a-z0-9_.:-]/g, "_");
      if (!key) continue;
      var value = meta[keys[i]];
      if (value == null) continue;
      if (typeof value === "object") continue;
      out[key] = String(value);
    }
    return out;
  }

  function postEvent(payload) {
    var body = JSON.stringify(payload);
    if (navigator.sendBeacon) {
      var blob = new Blob([body], { type: "application/json" });
      if (navigator.sendBeacon(collectUrl, blob)) {
        return;
      }
    }
    fetch(collectUrl, {
      method: "POST",
      credentials: "same-origin",
      keepalive: true,
      headers: {
        "Content-Type": "application/json"
      },
      body: body
    }).catch(function () {});
  }

  window.trackemEvent = function (eventName, payload) {
    if (typeof eventName !== "string" || !eventName.trim()) {
      return false;
    }
    payload = payload && typeof payload === "object" ? payload : {};
    postEvent({
      event: eventName,
      label: typeof payload.label === "string" ? payload.label : "",
      path: typeof payload.path === "string" ? payload.path : currentPath(),
      meta: sanitizeMeta(payload.meta)
    });
    return true;
  };

  document.addEventListener("click", function (e) {
    var target = e.target && e.target.closest ? e.target.closest("[data-trackem-event]") : null;
    if (!target) {
      return;
    }

    var meta = {};
    for (var i = 0; i < target.attributes.length; i++) {
      var attr = target.attributes[i];
      if (!attr || attr.name.indexOf("data-trackem-meta-") !== 0) {
        continue;
      }
      meta[attr.name.substring("data-trackem-meta-".length)] = attr.value;
    }

    window.trackemEvent(String(target.getAttribute("data-trackem-event") || ""), {
      label: String(target.getAttribute("data-trackem-label") || ""),
      path: currentPath(),
      meta: meta
    });
  }, true);
})();
