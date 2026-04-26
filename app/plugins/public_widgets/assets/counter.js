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
    url.search = "";
    url.hash = "";
    url.pathname = url.pathname.replace(/\/[^/]*$/, "/index.php");
    url.searchParams.set("p", "public_widgets.counter_data");
    return url;
  }

  function renderCounter(el, data) {
    var label = String((data && data.label) || "Visits");
    var display = String((data && data.display) || "0");
    el.textContent = display + " " + label;
  }

  function resolvePath(el) {
    var explicitPath = el.getAttribute("data-trackem-path");
    if (explicitPath) {
      return explicitPath;
    }
    return window.location.pathname + window.location.search;
  }

  function boot() {
    var base = endpointUrl();
    if (!base) {
      return;
    }

    var nodes = document.querySelectorAll("[data-trackem-counter]");
    for (var i = 0; i < nodes.length; i++) {
      (function (el) {
        var scope = el.getAttribute("data-trackem-counter") === "path" ? "path" : "site";
        var url = new URL(base.toString());
        url.searchParams.set("scope", scope);
        if (scope === "path") {
          url.searchParams.set("path", resolvePath(el));
        }
        fetch(url.toString(), { credentials: "same-origin", cache: "no-store" })
          .then(function (res) {
            if (!res.ok) {
              throw new Error("counter unavailable");
            }
            return res.json();
          })
          .then(function (data) {
            renderCounter(el, data);
          })
          .catch(function () {
            el.textContent = "";
          });
      })(nodes[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
