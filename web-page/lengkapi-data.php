<?php
// Halaman: Lengkapi data
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$user = $_SESSION['user'];

// Titik awal peta: Makassar
const DEFAULT_LAT = -5.1477;
const DEFAULT_LNG = 119.4327;

$v = ['nama'=>$user['nama'], 'wa'=>'', 'otp'=>'', 'alamat'=>'', 'lat'=>'', 'lng'=>'', 'patokan'=>''];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) { $v[$k] = trim($_POST[$k] ?? ''); }

    if (mb_strlen($v['nama']) < 3)                          $err['nama'] = 'Isi nama lengkap, minimal 3 huruf.';
    if (!preg_match('/^(\+62|62|0)8\d{8,12}$/', $v['wa'])) $err['wa']   = 'Nomor WhatsApp tidak valid. Contoh: 081234567890.';
    if (!preg_match('/^\d{6}$/', $v['otp']))                $err['otp']  = 'Masukkan 6 digit kode OTP.';

    $lat = filter_var($v['lat'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($v['lng'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180 || $v['alamat'] === '')
        $err['lokasi'] = 'Pasang titik lokasi dengan mengetuk peta atau mencari alamat.';

    if (!$err) {
        $_SESSION['pelanggan'] = $v + ['email'=>$user['email'], 'uid'=>$user['uid']];   // sementara di sesi (lat, lng, alamat ikut tersimpan)
        header('Location: beranda.php');
        exit;
    }
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$hasPoint  = $v['lat'] !== '' && $v['lng'] !== '';
$mapLat    = $hasPoint ? $v['lat'] : DEFAULT_LAT;
$mapLng    = $hasPoint ? $v['lng'] : DEFAULT_LNG;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>LaundryFlow · Lengkapi data</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>" defer></script>
  <script src="assets/js/lokasi.js?v=<?= filemtime(__DIR__ . '/assets/js/lokasi.js') ?>" defer></script>
</head>
<body>
<form class="screen" method="post" action="" novalidate>

  <div class="pagebar">
    <a class="iconbtn" href="index.php" aria-label="Kembali">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <h2>Lengkapi data</h2>
  </div>

  <div class="pad">
    <div class="intro">
      <h1>Satu langkah lagi sebelum mulai</h1>
      <p>Kami butuh kontak dan lokasi agar kurir bisa menjemput cucian Anda.</p>
    </div>

    <?php if ($err): ?><div class="alert" role="alert"><?= 'Periksa kembali kolom yang bertanda merah.' ?></div><?php endif; ?>

    <div class="form">
      <div class="field <?= isset($err['nama']) ? 'has-error' : '' ?>">
        <label for="nama">Nama lengkap</label>
        <input id="nama" name="nama" type="text" autocomplete="name" placeholder="Nama sesuai identitas" value="<?= e($v['nama']) ?>">
        <?php if (isset($err['nama'])): ?><p class="error"><?= e($err['nama']) ?></p><?php endif; ?>
      </div>

      <div>
        <div class="field <?= isset($err['wa']) ? 'has-error' : '' ?>">
          <label for="wa">Nomor WhatsApp</label>
          <input id="wa" name="wa" type="tel" inputmode="tel" autocomplete="tel" placeholder="081234567890" value="<?= e($v['wa']) ?>">
          <?php if (isset($err['wa'])): ?><p class="error"><?= e($err['wa']) ?></p><?php endif; ?>
        </div>
        <p class="help">Status cucian dan kode OTP dikirim ke nomor ini.</p>
        <div class="otp-row">
          <div class="field <?= isset($err['otp']) ? 'has-error' : '' ?>">
            <label for="otp">Kode OTP</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 digit" value="<?= e($v['otp']) ?>">
          </div>
          <button class="btn btn--ghost" id="kirim-otp" type="button">Kirim OTP</button>
        </div>
        <?php if (isset($err['otp'])): ?><p class="error"><?= e($err['otp']) ?></p><?php endif; ?>
      </div>

      <!-- Lokasi pelanggan (Leaflet + OpenStreetMap) -->
      <div class="field <?= isset($err['lokasi']) ? 'has-error' : '' ?>">
        <label for="cari-lokasi">Lokasi penjemputan</label>
        <div class="search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input id="cari-lokasi" type="search" enterkeyhint="search" placeholder="Cari alamat atau kawasan" autocomplete="off">
          <button class="search__go" id="btn-cari" type="button">Cari</button>
        </div>
        <p class="error" id="cari-status" role="status" hidden></p>
        <?php if (isset($err['lokasi'])): ?><p class="error"><?= e($err['lokasi']) ?></p><?php endif; ?>

        <div class="mapcard">
          <div class="map">
            <div id="peta" data-lat="<?= e($mapLat) ?>" data-lng="<?= e($mapLng) ?>" data-set="<?= $hasPoint ? '1' : '0' ?>" aria-label="Peta untuk menentukan lokasi penjemputan"></div>
            <button class="locate" id="btn-lokasi-saya" type="button" aria-label="Gunakan lokasi saya"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" x2="5" y1="12" y2="12"/><line x1="19" x2="22" y1="12" y2="12"/><line x1="12" x2="12" y1="2" y2="5"/><line x1="12" x2="12" y1="19" y2="22"/><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
          <div class="addr">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <div>
              <strong id="alamat-terpilih"><?= $hasPoint ? e($v['alamat']) : 'Belum ada titik dipasang' ?></strong>
              <small id="kota-terpilih"><?= $hasPoint ? '' : 'Ketuk peta untuk memasang titik' ?></small>
            </div>
          </div>
        </div>
        <input type="hidden" name="alamat" id="alamat" value="<?= e($v['alamat']) ?>">
        <input type="hidden" name="lat" id="lat" value="<?= e($v['lat']) ?>">
        <input type="hidden" name="lng" id="lng" value="<?= e($v['lng']) ?>">
      </div>

      <div class="field">
        <label for="patokan">Patokan <span style="font-weight:400;color:var(--muted)">(opsional)</span></label>
        <input id="patokan" name="patokan" type="text" placeholder="Contoh: pagar hijau, dekat masjid" value="<?= e($v['patokan']) ?>">
      </div>

      <p class="hint">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:2px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        Ketuk peta untuk memasang titik, lalu geser titik atau perbesar peta agar tepat di depan rumah Anda.
      </p>
    </div>
  </div>

  <div class="actionbar pad">
    <button class="btn" type="submit">Simpan &amp; lanjutkan</button>
    <small>Data bisa diubah kapan saja di profil.</small>
  </div>
</form>
</body>
</html>