<?php
require_once "../db.php";

header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER["REQUEST_METHOD"];
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$user_id = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($method) {
        case "GET":
            if ($user_id <= 0) {
                respond(400, ["message" => "user_id wajib diisi"]);
            }

            // Jangan pernah mengembalikan nilai OTP melalui GET.
            $sql = "SELECT id, user_id, expires_at, used_at
                    FROM otp_codes WHERE user_id = :user_id";
            $params = [":user_id" => $user_id];

            if ($id > 0) {
                $sql .= " AND id = :id";
                $params[":id"] = $id;
            }
            $sql .= " ORDER BY id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            if ($id > 0 && !$rows) {
                respond(404, ["message" => "OTP tidak ditemukan"]);
            }

            respond(200, [
                "message" => "Metadata OTP berhasil diambil",
                "data" => $id > 0 ? $rows[0] : $rows
            ]);

        case "POST":
            $user_id = (int) ($input["user_id"] ?? 0);
            $expires_in = (int) ($input["expires_in_seconds"] ?? 300);

            if ($user_id <= 0) {
                respond(400, ["message" => "user_id wajib diisi"]);
            }
            if ($expires_in < 60 || $expires_in > 900) {
                respond(400, [
                    "message" => "expires_in_seconds harus antara 60 dan 900"
                ]);
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id");
            $stmt->execute([":id" => $user_id]);
            if (!$stmt->fetch()) {
                respond(404, ["message" => "User tidak ditemukan"]);
            }

            // Invalidasi OTP aktif sebelumnya untuk user ini.
            $stmt = $pdo->prepare(
                "UPDATE otp_codes
                 SET used_at = NOW()
                 WHERE user_id = :user_id
                   AND used_at IS NULL
                   AND expires_at > NOW()"
            );
            $stmt->execute([":user_id" => $user_id]);

            // Kode 6 digit kriptografis. Simpan sebagai string agar nol awal terjaga.
            $code = (string) random_int(100000, 999999);
            $stmt = $pdo->prepare(
                "INSERT INTO otp_codes (user_id, code, expires_at)
                 VALUES (:user_id, :code, DATE_ADD(NOW(), INTERVAL :seconds SECOND))"
            );
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":code", $code, PDO::PARAM_STR);
            $stmt->bindValue(":seconds", $expires_in, PDO::PARAM_INT);
            $stmt->execute();

            // Kode dikembalikan hanya sekali saat dibuat untuk diteruskan ke kanal
            // pengiriman OTP yang tepercaya. Jangan mencatatnya di log.
            respond(201, [
                "message" => "OTP berhasil dibuat; kirim kode melalui kanal tepercaya",
                "data" => [
                    "id" => (int) $pdo->lastInsertId(),
                    "user_id" => $user_id,
                    "code" => $code,
                    "expires_in_seconds" => $expires_in
                ]
            ]);

        case "PUT":
            if ($id <= 0 || $user_id <= 0) {
                respond(400, ["message" => "id dan user_id wajib diisi melalui URL"]);
            }

            // Update dibatasi pada penandaan sudah digunakan.
            // Jangan sediakan edit kode OTP atau perpanjangan masa berlaku via CRUD.
            $used = $input["used"] ?? null;
            if (!is_bool($used)) {
                respond(400, ["message" => "Body harus berisi used: true atau false"]);
            }

            $stmt = $pdo->prepare(
                "UPDATE otp_codes
                 SET used_at = " . ($used ? "NOW()" : "NULL") . "
                 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([":id" => $id, ":user_id" => $user_id]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    "SELECT id FROM otp_codes WHERE id = :id AND user_id = :user_id"
                );
                $check->execute([":id" => $id, ":user_id" => $user_id]);
                if (!$check->fetch()) {
                    respond(404, ["message" => "OTP tidak ditemukan"]);
                }
            }

            respond(200, ["message" => "Status OTP berhasil diperbarui"]);

        case "DELETE":
            if ($id <= 0 || $user_id <= 0) {
                respond(400, ["message" => "id dan user_id wajib diisi melalui URL"]);
            }

            $stmt = $pdo->prepare(
                "DELETE FROM otp_codes WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([":id" => $id, ":user_id" => $user_id]);

            if ($stmt->rowCount() === 0) {
                respond(404, ["message" => "OTP tidak ditemukan"]);
            }

            respond(200, ["message" => "OTP berhasil dihapus"]);

        default:
            header("Allow: GET, POST, PUT, DELETE");
            respond(405, ["message" => "Method tidak didukung"]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    respond(500, ["message" => "Terjadi kesalahan database"]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(500, ["message" => "Terjadi kesalahan server"]);
}
