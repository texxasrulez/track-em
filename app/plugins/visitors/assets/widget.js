(function () {
  try {
    var grid = document.getElementById("dash-grid");
    if (!grid || document.querySelector('.card[data-id="visitors"]')) return;

    var BASE =
      typeof window.BASE === "string"
        ? window.BASE
        : (function () {
            var p = (location.pathname || "").replace(/\/index\.php.*$/, "");
            return p.replace(/\/+$/, "");
          })();
    var API =
      typeof window.API === "function"
        ? window.API
        : function (ep) {
            return BASE + "/index.php?p=" + ep;
          };
    var rowLimit =
      window.DASH && window.DASH.row_limit ? window.DASH.row_limit : 200;
    var tfSel = document.getElementById("timeframe");

    var card = document.createElement("div");
    card.className = "card";
    card.setAttribute("data-id", "visitors");
    card.innerHTML =
      "<div class='card-handle' style='cursor:grab;margin:-4px 0 8px;font-weight:bold'>Visitors</div><div id='visitors-body'><div>Loading…</div></div>";
    grid.appendChild(card);

    function tfRange(value) {
      var now = Math.floor(Date.now() / 1000);
      var limit = Math.max(1, rowLimit);
      switch (value) {
        case "day":
          return { since: now - 1 * 86400, until: now, limit: limit };
        case "week":
          return { since: now - 7 * 86400, until: now, limit: limit };
        case "month":
          return { since: now - 30 * 86400, until: now, limit: limit };
        case "year":
          return { since: now - 365 * 86400, until: now, limit: limit };
        case "all":
        default:
          return { since: null, until: now, limit: limit };
      }
    }

    function buildGeoURL() {
      var params = new URLSearchParams();
      var tf = tfSel ? tfSel.value : "all";
      var range = tfRange(tf);
      var limit = range.limit || rowLimit;
      params.set("limit", String(limit));
      if (range.since != null) params.set("since", String(range.since));
      if (range.until != null) params.set("until", String(range.until));
      params.set("_ts", Date.now());
      var qs = params.toString();
      return {
        url: API("api.geo") + "&" + qs,
        range: range,
      };
    }

    function renderCard(data) {
      var rows = Array.isArray(data)
        ? data
        : Array.isArray(data.items)
          ? data.items
          : [];
      var seen = Object.create(null);
      var byCountry = Object.create(null);
      for (var i = 0; i < rows.length; i++) {
        var row = rows[i] || {};
        var ip = row.ip || "";
        var ts = row.ts || 0;
        var c = row.country || "Unknown";
        if (!(ip in seen) || ts > seen[ip]) seen[ip] = ts;
        byCountry[c] = (byCountry[c] || 0) + 1;
      }
      var unique = null;
      if (data && typeof data.unique_ips !== "undefined") {
        var num = parseInt(data.unique_ips, 10);
        if (isFinite(num)) unique = num;
      }
      if (unique === null) unique = Object.keys(seen).length;
      var cutoff = Math.floor(Date.now() / 1000) - 86400;
      var dayCount = 0;
      for (var k in seen) {
        if (seen[k] >= cutoff) dayCount++;
      }
      var topCountries = Object.keys(byCountry)
        .map(function (k) {
          return [k, byCountry[k]];
        })
        .sort(function (a, b) {
          return b[1] - a[1];
        })
        .slice(0, 5);

      var el = document.getElementById("visitors-body");
      if (!el) return;
      var html =
        "<div style='display:flex;gap:16px;flex-wrap:wrap'>" +
        "<div style='min-width:120px'><div style='font-size:12px;opacity:.7'>Unique IPs</div><div style='font-size:24px;font-weight:700'>" +
        unique +
        "</div></div>" +
        "<div style='min-width:120px'><div style='font-size:12px;opacity:.7'>Last 24h</div><div style='font-size:24px;font-weight:700'>" +
        dayCount +
        "</div></div>" +
        "<div style='min-width:200px'><div style='font-size:12px;opacity:.7;margin-bottom:6px'>Top Countries</div>";
      if (topCountries.length === 0) {
        html += "<div>—</div>";
      } else {
        for (var j = 0; j < topCountries.length; j++) {
          var c = topCountries[j];
          html +=
            "<div style='display:flex;justify-content:space-between;border-bottom:1px dashed var(--border,#2a3340);padding:2px 0'><span>" +
            c[0] +
            "</span><span>" +
            c[1] +
            "</span></div>";
        }
      }
      html += "</div></div>";
      el.innerHTML = html;
    }

    function loadCard() {
      var target = buildGeoURL();
      fetch(target.url, { credentials: "same-origin", cache: "no-store" })
        .then(function (r) {
          return r.text();
        })
        .then(function (txt) {
          var data = null;
          try {
            data = JSON.parse(txt);
          } catch (e) {
            console.warn("[visitors] bad json");
            return;
          }
          renderCard(data);
        })
        .catch(function (e) {
          console.warn("[visitors] fetch error", e);
        });
    }

    loadCard();
    if (tfSel) {
      tfSel.addEventListener("change", function () {
        loadCard();
      });
    }
  } catch (e) {
    console.warn("[visitors] widget exception", e);
  }
})();
