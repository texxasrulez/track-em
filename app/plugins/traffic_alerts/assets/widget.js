(function () {
  var endpoint = (window.TE_BASE || "") + "/index.php?p=traffic_alerts.dashboard_data";

  function inject(alerts) {
    if (!alerts || !alerts.length) {
      return;
    }
    var host = document.querySelector("main") || document.body;
    if (!host || document.getElementById("ta-dashboard-widget")) {
      return;
    }

    var card = document.createElement("section");
    card.id = "ta-dashboard-widget";
    card.className = "card";
    card.style.marginBottom = "16px";
    card.innerHTML =
      '<h3 style="margin-top:0">Traffic Alerts</h3>' +
      '<div data-traffic-alert-list></div>';

    var list = card.querySelector("[data-traffic-alert-list]");
    for (var i = 0; i < alerts.length; i++) {
      var alert = alerts[i];
      var item = document.createElement("div");
      item.style.padding = "10px 12px";
      item.style.border = "1px solid var(--border)";
      item.style.borderRadius = "10px";
      item.style.marginTop = "8px";
      item.innerHTML =
        '<strong>' + String(alert.title || "Alert") + '</strong>' +
        '<div style="font-size:13px;margin-top:4px">' + String(alert.summary || "") + '</div>';
      list.appendChild(item);
    }

    host.insertBefore(card, host.firstChild);
  }

  fetch(endpoint, { credentials: "same-origin", cache: "no-store" })
    .then(function (res) {
      if (!res.ok) {
        throw new Error("dashboard data unavailable");
      }
      return res.json();
    })
    .then(function (data) {
      if (!data || !data.ok || !data.enabled || !data.dashboard_notice) {
        return;
      }
      inject(Array.isArray(data.alerts) ? data.alerts : []);
    })
    .catch(function () {});
})();
