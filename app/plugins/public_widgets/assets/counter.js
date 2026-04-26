(function () {
  function clampNumber(value, min, max, fallback) {
    var parsed = parseInt(value, 10);
    if (isNaN(parsed)) {
      return fallback;
    }
    return Math.max(min, Math.min(max, parsed));
  }

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

  function digitUrl(baseUrl, themeId, digit) {
    try {
      var url = new URL(baseUrl, window.location.href);
      url.searchParams.set("id", themeId);
      url.searchParams.set("n", digit);
      return url.toString();
    } catch (err) {
      return null;
    }
  }

  function renderTextCounter(el, label, display) {
    if (el.classList) {
      el.classList.remove("trackem-counter");
      el.classList.remove("trackem-counter-digits");
    }
    el.removeAttribute("aria-label");
    el.textContent = display + " " + label;
  }

  function renderImageCounter(el, data, label, display) {
    var themeId = String((data && data.digit_theme) || "");
    var baseUrl = String((data && data.digit_url_base) || "");
    var height = clampNumber(data && data.digit_height, 12, 128, 24);
    if (!themeId || !baseUrl) {
      renderTextCounter(el, label, display);
      return;
    }

    el.textContent = "";
    if (el.classList) {
      el.classList.add("trackem-counter");
      el.classList.add("trackem-counter-digits");
    }
    el.setAttribute("aria-label", label + ": " + display);

    for (var i = 0; i < display.length; i++) {
      var ch = display.charAt(i);
      if (/^[0-9]$/.test(ch)) {
        var img = document.createElement("img");
        var src = digitUrl(baseUrl, themeId, ch);
        if (!src) {
          renderTextCounter(el, label, display);
          return;
        }
        img.src = src;
        img.alt = "";
        img.setAttribute("aria-hidden", "true");
        img.setAttribute("data-digit-char", ch);
        img.style.height = String(height) + "px";
        img.style.width = "auto";
        img.style.verticalAlign = "middle";
        img.style.display = "inline-block";
        img.style.marginRight = "1px";
        img.onerror = function () {
          var span = document.createElement("span");
          span.textContent = this.getAttribute("data-digit-char") || "";
          span.style.display = "inline-block";
          span.style.verticalAlign = "middle";
          span.style.lineHeight = "1";
          this.replaceWith(span);
        };
        el.appendChild(img);
      } else {
        var textNode = document.createElement("span");
        textNode.textContent = ch;
        textNode.setAttribute("aria-hidden", "true");
        textNode.style.display = "inline-block";
        textNode.style.verticalAlign = "middle";
        textNode.style.lineHeight = "1";
        textNode.style.margin = "0 1px";
        el.appendChild(textNode);
      }
    }
  }

  function renderCounter(el, data) {
    var label = String((data && data.label) || "Visits");
    var display = String((data && data.display) || "0");
    if (String((data && data.display_mode) || "text") === "image_digits") {
      renderImageCounter(el, data, label, display);
      return;
    }
    renderTextCounter(el, label, display);
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
