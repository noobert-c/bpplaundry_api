
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../db.php";

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getInput() {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, ["message" => "JSON tidak valid"]);
    }

    return $data;
}

$method = $_SERVER["REQUEST_METHOD"];
$id = isset($_GET["id"]) ? filter_var(
    $_GET["id"],
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
) : null;

try {
    switch ($method) {

        // READ: GET /users.php atau /users.php?id=1
        case "GET":
            if (isset($_GET["id"])) {
                if ($id === false || $id === null) {
                    respond(400, ["message" => "ID tidak valid"]);
                }

                $stmt = $pdo->prepare(
                    "SELECT id, full_name, email, phone, created_at
                     FROM users WHERE id = ?"
                );
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if (!$user) {
                    respond(404, ["message" => "Pengguna tidak ditemukan"]);
                }

                respond(200, ["data" => $user]);
            }

            $stmt = $pdo->query(
                "SELECT id, full_name, email, phone, created_at
                 FROM users ORDER BY id DESC"
            );

            respond(200, ["data" => $stmt->fetchAll()]);

        // CREATE: POST /users.php
        case "POST":
            $data = getInput();

            $name = trim($data["full_name"] ?? "");
            $email = trim($data["email"] ?? "");
            $phone = trim($data["phone"] ?? "");
            $password = $data["password"] ?? "";

            if (
                $name === "" ||
                strlen($name) > 100 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150 ||
                strlen($phone) > 20 ||
                !is_string($password) ||
                strlen($password) < 8
            ) {
                respond(422, ["message" => "Data tidak valid"]);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO users
                 (full_name, email, phone, password)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $email,
                $phone !== "" ? $phone : null,
                $password
            ]);

            respond(201, [
                "message" => "Pengguna berhasil ditambahkan",
                "id" => (int) $pdo->lastInsertId()
            ]);

        // UPDATE: PUT /users.php?id=1
        case "PUT":
            if ($id === false || $id === null) {
                respond(400, ["message" => "ID wajib diisi"]);
            }

            $data = getInput();

            $name = trim($data["full_name"] ?? "");
            $email = trim($data["email"] ?? "");
            $phone = trim($data["phone"] ?? "");
            $password = $data["password"] ?? "";

            if (
                $name === "" ||
                strlen($name) > 100 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150 ||
                strlen($phone) > 20 ||
                !is_string($password) ||
                ($password !== "" && strlen($password) < 8)
            ) {
                respond(422, ["message" => "Data tidak valid"]);
            }

            if ($password !== "") {
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, phone = ?, password = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone !== "" ? $phone : null,
                    $password,
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, phone = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone !== "" ? $phone : null,
                    $id
                ]);
            }

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $check->execute([$id]);

                if (!$check->fetch()) {
                    respond(404, ["message" => "Pengguna tidak ditemukan"]);
                }
            }

            respond(200, ["message" => "Pengguna berhasil diperbarui"]);

        // DELETE: DELETE /users.php?id=1
        case "DELETE":
            if ($id === false || $id === null) {
                respond(400, ["message" => "ID wajib diisi"]);
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                respond(404, ["message" => "Pengguna tidak ditemukan"]);
            }

            respond(200, ["message" => "Pengguna berhasil dihapus"]);

        default:
            header("Allow: GET, POST, PUT, DELETE");
            respond(405, ["message" => "Method tidak didukung"]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        respond(409, ["message" => "Email sudah digunakan"]);
    }

    respond(500, ["message" => "Terjadi kesalahan database"]);
}
