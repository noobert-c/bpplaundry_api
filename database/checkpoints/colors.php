
<?php
require_once "../db.php";

header("Content-Type: application/json; charset=UTF-8");

function response($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalisasi warna ke format 0xFF[HEX]
function normalizeColor($color)
{
    if (!is_string($color)) {
        return false;
    }

    $color = trim($color);

    // Hapus prefix yang mungkin sudah ada
    if (preg_match('/^0x[0-9a-fA-F]{8}$/', $color)) {
        $color = substr($color, 2);
    } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = substr($color, 1);
    }

    // Format akhir harus berupa 8 digit HEX dengan alpha FF
    if (!preg_match('/^[0-9a-fA-F]{8}$/', $color)) {
        if (preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            $color = "FF" . $color;
        } else {
            return false;
        }
    }

    // Pastikan alpha FF (warna tidak transparan)
    if (strtoupper(substr($color, 0, 2)) !== "FF") {
        return false;
    }

    return "0x" . strtoupper($color);
}

$method = $_SERVER["REQUEST_METHOD"];
$id = isset($_GET["id"]) ? (int) $_GET["id"] : null;

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($input !== "" && !is_array($data)) {
    response(400, ["message" => "Format JSON tidak valid"]);
}

$data = is_array($data) ? $data : [];

try {
    switch ($method) {
        // GET: Ambil semua warna atau satu warna
        case "GET":
            if ($id !== null) {
                $stmt = $pdo->prepare(
                    "SELECT id, name, color, is_used
                     FROM colors
                     WHERE id = ?"
                );
                $stmt->execute([$id]);

                $colorData = $stmt->fetch();

                if (!$colorData) {
                    response(404, ["message" => "Warna tidak ditemukan"]);
                }

                $colorData["is_used"] = (bool) $colorData["is_used"];

                response(200, $colorData);
            }

            $stmt = $pdo->query(
                "SELECT id, name, color, is_used
                 FROM colors
                 ORDER BY id DESC"
            );

            $colors = $stmt->fetchAll();

            foreach ($colors as &$colorData) {
                $colorData["is_used"] = (bool) $colorData["is_used"];
            }
            unset($colorData);

            response(200, $colors);

        // POST: Tambah warna
        case "POST":
            if (
                !isset($data["name"], $data["color"]) ||
                !is_string($data["name"]) ||
                trim($data["name"]) === ""
            ) {
                response(400, [
                    "message" => "name dan color wajib diisi"
                ]);
            }

            $name = trim($data["name"]);
            $color = normalizeColor($data["color"]);

            if ($color === false) {
                response(400, [
                    "message" => "Format color tidak valid. Gunakan 0xFF[HEX] atau 6 digit HEX"
                ]);
            }

            if (mb_strlen($name) > 100) {
                response(400, [
                    "message" => "name maksimal 100 karakter"
                ]);
            }

            $isUsed = $data["is_used"] ?? false;

            if (
                !is_bool($isUsed) &&
                !in_array($isUsed, [0, 1, "0", "1"], true)
            ) {
                response(400, [
                    "message" => "is_used harus berupa boolean"
                ]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO colors (name, color, is_used)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $color,
                (int) $isUsed
            ]);

            response(201, [
                "message" => "Warna berhasil ditambahkan",
                "id" => (int) $pdo->lastInsertId(),
                "color" => $color
            ]);

        // PUT: Perbarui warna
        case "PUT":
            if (!$id || $id <= 0) {
                response(400, [
                    "message" => "Parameter id wajib diisi"
                ]);
            }

            if (
                !isset($data["name"], $data["color"]) ||
                !is_string($data["name"]) ||
                trim($data["name"]) === ""
            ) {
                response(400, [
                    "message" => "name dan color wajib diisi"
                ]);
            }

            $name = trim($data["name"]);
            $color = normalizeColor($data["color"]);

            if ($color === false) {
                response(400, [
                    "message" => "Format color tidak valid. Gunakan 0xFF[HEX] atau 6 digit HEX"
                ]);
            }

            if (mb_strlen($name) > 100) {
                response(400, [
                    "message" => "name maksimal 100 karakter"
                ]);
            }

            $isUsed = $data["is_used"] ?? null;

            if (
                $isUsed !== null &&
                !is_bool($isUsed) &&
                !in_array($isUsed, [0, 1, "0", "1"], true)
            ) {
                response(400, [
                    "message" => "is_used harus berupa boolean"
                ]);
            }

            if ($isUsed === null) {
                $stmt = $pdo->prepare(
                    "UPDATE colors
                     SET name = ?, color = ?
                     WHERE id = ?"
                );
                $stmt->execute([$name, $color, $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE colors
                     SET name = ?, color = ?, is_used = ?
                     WHERE id = ?"
                );
                $stmt->execute([
                    $name,
                    $color,
                    (int) $isUsed,
                    $id
                ]);
            }

            $check = $pdo->prepare(
                "SELECT id FROM colors WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                response(404, [
                    "message" => "Warna tidak ditemukan"
                ]);
            }

            response(200, [
                "message" => "Warna berhasil diperbarui",
                "color" => $color
            ]);

        // DELETE: Hapus warna
        case "DELETE":
            if (!$id || $id <= 0) {
                response(400, [
                    "message" => "Parameter id wajib diisi"
                ]);
            }

            $stmt = $pdo->prepare(
                "DELETE FROM colors WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                response(404, [
                    "message" => "Warna tidak ditemukan"
                ]);
            }

            response(200, [
                "message" => "Warna berhasil dihapus"
            ]);

        default:
            header("Allow: GET, POST, PUT, DELETE");
            response(405, [
                "message" => "Method tidak diizinkan"
            ]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());

    response(500, [
        "message" => "Terjadi kesalahan database"
    ]);
}
