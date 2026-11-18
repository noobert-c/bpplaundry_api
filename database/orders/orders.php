
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$input = json_decode(file_get_contents("php://input"), true);

if ($method === "OPTIONS") {
    http_response_code(200);
    exit;
}

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validId($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);

    return $id !== false && $id !== null && $id > 0
        ? $id
        : false;
}

function validateOrderInput($input, $requireId = false)
{
    if (!is_array($input)) {
        respond(400, [
            "success" => false,
            "message" => "Body JSON tidak valid"
        ]);
    }

    $id = null;

    if ($requireId) {
        $id = validId($input["id"] ?? null);

        if ($id === false) {
            respond(400, [
                "success" => false,
                "message" => "ID pesanan tidak valid"
            ]);
        }
    }

    $orderCode = trim($input["order_code"] ?? "");

    $customerId = validId($input["customer_id"] ?? null);
    $createdBy = validId($input["created_by"] ?? null);

    $description = $input["description"] ?? null;
    $receivedAt = $input["received_at"] ?? null;
    $readyAt = $input["ready_at"] ?? null;
    $completedAt = $input["completed_at"] ?? null;

    $totalAmount = $input["total_amount"] ?? 0;
    $paymentStatus = $input["payment_status"] ?? "unpaid";
    $needsDelivery = $input["needs_delivery"] ?? false;

    if (
        $orderCode === "" ||
        strlen($orderCode) > 50 ||
        $customerId === false ||
        $createdBy === false
    ) {
        respond(400, [
            "success" => false,
            "message" => "Kode pesanan, customer_id, dan created_by wajib valid"
        ]);
    }

    if (
        !is_numeric($totalAmount) ||
        !is_finite((float) $totalAmount) ||
        (float) $totalAmount < 0 ||
        (float) $totalAmount > 9999999999.99
    ) {
        respond(400, [
            "success" => false,
            "message" => "total_amount tidak valid"
        ]);
    }

    $allowedStatuses = [
        "unpaid",
        "partial",
        "paid",
        "refunded"
    ];

    if (
        !is_string($paymentStatus) ||
        !in_array($paymentStatus, $allowedStatuses, true)
    ) {
        respond(400, [
            "success" => false,
            "message" => "payment_status tidak valid",
            "allowed" => $allowedStatuses
        ]);
    }

    // Terima boolean JSON atau angka/string 0 dan 1.
    if (is_bool($needsDelivery)) {
        $needsDelivery = $needsDelivery ? 1 : 0;
    } elseif (
        $needsDelivery === 0 ||
        $needsDelivery === 1 ||
        $needsDelivery === "0" ||
        $needsDelivery === "1"
    ) {
        $needsDelivery = (int) $needsDelivery;
    } else {
        respond(400, [
            "success" => false,
            "message" => "needs_delivery harus berupa boolean atau 0/1"
        ]);
    }

    if ($description !== null && !is_string($description)) {
        respond(400, [
            "success" => false,
            "message" => "description harus berupa teks atau null"
        ]);
    }

    foreach (
        [
            "received_at" => $receivedAt,
            "ready_at" => $readyAt,
            "completed_at" => $completedAt
        ] as $field => $date
    ) {
        if ($date !== null && !validDateTime($date)) {
            respond(400, [
                "success" => false,
                "message" => "$field harus berformat YYYY-MM-DD HH:MM:SS"
            ]);
        }
    }

    return [
        "id" => $id,
        "order_code" => $orderCode,
        "customer_id" => $customerId,
        "created_by" => $createdBy,
        "description" => $description,
        "received_at" => $receivedAt,
        "ready_at" => $readyAt,
        "completed_at" => $completedAt,
        "total_amount" => number_format((float) $totalAmount, 2, ".", ""),
        "payment_status" => $paymentStatus,
        "needs_delivery" => $needsDelivery
    ];
}

function validDateTime($value)
{
    if (!is_string($value)) {
        return false;
    }

    $date = DateTime::createFromFormat("!Y-m-d H:i:s", $value);

    return $date !== false &&
        $date->format("Y-m-d H:i:s") === $value;
}

function checkOrderRelations($pdo, $customerId, $createdBy)
{
    $stmt = $pdo->prepare(
        "SELECT id FROM customers WHERE id = ?"
    );
    $stmt->execute([$customerId]);

    if (!$stmt->fetch()) {
        respond(404, [
            "success" => false,
            "message" => "Customer tidak ditemukan"
        ]);
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE id = ?"
    );
    $stmt->execute([$createdBy]);

    if (!$stmt->fetch()) {
        respond(404, [
            "success" => false,
            "message" => "User pembuat pesanan tidak ditemukan"
        ]);
    }
}

try {
    switch ($method) {

        // ==========================================
        // GET: AMBIL PESANAN
        // ==========================================
        case "GET":

            $sql = "
                SELECT
                    o.id,
                    o.order_code,
                    o.customer_id,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    o.created_by,
                    o.description,
                    o.received_at,
                    o.ready_at,
                    o.completed_at,
                    o.total_amount,
                    o.payment_status,
                    o.needs_delivery
                FROM orders o
                INNER JOIN customers c ON c.id = o.customer_id
                INNER JOIN users u ON u.id = o.created_by
            ";

            $conditions = [];
            $params = [];

            if (isset($_GET["id"])) {
                $id = validId($_GET["id"]);

                if ($id === false) {
                    respond(400, [
                        "success" => false,
                        "message" => "ID pesanan tidak valid"
                    ]);
                }

                $conditions[] = "o.id = ?";
                $params[] = $id;
            }

            if (isset($_GET["order_code"])) {
                $conditions[] = "o.order_code = ?";
                $params[] = trim($_GET["order_code"]);
            }

            if (isset($_GET["customer_id"])) {
                $customerId = validId($_GET["customer_id"]);

                if ($customerId === false) {
                    respond(400, [
                        "success" => false,
                        "message" => "customer_id tidak valid"
                    ]);
                }

                $conditions[] = "o.customer_id = ?";
                $params[] = $customerId;
            }

            if (isset($_GET["created_by"])) {
                $createdBy = validId($_GET["created_by"]);

                if ($createdBy === false) {
                    respond(400, [
                        "success" => false,
                        "message" => "created_by tidak valid"
                    ]);
                }

                $conditions[] = "o.created_by = ?";
                $params[] = $createdBy;
            }

            if (isset($_GET["payment_status"])) {
                $conditions[] = "o.payment_status = ?";
                $params[] = $_GET["payment_status"];
            }

            if (isset($_GET["needs_delivery"])) {
                if (!in_array($_GET["needs_delivery"], ["0", "1"], true)) {
                    respond(400, [
                        "success" => false,
                        "message" => "needs_delivery harus 0 atau 1"
                    ]);
                }

                $conditions[] = "o.needs_delivery = ?";
                $params[] = (int) $_GET["needs_delivery"];
            }

            if ($conditions) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY o.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $data = $stmt->fetchAll();

            if (isset($_GET["id"]) || isset($_GET["order_code"])) {
                if (!$data) {
                    respond(404, [
                        "success" => false,
                        "message" => "Pesanan tidak ditemukan"
                    ]);
                }

                respond(200, [
                    "success" => true,
                    "data" => $data[0]
                ]);
            }

            respond(200, [
                "success" => true,
                "data" => $data
            ]);

        // ==========================================
        // POST: TAMBAH PESANAN
        // ==========================================
        case "POST":

            $order = validateOrderInput($input);

            checkOrderRelations(
                $pdo,
                $order["customer_id"],
                $order["created_by"]
            );

            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_code,
                    customer_id,
                    created_by,
                    description,
                    received_at,
                    ready_at,
                    completed_at,
                    total_amount,
                    payment_status,
                    needs_delivery
                ) VALUES (?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP), ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $order["order_code"],
                $order["customer_id"],
                $order["created_by"],
                $order["description"],
                $order["received_at"],
                $order["ready_at"],
                $order["completed_at"],
                $order["total_amount"],
                $order["payment_status"],
                $order["needs_delivery"]
            ]);

            $id = (int) $pdo->lastInsertId();

            respond(201, [
                "success" => true,
                "message" => "Pesanan berhasil dibuat",
                "data" => [
                    "id" => $id,
                    "order_code" => $order["order_code"]
                ]
            ]);

        // ==========================================
        // PUT: PERBARUI PESANAN
        // ==========================================
        case "PUT":

            $order = validateOrderInput($input, true);

            checkOrderRelations(
                $pdo,
                $order["customer_id"],
                $order["created_by"]
            );

            $check = $pdo->prepare(
                "SELECT id FROM orders WHERE id = ?"
            );
            $check->execute([$order["id"]]);

            if (!$check->fetch()) {
                respond(404, [
                    "success" => false,
                    "message" => "Pesanan tidak ditemukan"
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE orders SET
                    order_code = ?,
                    customer_id = ?,
                    created_by = ?,
                    description = ?,
                    received_at = ?,
                    ready_at = ?,
                    completed_at = ?,
                    total_amount = ?,
                    payment_status = ?,
                    needs_delivery = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $order["order_code"],
                $order["customer_id"],
                $order["created_by"],
                $order["description"],
                $order["received_at"] ?? date("Y-m-d H:i:s"),
                $order["ready_at"],
                $order["completed_at"],
                $order["total_amount"],
                $order["payment_status"],
                $order["needs_delivery"],
                $order["id"]
            ]);

            respond(200, [
                "success" => true,
                "message" => "Pesanan berhasil diperbarui",
                "data" => [
                    "id" => (int) $order["id"],
                    "order_code" => $order["order_code"]
                ]
            ]);

        // ==========================================
        // DELETE: HAPUS PESANAN
        // ==========================================
        case "DELETE":

            $id = validId(
                $_GET["id"] ?? ($input["id"] ?? null)
            );

            if ($id === false) {
                respond(400, [
                    "success" => false,
                    "message" => "ID pesanan tidak valid"
                ]);
            }

            $stmt = $pdo->prepare(
                "SELECT id, order_code FROM orders WHERE id = ?"
            );
            $stmt->execute([$id]);

            $order = $stmt->fetch();

            if (!$order) {
                respond(404, [
                    "success" => false,
                    "message" => "Pesanan tidak ditemukan"
                ]);
            }

            $stmt = $pdo->prepare(
                "DELETE FROM orders WHERE id = ?"
            );
            $stmt->execute([$id]);

            respond(200, [
                "success" => true,
                "message" => "Pesanan berhasil dihapus",
                "data" => $order
            ]);

        default:

            header("Allow: GET, POST, PUT, DELETE, OPTIONS");

            respond(405, [
                "success" => false,
                "message" => "Method tidak didukung"
            ]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        respond(409, [
            "success" => false,
            "message" => "Data duplikat atau melanggar relasi database"
        ]);
    }

    respond(500, [
        "success" => false,
        "message" => "Terjadi kesalahan database"
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Terjadi kesalahan server"
    ]);
}
