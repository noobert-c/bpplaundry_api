// Pemilih lokasi: Leaflet + tile OpenStreetMap + Nominatim (gratis, tanpa API key).
// Ketuk/klik peta untuk memasang titik, seret titik untuk menggeser, zoom dengan tombol +/-, pinch, atau scroll.
// data-mode="lihat" pada #peta: hanya tampil sampai tombol #ubah-lokasi ditekan.
function muatLeafletCadangan(cb, gagal) {
  var css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
  document.head.appendChild(css);
  var js = document.createElement('script');
  js.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
  js.onload = cb; js.onerror = gagal;
  document.head.appendChild(js);
}

document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('peta');
  if (!el) return;
  var $ = function (id) { return document.getElementById(id); };
  var status = $('cari-status');
  function msg(t) { if (!status) return; status.textContent = t || ''; status.hidden = !t; }

  if (typeof L === 'undefined') {
    el.innerHTML = '<div class="map-fail">Memuat peta…</div>';
    muatLeafletCadangan(mulai, function () {
      el.innerHTML = '<div class="map-fail">Peta gagal dimuat. Periksa koneksi internet atau pengaturan blokir (adblock/firewall), lalu muat ulang.</div>';
    });
  } else {
    mulai();
  }

  function mulai() {
    el.innerHTML = '';
    var NOMINATIM = 'https://nominatim.openstreetmap.org';
    var fLat = $('lat'), fLng = $('lng'), fAlamat = $('alamat');
    var tAlamat = $('alamat-terpilih'), tKota = $('kota-terpilih'), cari = $('cari-lokasi');
    var btnCari = $('btn-cari'), btnSaya = $('btn-lokasi-saya'), btnUbah = $('ubah-lokasi'), barisCari = $('baris-cari');
    var kotak = el.parentNode;

    var startSet = el.dataset.set === '1';
    var start = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
    var editOn = el.dataset.mode !== 'lihat';

    var map = L.map(el, { zoomControl: true, minZoom: 3, maxZoom: 19 }).setView(start, startSet ? 17 : 13);
    map.zoomControl.setPosition('topright');

    var tileErrors = 0;
    var tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      referrerPolicy: 'strict-origin-when-cross-origin',
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    tiles.on('tileerror', function () {
      if (++tileErrors === 5) msg('Gambar peta gagal dimuat. Buka halaman lewat http://localhost (bukan file://) dan periksa internet.');
    });
    tiles.on('tileload', function () { if (tileErrors) { tileErrors = 0; msg(''); } });

    var icon = L.divIcon({
      className: 'lf-pin-wrap',
      html: '<div class="lf-pin"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></div>',
      iconSize: [36, 42], iconAnchor: [18, 42]
    });
    var marker = null, reqId = 0;

    function buatMarker(latlng) {
      marker = L.marker(latlng, { icon: icon, draggable: editOn, keyboard: false }).addTo(map);
      marker.on('dragend', function () { var p = marker.getLatLng(); reverse(p.lat, p.lng); });
    }

    function simpan(lat, lng, b1, b2, penuh) {
      fLat.value = lat.toFixed(7); fLng.value = lng.toFixed(7);
      fAlamat.value = penuh || b1;
      tAlamat.textContent = b1; tKota.textContent = b2 || '';
    }

    function reverse(lat, lng) {
      var my = ++reqId;
      tAlamat.textContent = 'Mencari alamat…'; tKota.textContent = '';
      fLat.value = lat.toFixed(7); fLng.value = lng.toFixed(7);
      fAlamat.value = 'Titik di peta ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
      fetch(NOMINATIM + '/reverse?format=jsonv2&zoom=18&addressdetails=1&accept-language=id&lat=' + lat + '&lon=' + lng)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (my !== reqId) return;
          var a = d.address || {};
          var jalan = [a.road, a.house_number].filter(Boolean).join(' ');
          var area = a.suburb || a.neighbourhood || a.village || a.city_district;
          var b1 = [jalan, area].filter(Boolean).join(', ') || (d.display_name || '').split(',').slice(0, 2).join(',').trim();
          var kota = a.city || a.town || a.county || a.municipality;
          simpan(lat, lng, b1 || 'Titik di peta', [kota, a.state].filter(Boolean).join(', '), d.display_name || b1);
        })
        .catch(function () {
          if (my !== reqId) return;
          simpan(lat, lng, 'Titik di peta (' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ')', '', fAlamat.value);
        });
    }

    function pasang(latlng, zoomKe) {
      if (!marker) buatMarker(latlng); else marker.setLatLng(latlng);
      if (zoomKe) map.setView(latlng, Math.max(map.getZoom(), zoomKe));
      reverse(latlng.lat, latlng.lng);
    }

    // Mode lihat / ubah
    function setEdit(on) {
      editOn = on;
      ['dragging', 'touchZoom', 'doubleClickZoom', 'scrollWheelZoom', 'boxZoom', 'keyboard'].forEach(function (h) {
        if (map[h]) { if (on) map[h].enable(); else map[h].disable(); }
      });
      kotak.classList.toggle('map--lihat', !on);
      if (marker && marker.dragging) { if (on) marker.dragging.enable(); else marker.dragging.disable(); }
      if (barisCari) barisCari.hidden = !on;
      if (btnUbah) { var t = btnUbah.querySelector('.ubah__txt'); if (t) t.textContent = on ? 'Selesai' : 'Ubah lokasi'; }
      setTimeout(function () { map.invalidateSize(); }, 50);
    }

    map.on('click', function (e) { if (!editOn) return; msg(''); pasang(e.latlng); });
    if (startSet) buatMarker(start);
    setEdit(editOn);
    if (btnUbah) btnUbah.addEventListener('click', function () { setEdit(!editOn); });

    // Pencarian alamat
    function cariAlamat() {
      if (!cari) return;
      var q = cari.value.trim();
      if (!q) return;
      msg('Mencari…');
      fetch(NOMINATIM + '/search?format=jsonv2&limit=1&countrycodes=id&accept-language=id&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (list) {
          if (!list.length) { msg('Alamat tidak ditemukan. Coba kata kunci lain.'); return; }
          msg('');
          pasang(L.latLng(parseFloat(list[0].lat), parseFloat(list[0].lon)), 17);
        })
        .catch(function () { msg('Pencarian gagal. Periksa koneksi internet.'); });
    }
    if (btnCari) btnCari.addEventListener('click', cariAlamat);
    if (cari) cari.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); cariAlamat(); } });

    // Lokasi saya (butuh HTTPS atau localhost)
    if (btnSaya) btnSaya.addEventListener('click', function () {
      if (!navigator.geolocation) { msg('Peramban tidak mendukung lokasi.'); return; }
      msg('Mengambil lokasi…');
      navigator.geolocation.getCurrentPosition(
        function (p) { msg(''); pasang(L.latLng(p.coords.latitude, p.coords.longitude), 17); },
        function () { msg('Izin lokasi ditolak. Ketuk peta untuk memasang titik.'); },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });

    setTimeout(function () { map.invalidateSize(); }, 200);
    window.addEventListener('resize', function () { map.invalidateSize(); });
  }
});