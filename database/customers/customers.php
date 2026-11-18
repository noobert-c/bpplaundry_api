
<?php
require_once "../db.php";

header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER["REQUEST_METHOD"];

// Membaca JSON dari request body
$input = json_decode(file_get_contents("php://input"), true);

// Mengambil parameter URL
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$user_id = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;

// Helper untuk mengirim respons JSON
function response($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($method) {

        // =========================
        // GET: READ DATA
        // =========================
        case "GET":
            if ($user_id <= 0) {
                response(400, [
                    "message" => "user_id wajib diisi"
                ]);
            }

            if ($id > 0) {
                // Ambil satu customer milik user
                $stmt = $pdo->prepare(
                    "SELECT id, google_id, user_id, name, phone, created_at
                     FROM customers
                     WHERE id = :id AND user_id = :user_id"
                );

                $stmt->execute([
                    ":id" => $id,
                    ":user_id" => $user_id
                ]);

                $customer = $stmt->fetch();

                if (!$customer) {
                    response(404, [
                        "message" => "Customer tidak ditemukan"
                    ]);
                }

                response(200, [
                    "message" => "Data customer berhasil diambil",
                    "data" => $customer
                ]);
            }

            // Ambil semua customer milik user
            $stmt = $pdo->prepare(
                "SELECT id, google_id, user_id, name, phone, created_at
                 FROM customers
                 WHERE user_id = :user_id
                 ORDER BY id DESC"
            );

            $stmt->execute([
                ":user_id" => $user_id
            ]);

            response(200, [
                "message" => "Data customer berhasil diambil",
                "data" => $stmt->fetchAll()
            ]);

        // =========================
        // POST: CREATE DATA
        // =========================
        case "POST":
            $user_id = (int) ($input["user_id"] ?? 0);
            $google_id = trim($input["google_id"] ?? "");
            $name = trim($input["name"] ?? "");
            $phone = trim($input["phone"] ?? "");

            if ($user_id <= 0 || $name === "") {
                response(400, [
                    "message" => "user_id dan name wajib diisi"
                ]);
            }

            // Pastikan user pemilik terdaftar
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE id = :id"
            );
            $stmt->execute([":id" => $user_id]);

            if (!$stmt->fetch()) {
                response(404, [
                    "message" => "User pemilik tidak ditemukan"
                ]);
            }

            // google_id bersifat opsional
            $google_id = $google_id !== "" ? $google_id : null;
            $phone = $phone !== "" ? $phone : null;

            $stmt = $pdo->prepare(
                "INSERT INTO customers
                    (google_id, user_id, name, phone)
                 VALUES
                    (:google_id, :user_id, :name, :phone)"
            );

            $stmt->execute([
                ":google_id" => $google_id,
                ":user_id" => $user_id,
                ":name" => $name,
                ":phone" => $phone
            ]);

            response(201, [
                "message" => "Customer berhasil ditambahkan",
                "data" => [
                    "id" => (int) $pdo->lastInsertId(),
                    "google_id" => $google_id,
                    "user_id" => $user_id,
                    "name" => $name,
                    "phone" => $phone
                ]
            ]);

        // =========================
        // PUT: UPDATE DATA
        // =========================
        case "PUT":
            if ($id <= 0 || $user_id <= 0) {
                response(400, [
                    "message" => "id dan user_id wajib diisi melalui URL"
                ]);
            }

            $name = trim($input["name"] ?? "");
            $phone = trim($input["phone"] ?? "");

            // Nama wajib diisi saat update
            if ($name === "") {
                response(400, [
                    "message" => "name wajib diisi"
                ]);
            }

            $google_id = trim($input["google_id"] ?? "");
            $google_id = $google_id !== "" ? $google_id : null;
            $phone = $phone !== "" ? $phone : null;

            // Pastikan customer milik user tersebut
            $stmt = $pdo->prepare(
                "SELECT id FROM customers
                 WHERE id = :id AND user_id = :user_id"
            );

            $stmt->execute([
                ":id" => $id,
                ":user_id" => $user_id
            ]);

            if (!$stmt->fetch()) {
                response(404, [
                    "message" => "Customer tidak ditemukan"
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE customers
                 SET google_id = :google_id,
                     name = :name,
                     phone = :phone
                 WHERE id = :id AND user_id = :user_id"
            );

            $stmt->execute([
                ":google_id" => $google_id,
                ":name" => $name,
                ":phone" => $phone,
                ":id" => $id,
                ":user_id" => $user_id
            ]);

            response(200, [
                "message" => "Customer berhasil diperbarui"
            ]);

        // =========================
        // DELETE: HAPUS DATA
        // =========================
        case "DELETE":
            if ($id <= 0 || $user_id <= 0) {
                response(400, [
                    "message" => "id dan user_id wajib diisi melalui URL"
                ]);
            }

            $stmt = $pdo->prepare(
                "DELETE FROM customers
                 WHERE id = :id AND user_id = :user_id"
            );

            $stmt->execute([
                ":id" => $id,
                ":user_id" => $user_id
            ]);

            if ($stmt->rowCount() === 0) {
                response(404, [
                    "message" => "Customer tidak ditemukan"
                ]);
            }

            response(200, [
                "message" => "Customer berhasil dihapus"
            ]);

        // =========================
        // METHOD TIDAK DIDUKUNG
        // =========================
        default:
            header("Allow: GET, POST, PUT, DELETE");

            response(405, [
                "message" => "Method tidak didukung"
            ]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    // Error duplicate google_id
    if ($e->getCode() === "23000") {
        response(409, [
            "message" => "Data bentrok: google_id mungkin sudah digunakan"
        ]);
    }

    response(500, [
        "message" => "Terjadi kesalahan database"
    ]);
}
