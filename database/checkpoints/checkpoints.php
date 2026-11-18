
<?php
require_once "../db.php";

header("Content-Type: application/json; charset=UTF-8");

function response($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
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
        // GET: Ambil semua data atau satu data berdasarkan ID
        case "GET":
            if ($id !== null) {
                $stmt = $pdo->prepare(
                    "SELECT id, color_id, name
                     FROM checkpoints
                     WHERE id = ?"
                );
                $stmt->execute([$id]);

                $checkpoint = $stmt->fetch();

                if (!$checkpoint) {
                    response(404, ["message" => "Checkpoint tidak ditemukan"]);
                }

                response(200, $checkpoint);
            }

            $stmt = $pdo->query(
                "SELECT id, color_id, name
                 FROM checkpoints
                 ORDER BY id DESC"
            );

            response(200, $stmt->fetchAll());

        // POST: Tambah checkpoint
        case "POST":
            if (
                !isset($data["color_id"], $data["name"]) ||
                !is_numeric($data["color_id"]) ||
                (int) $data["color_id"] <= 0 ||
                !is_string($data["name"]) ||
                trim($data["name"]) === ""
            ) {
                response(400, [
                    "message" => "color_id dan name wajib diisi dengan benar"
                ]);
            }

            $name = trim($data["name"]);

            if (mb_strlen($name) > 100) {
                response(400, [
                    "message" => "name maksimal 100 karakter"
                ]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO checkpoints (color_id, name)
                 VALUES (?, ?)"
            );

            $stmt->execute([
                (int) $data["color_id"],
                $name
            ]);

            response(201, [
                "message" => "Checkpoint berhasil ditambahkan",
                "id" => (int) $pdo->lastInsertId()
            ]);

        // PUT: Ubah checkpoint
        case "PUT":
            if (!$id || $id <= 0) {
                response(400, ["message" => "Parameter id wajib diisi"]);
            }

            if (
                !isset($data["color_id"], $data["name"]) ||
                !is_numeric($data["color_id"]) ||
                (int) $data["color_id"] <= 0 ||
                !is_string($data["name"]) ||
                trim($data["name"]) === ""
            ) {
                response(400, [
                    "message" => "color_id dan name wajib diisi dengan benar"
                ]);
            }

            $name = trim($data["name"]);

            if (mb_strlen($name) > 100) {
                response(400, [
                    "message" => "name maksimal 100 karakter"
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE checkpoints
                 SET color_id = ?, name = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                (int) $data["color_id"],
                $name,
                $id
            ]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    "SELECT id FROM checkpoints WHERE id = ?"
                );
                $check->execute([$id]);

                if (!$check->fetch()) {
                    response(404, ["message" => "Checkpoint tidak ditemukan"]);
                }
            }

            response(200, ["message" => "Checkpoint berhasil diperbarui"]);

        // DELETE: Hapus checkpoint
        case "DELETE":
            if (!$id || $id <= 0) {
                response(400, ["message" => "Parameter id wajib diisi"]);
            }

            $stmt = $pdo->prepare(
                "DELETE FROM checkpoints WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                response(404, ["message" => "Checkpoint tidak ditemukan"]);
            }

            response(200, ["message" => "Checkpoint berhasil dihapus"]);

        default:
            header("Allow: GET, POST, PUT, DELETE");
            response(405, ["message" => "Method tidak diizinkan"]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());

    response(500, [
        "message" => "Terjadi kesalahan database"
    ]);
}
