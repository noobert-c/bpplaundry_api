
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once "../db.php";

$method = $_SERVER["REQUEST_METHOD"];

// Handle preflight request
if ($method === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Baca JSON request body
$input = json_decode(file_get_contents("php://input"), true);

try {
    switch ($method) {

        // ==========================================
        // GET: AMBIL SEMUA PAKET / PAKET BERDASARKAN ID
        // ==========================================
        case "GET":

            if (isset($_GET["id"])) {
                $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

                if ($id === false || $id < 1) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "ID paket tidak valid"
                    ]);
                    exit;
                }

                $stmt = $pdo->prepare(
                    "SELECT id, name, price_per_kg, est_days
                     FROM packages
                     WHERE id = ?"
                );

                $stmt->execute([$id]);
                $package = $stmt->fetch();

                if (!$package) {
                    http_response_code(404);
                    echo json_encode([
                        "success" => false,
                        "message" => "Paket tidak ditemukan"
                    ]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "data" => $package
                ]);
            } else {
                $stmt = $pdo->query(
                    "SELECT id, name, price_per_kg, est_days
                     FROM packages
                     ORDER BY id DESC"
                );

                echo json_encode([
                    "success" => true,
                    "data" => $stmt->fetchAll()
                ]);
            }

            break;

        // ==========================================
        // POST: TAMBAH PAKET
        // ==========================================
        case "POST":

            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Body JSON tidak valid"
                ]);
                exit;
            }

            $name = trim($input["name"] ?? "");
            $price = $input["price_per_kg"] ?? null;
            $days = $input["est_days"] ?? null;

            if (
                $name === "" ||
                !is_numeric($price) ||
                !is_numeric($days) ||
                !is_finite((float) $price) ||
                (float) $price < 0 ||
                (float) $price > 99999999.99 ||
                filter_var($days, FILTER_VALIDATE_INT) === false ||
                (int) $days < 1
            ) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Nama, harga, atau estimasi hari tidak valid"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO packages (name, price_per_kg, est_days)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $price,
                (int) $days
            ]);

            http_response_code(201);

            echo json_encode([
                "success" => true,
                "message" => "Paket berhasil ditambahkan",
                "data" => [
                    "id" => (int) $pdo->lastInsertId(),
                    "name" => $name,
                    "price_per_kg" => (float) $price,
                    "est_days" => (int) $days
                ]
            ]);

            break;

        // ==========================================
        // PUT: UBAH PAKET
        // ==========================================
        case "PUT":

            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Body JSON tidak valid"
                ]);
                exit;
            }

            $id = filter_var(
                $input["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            $name = trim($input["name"] ?? "");
            $price = $input["price_per_kg"] ?? null;
            $days = $input["est_days"] ?? null;

            if (
                $id === false ||
                $id < 1 ||
                $name === "" ||
                !is_numeric($price) ||
                !is_finite((float) $price) ||
                (float) $price < 0 ||
                (float) $price > 99999999.99 ||
                filter_var($days, FILTER_VALIDATE_INT) === false ||
                (int) $days < 1
            ) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "ID, nama, harga, atau estimasi hari tidak valid"
                ]);
                exit;
            }

            $check = $pdo->prepare(
                "SELECT id FROM packages WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Paket tidak ditemukan"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "UPDATE packages
                 SET name = ?, price_per_kg = ?, est_days = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $name,
                $price,
                (int) $days,
                $id
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Paket berhasil diperbarui",
                "data" => [
                    "id" => (int) $id,
                    "name" => $name,
                    "price_per_kg" => (float) $price,
                    "est_days" => (int) $days
                ]
            ]);

            break;

        // ==========================================
        // DELETE: HAPUS PAKET
        // ==========================================
        case "DELETE":

            $id = filter_var(
                $_GET["id"] ?? ($input["id"] ?? null),
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id === null || $id < 1) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "ID paket tidak valid"
                ]);
                exit;
            }

            $check = $pdo->prepare(
                "SELECT id FROM packages WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Paket tidak ditemukan"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "DELETE FROM packages WHERE id = ?"
            );

            $stmt->execute([$id]);

            echo json_encode([
                "success" => true,
                "message" => "Paket berhasil dihapus"
            ]);

            break;

        // ==========================================
        // METHOD TIDAK DIDUKUNG
        // ==========================================
        default:

            header("Allow: GET, POST, PUT, DELETE, OPTIONS");
            http_response_code(405);

            echo json_encode([
                "success" => false,
                "message" => "Method tidak didukung"
            ]);

            break;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan database"
    ]);
}
