<?php
// Memverifikasi Firebase ID token di server lalu membuat sesi PHP.
// Verifikasi memakai REST Identity Toolkit (tanpa library tambahan, butuh ekstensi cURL).
session_start();
header('Content-Type: application/json; charset=utf-8');

const FIREBASE_API_KEY    = 'AIzaSyCxxjZ9LB6GK0SPGTazEUSehFeDGTnXGUs';
const FIREBASE_PROJECT_ID = 'bpplaundry';

function out(int $code, array $body){ http_response_code($code); echo json_encode($body); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok'=>false,'error'=>'Metode tidak diizinkan.']);

$in = json_decode(file_get_contents('php://input'), true);
$idToken = is_array($in) ? ($in['idToken'] ?? '') : '';
if (!is_string($idToken) || $idToken === '') out(400, ['ok'=>false,'error'=>'Token tidak ada.']);

$ch = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . FIREBASE_API_KEY);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = $resp ? json_decode($resp, true) : null;
$u = $data['users'][0] ?? null;
if ($code !== 200 || !$u) out(401, ['ok'=>false,'error'=>'Token tidak valid atau kedaluwarsa.']);

// Pastikan akun ini memang login lewat Google
$google = false;
foreach (($u['providerUserInfo'] ?? []) as $p) { if (($p['providerId'] ?? '') === 'google.com') $google = true; }
if (!$google) out(403, ['ok'=>false,'error'=>'Hanya login Google yang diizinkan.']);

session_regenerate_id(true);
$_SESSION['user'] = [
  'uid'   => $u['localId'],
  'email' => $u['email'] ?? '',
  'nama'  => $u['displayName'] ?? '',
  'foto'  => $u['photoUrl'] ?? '',
];
out(200, ['ok'=>true]);