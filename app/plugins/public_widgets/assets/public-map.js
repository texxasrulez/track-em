(function () {
  var mapEl = document.getElementById("pw-public-map");
  var statusEl = document.getElementById("pw-map-status");
  var layerSelect = document.getElementById("pw-map-layer-select");
  if (!mapEl || !window.L || !window.PUBLIC_WIDGETS_MAP_DATA_URL) {
    return;
  }

  var layers = {
    roads: L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap contributors"
    }),
    light: L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png", {
      subdomains: "abcd",
      maxZoom: 20,
      attribution: "&copy; OpenStreetMap contributors &copy; CARTO"
    }),
    terrain: L.tileLayer("https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png", {
      subdomains: "abc",
      maxZoom: 17,
      attribution: "&copy; OpenStreetMap contributors, SRTM | &copy; OpenTopoMap"
    }),
    satellite: L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
      maxZoom: 18,
      attribution: "Tiles &copy; Esri"
    })
  };

  var map = L.map(mapEl, {
    zoomControl: true,
    scrollWheelZoom: false,
    attributionControl: true
  }).setView([20, 0], 2);

  var currentLayer = null;
  function setBaseLayer(name) {
    var next = layers[name] || layers.roads;
    if (currentLayer) {
      map.removeLayer(currentLayer);
    }
    currentLayer = next;
    currentLayer.addTo(map);
  }

  function setStatus(message, isError) {
    if (!statusEl) {
      return;
    }
    statusEl.hidden = !message;
    statusEl.textContent = message || "";
    statusEl.className = "pw-map-status" + (isError ? " is-error" : "");
  }

  function radiusForCount(count) {
    if (count >= 100) return 22;
    if (count >= 25) return 18;
    if (count >= 10) return 14;
    return 10;
  }

  if (layerSelect) {
    layerSelect.addEventListener("change", function () {
      setBaseLayer(layerSelect.value);
    });
  }

  setBaseLayer(window.PUBLIC_WIDGETS_MAP_DEFAULT_LAYER || "roads");

  fetch(window.PUBLIC_WIDGETS_MAP_DATA_URL, {
    credentials: "same-origin",
    cache: "no-store"
  })
    .then(function (res) {
      if (!res.ok) {
        throw new Error("map unavailable");
      }
      return res.json();
    })
    .then(function (data) {
      var points = Array.isArray(data.points) ? data.points : [];
      var showCounts = !!(data.profile && data.profile.show_counts);
      if (!points.length) {
        setStatus("No public map data is available for this range yet.", false);
        return;
      }

      var bounds = [];
      for (var i = 0; i < points.length; i++) {
        var point = points[i];
        if (typeof point.lat !== "number" || typeof point.lon !== "number") {
          continue;
        }
        var count = typeof point.count === "number" ? point.count : 0;
        var marker = L.circleMarker([point.lat, point.lon], {
          radius: radiusForCount(count),
          color: "#0f172a",
          weight: 1,
          fillColor: "#2563eb",
          fillOpacity: 0.72
        }).addTo(map);

        if (showCounts) {
          marker.bindTooltip(String(count), {
            permanent: true,
            direction: "center",
            className: "pw-count-label"
          });
        }
        marker.bindPopup(count + " aggregated visits");
        bounds.push([point.lat, point.lon]);
      }

      if (bounds.length === 1) {
        map.setView(bounds[0], 4);
      } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [24, 24] });
      }
      setStatus("", false);
    })
    .catch(function () {
      setStatus("Map data is unavailable.", true);
    });
})();
