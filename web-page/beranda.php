<?php
function asetv(string $f): int { $t = @filemtime(__DIR__ . '/' . $f); return $t ?: 1; }  // aman walau file belum ada
// Halaman: Beranda pelanggan. chatbot.php memakai file ini dengan $chatAktif = true.
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$p = $_SESSION['pelanggan'] ?? null;
if (!$p) { header('Location: lengkapi-data.php'); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$chatAktif = !empty($chatAktif);
$nama    = $p['nama'];
$depan   = explode(' ', $nama)[0];
$inisial = mb_strtoupper(mb_substr($nama, 0, 1));

$pesanan = array_merge($_SESSION['pesanan_baru'] ?? [], require __DIR__ . '/data-pesanan.php');
$rp = fn($n) => $n === null ? 'Belum dihitung' : 'Rp ' . number_format($n, 0, ',', '.');
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$statusChat = $pesanan ? ('Pesanan ' . $pesanan[0]['no'] . ': ' . $pesanan[0]['progres'] . '. ' . $pesanan[0]['estimasi'] . '.') : 'Anda belum punya pesanan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>LaundryFlow · Beranda</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= asetv('assets/css/style.css') ?>">
  <script src="assets/js/app.js?v=<?= asetv('assets/js/app.js') ?>" defer></script>
  <script src="assets/js/chatbot.js?v=<?= asetv('assets/js/chatbot.js') ?>" defer></script>
</head>
<body>
<main class="screen">

  <header class="topbar pad">
    <div class="brand">
      <div class="brand__logo" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>
      </div>
      <span class="brand__name">LaundryFlow</span>
    </div>
    <a class="avatar" href="pengaturan.php" title="Pengaturan akun" aria-label="Pengaturan akun"><?= e($inisial) ?></a>
  </header>

  <section class="greet pad">
    <h1>Halo, <?= e($depan) ?></h1>
    <p>Mau cuci apa hari ini?</p>
  </section>

  <section class="actions pad">
    <a class="btn" href="tambah-pesanan.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      Tambah pesanan baru
    </a>
    <a class="btn btn--outline" href="#">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
      Bayar QR
    </a>
  </section>

  <section class="orders pad" aria-labelledby="judul-pesanan">
    <?php if ($flash): ?><div class="notice" role="status"><?= e($flash) ?></div><?php endif; ?>
    <div class="orders__head">
      <h2 id="judul-pesanan">Pesanan saya</h2>
      <span><?= count($pesanan) ?> pesanan</span>
    </div>

    <?php foreach ($pesanan as $i => $o): $open = $i === 0; $pid = "panel-$i"; ?>
    <article class="card acc">
      <button class="acc__btn" type="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $pid ?>">
        <span class="acc__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg></span>
        <span class="acc__id"><b><?= e($o['no']) ?></b><small><?= e($o['tgl']) ?></small></span>
        <span class="badge badge--<?= e($o['kelas']) ?>"><?= e($o['status']) ?></span>
        <svg class="acc__chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="acc__panel" id="<?= $pid ?>" <?= $open ? '' : 'hidden' ?>>
        <hr>
        <div class="bill">
          <div><b><?= e($o['layanan']) ?></b><small><?= e($o['berat']) ?></small></div>
          <strong><?= e($rp($o['tagihan'])) ?></strong>
        </div>
        <div class="track">
          <b><?= e($o['progres']) ?></b>
          <small><?= e($o['estimasi']) ?></small>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </section>

  <button class="fab" type="button" id="chat-open" aria-label="Buka chatbot" aria-controls="chat">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
  </button>
</main>

<!-- Chatbot (popup). Terbuka otomatis di chatbot.php -->
<div class="chat" id="chat" data-mode="<?= $chatAktif ? 'halaman' : 'popup' ?>" data-status="<?= e($statusChat) ?>" role="dialog" aria-modal="true" aria-labelledby="chat-judul" <?= $chatAktif ? '' : 'hidden' ?>>
  <a class="chat__overlay" href="beranda.php" data-chat-close aria-label="Tutup chatbot"></a>
  <div class="chat__wrap">
    <section class="chat__panel">
      <header class="chat__head">
        <div class="chat__avatar" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg></div>
        <div class="chat__who"><b id="chat-judul">Asisten Laundry</b><small>Online · membalas otomatis</small></div>
        <a class="iconbtn" href="beranda.php" data-chat-close aria-label="Tutup chatbot"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
      </header>

      <div class="chat__log" id="chat-log" aria-live="polite">
        <p class="chat__date">Hari ini</p>
        <div class="msg msg--bot"><p>Halo <?= e($depan) ?>! Saya asisten LaundryFlow. Ada yang bisa saya bantu soal pesanan, tarif, atau jemput-antar?</p><time><?= date('H.i') ?></time></div>
        <div class="msg msg--me"><p>Bagaimana status pesanan saya?</p><time><?= date('H.i') ?></time></div>
        <div class="msg msg--bot"><p><?= e($statusChat) ?></p><time><?= date('H.i') ?></time></div>
        <button class="chat__chip" type="button" data-q="Berapa tarif cuci kiloan?">Berapa tarif cuci kiloan?</button>
      </div>

      <form class="chat__form" id="chat-form" autocomplete="off">
        <input id="chat-input" type="text" placeholder="Tulis pesan…" aria-label="Tulis pesan">
        <button class="chat__send" type="submit" aria-label="Kirim pesan"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 21.7a.5.5 0 0 0 .9-.1l6.6-19a.5.5 0 0 0-.6-.6l-19 6.6a.5.5 0 0 0-.1.9l7.9 3.2a2 2 0 0 1 1.1 1.1z"/><path d="m21.9 2.1-10.8 10.8"/></svg></button>
      </form>
    </section>
  </div>
</div>
</body>
</html>