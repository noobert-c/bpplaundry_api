
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../db.php";

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, ["message" => "Method tidak didukung"]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    respond(400, ["message" => "JSON tidak valid"]);
}

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || !is_string($password) || $password === "") {
    respond(422, ["message" => "Email dan password wajib diisi"]);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, phone, password, created_at
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $password !== $user["password"]) {
        respond(401, ["message" => "Email atau password salah"]);
    }

    // Jangan kirim password ke Android.
    unset($user["password"]);

    respond(200, [
        "message" => "Login berhasil",
        "data" => $user
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    respond(500, ["message" => "Terjadi kesalahan database"]);
}
