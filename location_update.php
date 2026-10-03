<?php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$courier_id = (int)($_REQUEST['courier_id'] ?? 0);
if (!$courier_id) {
    echo json_encode(['error' => 'Missing courier_id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['role'] !== 'partner') {
        echo json_encode(['error' => 'Only travelers can push location']);
        exit;
    }

    $partner_id = (int)$_SESSION['user_id'];
    $lat        = floatval($_POST['lat']      ?? 0);
    $lng        = floatval($_POST['lng']      ?? 0);
    $accuracy   = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : null;

    if (!$lat || !$lng) {
        echo json_encode(['error' => 'Invalid coordinates']);
        exit;
    }

    $chk = $conn->prepare("
        SELECT id FROM courier_requests
        WHERE id=? AND partner_id=?
        AND status IN ('accepted','picked_up','awaiting_payment','payment_done','in_transit')
    ");
    $chk->bind_param("ii", $courier_id, $partner_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        echo json_encode(['error' => 'Courier not found or not active']);
        $chk->close(); exit;
    }
    $chk->close();

    $stmt = $conn->prepare("
        INSERT INTO traveler_locations (courier_id, partner_id, lat, lng, accuracy, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng),
            accuracy=VALUES(accuracy), updated_at=NOW()
    ");
    $stmt->bind_param("iiddd", $courier_id, $partner_id, $lat, $lng, $accuracy);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

$role = $_SESSION['role'];
$uid  = (int)$_SESSION['user_id'];

if ($role === 'customer') {
    $chk = $conn->prepare("SELECT id FROM courier_requests WHERE id=? AND customer_id=?");
    $chk->bind_param("ii", $courier_id, $uid);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        echo json_encode(['found' => false, 'error' => 'Access denied']); exit;
    }
    $chk->close();
} elseif ($role !== 'admin') {
    echo json_encode(['found' => false, 'error' => 'Access denied']); exit;
}

$stmt = $conn->prepare("
    SELECT lat, lng, accuracy, updated_at
    FROM traveler_locations
    WHERE courier_id = ?
");
$stmt->bind_param("i", $courier_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['found' => false]);
    exit;
}

$stale = (time() - strtotime($row['updated_at'])) > 30;

echo json_encode([
    'found'    => true,
    'lat'      => (float)$row['lat'],
    'lng'      => (float)$row['lng'],
    'accuracy' => $row['accuracy'] ? (float)$row['accuracy'] : null,
    'updated'  => $row['updated_at'],
    'stale'    => $stale,
]);
