
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once "../db.php";

$method = $_SERVER["REQUEST_METHOD"];
$input = json_decode(file_get_contents("php://input"), true);

if ($method === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {
    switch ($method) {

        // ==========================================
        // GET: AMBIL DATA RELASI
        // ==========================================
        case "GET":

            $conditions = [];
            $params = [];

            if (isset($_GET["id"])) {
                $conditions[] = "pc.id = ?";
                $params[] = $_GET["id"];
            }

            if (isset($_GET["package_id"])) {
                $conditions[] = "pc.package_id = ?";
                $params[] = $_GET["package_id"];
            }

            if (isset($_GET["checkpoint_id"])) {
                $conditions[] = "pc.checkpoint_id = ?";
                $params[] = $_GET["checkpoint_id"];
            }

            $sql = "
                SELECT
                    pc.id,
                    pc.package_id,
                    p.name AS package_name,
                    pc.checkpoint_id,
                    c.name AS checkpoint_name
                FROM package_checkpoints pc
                INNER JOIN packages p
                    ON p.id = pc.package_id
                INNER JOIN checkpoints c
                    ON c.id = pc.checkpoint_id
            ";

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY pc.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                "success" => true,
                "data" => $stmt->fetchAll()
            ]);

            break;

        // ==========================================
        // POST: TAMBAH RELASI PAKET DAN CHECKPOINT
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

            $packageId = filter_var(
                $input["package_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            $checkpointId = filter_var(
                $input["checkpoint_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $packageId === false || $packageId === null ||
                $packageId < 1 ||
                $checkpointId === false || $checkpointId === null ||
                $checkpointId < 1
            ) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "package_id dan checkpoint_id wajib berupa ID positif"
                ]);
                exit;
            }

            // Pastikan paket tersedia
            $checkPackage = $pdo->prepare(
                "SELECT id FROM packages WHERE id = ?"
            );
            $checkPackage->execute([$packageId]);

            if (!$checkPackage->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Paket tidak ditemukan"
                ]);
                exit;
            }

            // Pastikan checkpoint tersedia
            $checkCheckpoint = $pdo->prepare(
                "SELECT id FROM checkpoints WHERE id = ?"
            );
            $checkCheckpoint->execute([$checkpointId]);

            if (!$checkCheckpoint->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Checkpoint tidak ditemukan"
                ]);
                exit;
            }

            // Cek relasi duplikat
            $checkRelation = $pdo->prepare(
                "SELECT id
                 FROM package_checkpoints
                 WHERE package_id = ? AND checkpoint_id = ?"
            );
            $checkRelation->execute([
                $packageId,
                $checkpointId
            ]);

            if ($checkRelation->fetch()) {
                http_response_code(409);
                echo json_encode([
                    "success" => false,
                    "message" => "Relasi paket dan checkpoint sudah ada"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO package_checkpoints
                    (package_id, checkpoint_id)
                 VALUES (?, ?)"
            );

            $stmt->execute([
                $packageId,
                $checkpointId
            ]);

            http_response_code(201);

            echo json_encode([
                "success" => true,
                "message" => "Checkpoint berhasil ditambahkan ke paket",
                "data" => [
                    "id" => (int) $pdo->lastInsertId(),
                    "package_id" => (int) $packageId,
                    "checkpoint_id" => (int) $checkpointId
                ]
            ]);

            break;

        // ==========================================
        // PUT: UBAH RELASI
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

            $packageId = filter_var(
                $input["package_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            $checkpointId = filter_var(
                $input["checkpoint_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $id === false || $id === null || $id < 1 ||
                $packageId === false || $packageId === null || $packageId < 1 ||
                $checkpointId === false || $checkpointId === null || $checkpointId < 1
            ) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "ID relasi, paket, atau checkpoint tidak valid"
                ]);
                exit;
            }

            $checkRelationId = $pdo->prepare(
                "SELECT id FROM package_checkpoints WHERE id = ?"
            );
            $checkRelationId->execute([$id]);

            if (!$checkRelationId->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Relasi tidak ditemukan"
                ]);
                exit;
            }

            $checkPackage = $pdo->prepare(
                "SELECT id FROM packages WHERE id = ?"
            );
            $checkPackage->execute([$packageId]);

            if (!$checkPackage->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Paket tidak ditemukan"
                ]);
                exit;
            }

            $checkCheckpoint = $pdo->prepare(
                "SELECT id FROM checkpoints WHERE id = ?"
            );
            $checkCheckpoint->execute([$checkpointId]);

            if (!$checkCheckpoint->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Checkpoint tidak ditemukan"
                ]);
                exit;
            }

            $checkDuplicate = $pdo->prepare(
                "SELECT id
                 FROM package_checkpoints
                 WHERE package_id = ?
                   AND checkpoint_id = ?
                   AND id != ?"
            );

            $checkDuplicate->execute([
                $packageId,
                $checkpointId,
                $id
            ]);

            if ($checkDuplicate->fetch()) {
                http_response_code(409);
                echo json_encode([
                    "success" => false,
                    "message" => "Relasi paket dan checkpoint tersebut sudah ada"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "UPDATE package_checkpoints
                 SET package_id = ?, checkpoint_id = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $packageId,
                $checkpointId,
                $id
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Relasi berhasil diperbarui",
                "data" => [
                    "id" => (int) $id,
                    "package_id" => (int) $packageId,
                    "checkpoint_id" => (int) $checkpointId
                ]
            ]);

            break;

        // ==========================================
        // DELETE: HAPUS RELASI
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
                    "message" => "ID relasi tidak valid"
                ]);
                exit;
            }

            $check = $pdo->prepare(
                "SELECT id FROM package_checkpoints WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Relasi tidak ditemukan"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "DELETE FROM package_checkpoints WHERE id = ?"
            );
            $stmt->execute([$id]);

            echo json_encode([
                "success" => true,
                "message" => "Relasi berhasil dihapus"
            ]);

            break;

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

    // Duplicate key juga bisa dicegah oleh UNIQUE constraint database.
    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Data melanggar constraint database atau relasi sudah ada"
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan database"
    ]);
}
