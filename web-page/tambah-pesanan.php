<?php
function asetv(string $f): int { $t = @filemtime(__DIR__ . '/' . $f); return $t ?: 1; }  // aman walau file belum ada
// Halaman: Tambah pesanan
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['pelanggan'])) { header('Location: lengkapi-data.php'); exit; }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$p = $_SESSION['pelanggan'];
$deskripsi = '';
$antar = true;
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $antar = isset($_POST['antar']);
    if (mb_strlen($deskripsi) < 5)   $err['deskripsi'] = 'Jelaskan cucian Anda, minimal 5 karakter.';
    if (mb_strlen($deskripsi) > 500) $err['deskripsi'] = 'Deskripsi terlalu panjang (maksimal 500 karakter).';

    if (!$err) {
        $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $baru = [
          'no'=>'#LF-' . random_int(1000, 9999),
          'tgl'=>date('d') . ' ' . $bulan[(int)date('n') - 1] . ' ' . date('Y'),
          'status'=>'Menunggu jemput', 'kelas'=>'proses',
          'layanan'=>$antar ? 'Jemput & antar' : 'Antar sendiri',
          'berat'=>'Ditimbang saat dijemput', 'tagihan'=>null,
          'progres'=>'Pesanan diterima', 'estimasi'=>$deskripsi,
        ];
        $_SESSION['pesanan_baru'] = array_merge([$baru], $_SESSION['pesanan_baru'] ?? []);   // sementara di sesi
        $_SESSION['flash'] = 'Pesanan ' . $baru['no'] . ' berhasil dikirim.';
        header('Location: beranda.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>LaundryFlow · Tambah pesanan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= asetv('assets/css/style.css') ?>">
</head>
<body>
<form class="screen" method="post" action="" novalidate>

  <div class="pagebar">
    <a class="iconbtn" href="beranda.php" aria-label="Kembali"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>
    <h2>Tambah pesanan</h2>
  </div>

  <div class="pad">
    <div class="intro">
      <h1>Apa yang mau dicuci?</h1>
      <p>Ceritakan cucian Anda. Berat dan biaya dihitung setelah ditimbang.</p>
    </div>

    <div class="form">
      <div class="field <?= isset($err['deskripsi']) ? 'has-error' : '' ?>">
        <label for="deskripsi">Deskripsi</label>
        <textarea id="deskripsi" name="deskripsi" rows="4" maxlength="500" placeholder="Contoh: 5 kemeja, 2 celana, 1 seprai. Tolong setrika rapi."><?= e($deskripsi) ?></textarea>
        <?php if (isset($err['deskripsi'])): ?><p class="error"><?= e($err['deskripsi']) ?></p><?php endif; ?>
      </div>

      <div class="card antar">
        <label class="antar__row">
          <input class="check" type="checkbox" name="antar" value="1" <?= $antar ? 'checked' : '' ?>>
          <span class="antar__txt"><b>Antar</b><small>Kurir menjemput cucian di alamat Anda dan mengantarnya kembali setelah selesai.</small></span>
          <svg class="antar__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
        </label>
        <div class="antar__alamat">
          <hr>
          <p><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg><span><?= e($p['alamat']) ?></span></p>
        </div>
      </div>
    </div>
  </div>

  <div class="actionbar pad">
    <button class="btn" type="submit">Kirim pesanan</button>
    <small>Biaya dihitung setelah cucian ditimbang. Bayar lewat QR.</small>
  </div>
</form>
</body>
</html>