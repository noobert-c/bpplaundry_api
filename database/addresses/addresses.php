
<?php
require_once "../db.php";

header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER["REQUEST_METHOD"];
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = [];
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$user_id = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;
$customer_id = isset($_GET["customer_id"])
    ? (int) $_GET["customer_id"]
    : 0;

function response($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateCoordinates($latitude, $longitude)
{
    if ($latitude !== null && $latitude !== "") {
        if (
            !is_numeric($latitude) ||
            $latitude < -90 ||
            $latitude > 90
        ) {
            response(400, [
                "message" => "Latitude harus antara -90 dan 90"
            ]);
        }
    }

    if ($longitude !== null && $longitude !== "") {
        if (
            !is_numeric($longitude) ||
            $longitude < -180 ||
            $longitude > 180
        ) {
            response(400, [
                "message" => "Longitude harus antara -180 dan 180"
            ]);
        }
    }

    return [
        ($latitude === "" || $latitude === null)
            ? null : (float) $latitude,
        ($longitude === "" || $longitude === null)
            ? null : (float) $longitude
    ];
}

try {
    switch ($method) {

        // =========================
        // GET: READ
        // =========================
        case "GET":
            if ($user_id <= 0) {
                response(400, [
                    "message" => "user_id wajib diisi"
                ]);
            }

            $sql = "SELECT
                        a.id,
                        a.customer_id,
                        a.address,
                        a.latitude,
                        a.longitude
                    FROM addresses a
                    INNER JOIN customers c
                        ON c.id = a.customer_id
                    WHERE c.user_id = :user_id";

            $params = [
                ":user_id" => $user_id
            ];

            if ($customer_id > 0) {
                $sql .= " AND a.customer_id = :customer_id";
                $params[":customer_id"] = $customer_id;
            }

            if ($id > 0) {
                $sql .= " AND a.id = :id";
                $params[":id"] = $id;
            }

            $sql .= " ORDER BY a.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            if ($id > 0 && empty($data)) {
                response(404, [
                    "message" => "Alamat tidak ditemukan"
                ]);
            }

            response(200, [
                "message" => "Data alamat berhasil diambil",
                "data" => $id > 0 ? $data[0] : $data
            ]);

        // =========================
        // POST: CREATE
        // =========================
        case "POST":
            $user_id = (int) ($input["user_id"] ?? 0);
            $customer_id = (int) ($input["customer_id"] ?? 0);
            $address = trim((string) ($input["address"] ?? ""));
            $latitude = $input["latitude"] ?? null;
            $longitude = $input["longitude"] ?? null;

            if ($user_id <= 0 || $customer_id <= 0) {
                response(400, [
                    "message" => "user_id dan customer_id wajib diisi"
                ]);
            }

            $address = $address === "" ? null : $address;

            [$latitude, $longitude] = validateCoordinates(
                $latitude,
                $longitude
            );

            // Pastikan customer milik user yang dimaksud.
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM customers
                 WHERE id = :customer_id
                   AND user_id = :user_id"
            );

            $stmt->execute([
                ":customer_id" => $customer_id,
                ":user_id" => $user_id
            ]);

            if (!$stmt->fetch()) {
                response(404, [
                    "message" => "Customer tidak ditemukan"
                ]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO addresses
                    (customer_id, address, latitude, longitude)
                 VALUES
                    (:customer_id, :address, :latitude, :longitude)"
            );

            $stmt->execute([
                ":customer_id" => $customer_id,
                ":address" => $address,
                ":latitude" => $latitude,
                ":longitude" => $longitude
            ]);

            response(201, [
                "message" => "Alamat berhasil ditambahkan",
                "data" => [
                    "id" => (int) $pdo->lastInsertId(),
                    "customer_id" => $customer_id,
                    "address" => $address,
                    "latitude" => $latitude,
                    "longitude" => $longitude
                ]
            ]);

        // =========================
        // PUT: UPDATE
        // =========================
        case "PUT":
            if ($id <= 0 || $user_id <= 0) {
                response(400, [
                    "message" => "id dan user_id wajib diisi melalui URL"
                ]);
            }

            $address = trim((string) ($input["address"] ?? ""));
            $address = $address === "" ? null : $address;

            $latitude = $input["latitude"] ?? null;
            $longitude = $input["longitude"] ?? null;

            // Jika customer_id disertakan, pastikan alamat memang
            // milik customer tersebut.
            $target_customer_id = isset($input["customer_id"])
                ? (int) $input["customer_id"]
                : $customer_id;

            [$latitude, $longitude] = validateCoordinates(
                $latitude,
                $longitude
            );

            $sql = "SELECT a.id
                    FROM addresses a
                    INNER JOIN customers c
                        ON c.id = a.customer_id
                    WHERE a.id = :id
                      AND c.user_id = :user_id";

            $params = [
                ":id" => $id,
                ":user_id" => $user_id
            ];

            if ($target_customer_id > 0) {
                $sql .= " AND a.customer_id = :customer_id";
                $params[":customer_id"] = $target_customer_id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                response(404, [
                    "message" => "Alamat tidak ditemukan"
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE addresses
                 SET address = :address,
                     latitude = :latitude,
                     longitude = :longitude
                 WHERE id = :id"
            );

            $stmt->execute([
                ":address" => $address,
                ":latitude" => $latitude,
                ":longitude" => $longitude,
                ":id" => $id
            ]);

            response(200, [
                "message" => "Alamat berhasil diperbarui"
            ]);

        // =========================
        // DELETE
        // =========================
        case "DELETE":
            if ($id <= 0 || $user_id <= 0) {
                response(400, [
                    "message" => "id dan user_id wajib diisi melalui URL"
                ]);
            }

            $stmt = $pdo->prepare(
                "DELETE a
                 FROM addresses a
                 INNER JOIN customers c
                    ON c.id = a.customer_id
                 WHERE a.id = :id
                   AND c.user_id = :user_id"
            );

            $stmt->execute([
                ":id" => $id,
                ":user_id" => $user_id
            ]);

            if ($stmt->rowCount() === 0) {
                response(404, [
                    "message" => "Alamat tidak ditemukan"
                ]);
            }

            response(200, [
                "message" => "Alamat berhasil dihapus"
            ]);

        default:
            header("Allow: GET, POST, PUT, DELETE");

            response(405, [
                "message" => "Method tidak didukung"
            ]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    response(500, [
        "message" => "Terjadi kesalahan database"
    ]);
}
