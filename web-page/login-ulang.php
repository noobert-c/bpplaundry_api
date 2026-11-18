<?php
// Halaman: Masuk kembali (nomor terdaftar + verifikasi OTP) — UI saja
session_start();
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Nilai awal & pesan galat (nanti diisi oleh logika backend)
$wa   = '';
$step = 1;      // 1 = isi nomor, 2 = isi kode OTP
$err  = [];     // contoh: $err['wa'] = 'Nomor belum terdaftar.'; $err['otp'] = 'Kode OTP salah.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>LaundryFlow · Masuk</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>" defer></script>
  <style>
    /* Hanya tambahan kecil untuk halaman ini (memakai variabel dari style.css) */
    .otp-boxes{display:grid;grid-template-columns:repeat(6,1fr);gap:8px}
    .field .otp-boxes input{height:56px;padding:0;text-align:center;font-size:22px;font-weight:700}
    .resend{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;font-size:14px;color:var(--muted)}
    .resend strong{color:var(--ink)}
    .resend button,.ubah{background:none;border:0;padding:0;font:inherit;font-weight:700;color:var(--primary);cursor:pointer}
    .intro p strong{color:var(--ink)}
    [hidden]{display:none !important}
  </style>
</head>
<body>
<form class="screen" method="post" action="" novalidate id="form-masuk">

  <div class="pagebar">
    <a class="iconbtn" href="index.php" aria-label="Kembali">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <h2>Masuk</h2>
  </div>

  <div class="pad">

    <!-- LANGKAH 1: nomor terdaftar -->
    <section id="langkah-1" <?= $step === 1 ? '' : 'hidden' ?>>
      <div class="intro">
        <h1>Selamat datang kembali</h1>
        <p>Masukkan nomor WhatsApp yang terdaftar. Kami akan mengirim kode OTP untuk memastikan ini Anda.</p>
      </div>

      <?php if (isset($err['wa'])): ?><div class="alert" role="alert"><?= e($err['wa']) ?></div><?php endif; ?>

      <div class="form">
        <div class="field <?= isset($err['wa']) ? 'has-error' : '' ?>">
          <label for="wa">Nomor WhatsApp</label>
          <input id="wa" name="wa" type="tel" inputmode="tel" autocomplete="tel" placeholder="081234567890" value="<?= e($wa) ?>">
          <p class="error" id="wa-error" <?= isset($err['wa']) ? '' : 'hidden' ?>><?= e($err['wa'] ?? 'Nomor WhatsApp tidak valid. Contoh: 081234567890.') ?></p>
        </div>
        <p class="hint">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:2px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          Belum punya akun? <a href="login.php">Daftar di sini</a>.
        </p>
      </div>
    </section>

    <!-- LANGKAH 2: verifikasi OTP -->
    <section id="langkah-2" <?= $step === 2 ? '' : 'hidden' ?>>
      <div class="intro">
        <h1>Masukkan kode OTP</h1>
        <p>Kode 6 digit dikirim ke WhatsApp <strong id="wa-tampil">0812 •••• 7890</strong>. <button class="ubah" id="ubah-nomor" type="button">Ubah nomor</button></p>
      </div>

      <?php if (isset($err['otp'])): ?><div class="alert" role="alert"><?= e($err['otp']) ?></div><?php endif; ?>

      <div class="form">
        <div class="field <?= isset($err['otp']) ? 'has-error' : '' ?>">
          <label for="otp-1">Kode OTP</label>
          <div class="otp-boxes" id="otp-boxes">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <input id="otp-<?= $i ?>" type="text" inputmode="numeric" maxlength="1" pattern="\d" autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>" aria-label="Digit <?= $i ?>">
            <?php endfor; ?>
          </div>
          <input type="hidden" name="otp" id="otp">
          <p class="error" id="otp-error" <?= isset($err['otp']) ? '' : 'hidden' ?>><?= e($err['otp'] ?? 'Masukkan 6 digit kode OTP.') ?></p>
        </div>

        <div class="resend">
          <span id="hitung-teks">Kirim ulang kode dalam <strong id="hitung">00:60</strong></span>
          <button type="button" id="kirim-ulang" hidden>Kirim ulang kode</button>
        </div>
      </div>
    </section>

  </div>

  <div class="actionbar pad">
    <button class="btn" type="submit" id="btn-aksi">Kirim kode OTP</button>
    <small id="aksi-info">Kode dikirim lewat WhatsApp dan berlaku 5 menit.</small>
  </div>
</form>

<script>
// Interaksi UI saja (belum terhubung ke backend)
(function () {
  var form = document.getElementById('form-masuk');
  var s1 = document.getElementById('langkah-1'), s2 = document.getElementById('langkah-2');
  var wa = document.getElementById('wa'), waErr = document.getElementById('wa-error'), waTampil = document.getElementById('wa-tampil');
  var btn = document.getElementById('btn-aksi'), info = document.getElementById('aksi-info');
  var boxes = Array.prototype.slice.call(document.querySelectorAll('#otp-boxes input'));
  var otpHidden = document.getElementById('otp'), otpErr = document.getElementById('otp-error');
  var hitung = document.getElementById('hitung'), hitungTeks = document.getElementById('hitung-teks'), ulang = document.getElementById('kirim-ulang');
  var langkah = <?= (int)$step ?>, timer;

  function maskNomor(n) {
    n = n.replace(/\D/g, '');
    return n.length > 8 ? n.slice(0, 4) + ' •••• ' + n.slice(-4) : n;
  }
  function mulaiHitung() {
    var sisa = 60;
    clearInterval(timer);
    ulang.hidden = true; hitungTeks.hidden = false;
    function tik() {
      hitung.textContent = '00:' + String(sisa).padStart(2, '0');
      if (sisa-- <= 0) { clearInterval(timer); hitungTeks.hidden = true; ulang.hidden = false; }
    }
    tik(); timer = setInterval(tik, 1000);
  }
  function tampil(n) {
    langkah = n;
    s1.hidden = n !== 1; s2.hidden = n !== 2;
    btn.textContent = n === 1 ? 'Kirim kode OTP' : 'Verifikasi & masuk';
    info.textContent = n === 1 ? 'Kode dikirim lewat WhatsApp dan berlaku 5 menit.' : 'Pastikan nomor WhatsApp Anda aktif.';
    if (n === 2) { mulaiHitung(); boxes[0].focus(); } else { clearInterval(timer); wa.focus(); }
  }

  form.addEventListener('submit', function (ev) {
    if (langkah === 1) {
      ev.preventDefault();
      var ok = /^(\+62|62|0)8\d{8,12}$/.test(wa.value.trim());
      waErr.hidden = ok; wa.closest('.field').classList.toggle('has-error', !ok);
      if (ok) { waTampil.textContent = maskNomor(wa.value); tampil(2); }
    } else {
      var kode = boxes.map(function (b) { return b.value; }).join('');
      otpHidden.value = kode;
      if (!/^\d{6}$/.test(kode)) {
        ev.preventDefault();
        otpErr.hidden = false; otpHidden.closest('.field').classList.add('has-error');
      }
      // jika valid, form dikirim ke backend untuk diverifikasi
    }
  });

  document.getElementById('ubah-nomor').addEventListener('click', function () { tampil(1); });
  ulang.addEventListener('click', function () { mulaiHitung(); boxes.forEach(function (b) { b.value = ''; }); boxes[0].focus(); });

  // Kotak OTP: pindah otomatis, hapus mundur, dan tempel 6 digit
  boxes.forEach(function (b, i) {
    b.addEventListener('input', function () {
      b.value = b.value.replace(/\D/g, '').slice(-1);
      if (b.value && boxes[i + 1]) boxes[i + 1].focus();
    });
    b.addEventListener('keydown', function (ev) {
      if (ev.key === 'Backspace' && !b.value && boxes[i - 1]) boxes[i - 1].focus();
    });
    b.addEventListener('paste', function (ev) {
      var t = (ev.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!t) return;
      ev.preventDefault();
      t.split('').forEach(function (c, k) { if (boxes[k]) boxes[k].value = c; });
      boxes[Math.min(t.length, 5)].focus();
    });
  });

  if (langkah === 2) mulaiHitung();
})();
</script>
</body>
</html>