// Chatbot sederhana berbasis kata kunci (demo, bukan AI). Balasan bisa diganti dengan API nanti.
document.addEventListener('DOMContentLoaded', function () {
  var chat = document.getElementById('chat');
  if (!chat) return;
  var log = document.getElementById('chat-log'), form = document.getElementById('chat-form'), input = document.getElementById('chat-input');
  var popup = chat.dataset.mode === 'popup';
  var statusPesanan = chat.dataset.status || '';

  function scrollBawah() { log.scrollTop = log.scrollHeight; }
  function buka() { chat.hidden = false; document.body.style.overflow = 'hidden'; scrollBawah(); setTimeout(function () { input.focus(); }, 50); }
  function tutup() { chat.hidden = true; document.body.style.overflow = ''; }

  if (!popup) { document.body.style.overflow = 'hidden'; scrollBawah(); }
  var tombol = document.getElementById('chat-open');
  if (tombol) tombol.addEventListener('click', buka);
  chat.querySelectorAll('[data-chat-close]').forEach(function (a) {
    a.addEventListener('click', function (e) { if (popup) { e.preventDefault(); tutup(); } });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popup && !chat.hidden) tutup(); });

  function jam() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + '.' + ('0' + d.getMinutes()).slice(-2); }
  function tambah(teks, siapa) {
    var m = document.createElement('div'); m.className = 'msg msg--' + siapa;
    var p = document.createElement('p'); p.textContent = teks;
    var t = document.createElement('time'); t.textContent = jam();
    m.appendChild(p); m.appendChild(t); log.appendChild(m); scrollBawah();
  }

  function balas(q) {
    q = q.toLowerCase();
    if (/status|pesanan|cucian saya|sampai mana/.test(q)) return statusPesanan || 'Belum ada data pesanan.';
    if (/tarif|harga|biaya|kiloan/.test(q)) return 'Tarif contoh: cuci kering Rp 12.000/kg dan cuci + setrika Rp 15.000/kg. Biaya final dihitung setelah ditimbang.';
    if (/jemput|antar|kurir/.test(q)) return 'Kurir menjemput dan mengantar ke alamat Anda. Buat pesanan lewat tombol "Tambah pesanan baru" dan centang Antar.';
    if (/bayar|qr|pembayaran/.test(q)) return 'Pembayaran lewat QR dari tombol "Bayar QR" di beranda, setelah cucian ditimbang.';
    if (/halo|hai|pagi|siang|sore|malam/.test(q)) return 'Halo! Mau tanya soal status pesanan, tarif, jemput-antar, atau pembayaran?';
    return 'Maaf, saya belum paham. Coba tanyakan soal status pesanan, tarif, jemput-antar, atau pembayaran.';
  }

  function kirim(teks) {
    teks = teks.trim(); if (!teks) return;
    tambah(teks, 'me');
    setTimeout(function () { tambah(balas(teks), 'bot'); }, 600);
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); kirim(input.value); input.value = ''; });
  log.addEventListener('click', function (e) {
    var c = e.target.closest('.chat__chip'); if (!c) return;
    c.remove(); kirim(c.dataset.q);
  });
});