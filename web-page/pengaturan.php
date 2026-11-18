<?php
function asetv(string $f): int { $t = @filemtime(__DIR__ . '/' . $f); return $t ?: 1; }  // aman walau file belum ada
// Halaman: Pengaturan akun
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['pelanggan'])) { header('Location: lengkapi-data.php'); exit; }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$user = $_SESSION['user'];
$v = $_SESSION['pelanggan'];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nama','wa','alamat','lat','lng','patokan'] as $k) { $v[$k] = trim($_POST[$k] ?? ''); }
    if (mb_strlen($v['nama']) < 3)                          $err['nama'] = 'Isi nama lengkap, minimal 3 huruf.';
    if (!preg_match('/^(\+62|62|0)8\d{8,12}$/', $v['wa'])) $err['wa']   = 'Nomor WhatsApp tidak valid. Contoh: 081234567890.';
    $lat = filter_var($v['lat'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($v['lng'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180 || $v['alamat'] === '')
        $err['lokasi'] = 'Lokasi tidak valid. Tekan Ubah lokasi lalu pasang titik di peta.';
    if (!$err) {
        $_SESSION['pelanggan'] = $v;                       // sementara di sesi
        $_SESSION['flash_set'] = 'Perubahan tersimpan.';
        header('Location: pengaturan.php');
        exit;
    }
}
$flash = $_SESSION['flash_set'] ?? ''; unset($_SESSION['flash_set']);
$inisial = mb_strtoupper(mb_substr($v['nama'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>LaundryFlow · Pengaturan akun</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= asetv('assets/css/style.css') ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
  <script src="assets/js/lokasi.js?v=<?= asetv('assets/js/lokasi.js') ?>" defer></script>
</head>
<body>
<form class="screen" method="post" action="" novalidate>

  <div class="pagebar">
    <a class="iconbtn" href="beranda.php" aria-label="Kembali"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>
    <h2>Pengaturan akun</h2>
  </div>

  <div class="pad">
    <?php if ($flash): ?><div class="notice" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert" role="alert">Periksa kembali kolom yang bertanda merah.</div><?php endif; ?>

    <div class="card profil">
      <div class="avatar avatar--lg"><?= e($inisial) ?></div>
      <div class="profil__id"><b><?= e($v['nama']) ?></b><small>Akun pelanggan · <?= e($user['email']) ?></small></div>
    </div>

    <div class="form">
      <div class="field <?= isset($err['nama']) ? 'has-error' : '' ?>">
        <label for="nama">Nama lengkap</label>
        <input id="nama" name="nama" type="text" autocomplete="name" value="<?= e($v['nama']) ?>">
        <?php if (isset($err['nama'])): ?><p class="error"><?= e($err['nama']) ?></p><?php endif; ?>
      </div>

      <div class="field <?= isset($err['wa']) ? 'has-error' : '' ?>">
        <label for="wa">Nomor handphone</label>
        <input id="wa" name="wa" type="tel" inputmode="tel" autocomplete="tel" value="<?= e($v['wa']) ?>">
        <?php if (isset($err['wa'])): ?><p class="error"><?= e($err['wa']) ?></p><?php endif; ?>
      </div>

      <div class="field <?= isset($err['lokasi']) ? 'has-error' : '' ?>">
        <div class="label-row">
          <label for="cari-lokasi">Lokasi penjemputan</label>
          <button class="link-btn" id="ubah-lokasi" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.81a1 1 0 0 0-3.99-3.99L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/><path d="m15 5 4 4"/></svg>
            <span class="ubah__txt">Ubah lokasi</span>
          </button>
        </div>

        <div id="baris-cari" hidden>
          <div class="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="cari-lokasi" type="search" enterkeyhint="search" placeholder="Cari alamat atau kawasan" autocomplete="off">
            <button class="search__go" id="btn-cari" type="button">Cari</button>
          </div>
        </div>
        <p class="error" id="cari-status" role="status" hidden></p>
        <?php if (isset($err['lokasi'])): ?><p class="error"><?= e($err['lokasi']) ?></p><?php endif; ?>

        <div class="mapcard">
          <div class="map map--kecil map--lihat">
            <div id="peta" data-mode="lihat" data-lat="<?= e($v['lat']) ?>" data-lng="<?= e($v['lng']) ?>" data-set="1" aria-label="Peta lokasi penjemputan"></div>
            <button class="locate" id="btn-lokasi-saya" type="button" aria-label="Gunakan lokasi saya"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" x2="5" y1="12" y2="12"/><line x1="19" x2="22" y1="12" y2="12"/><line x1="12" x2="12" y1="2" y2="5"/><line x1="12" x2="12" y1="19" y2="22"/><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
          <div class="addr">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <div><strong id="alamat-terpilih"><?= e($v['alamat']) ?></strong><small id="kota-terpilih"></small></div>
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

      <a class="logout" href="logout.php">Keluar dari akun</a>
    </div>
  </div>

  <div class="actionbar pad">
    <button class="btn" type="submit">Simpan perubahan</button>
    <small>Perubahan berlaku untuk pesanan berikutnya.</small>
  </div>
</form>
</body>
</html>