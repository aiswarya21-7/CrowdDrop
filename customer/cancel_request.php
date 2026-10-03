<?php
require_once '../includes/config.php';
requireLogin('customer');

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare(
        "UPDATE courier_requests SET status='cancelled'
         WHERE id=? AND customer_id=? AND status='pending'"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $note = 'Request cancelled by sender.';
        $trk  = $conn->prepare(
            "INSERT INTO tracking_updates (courier_id, status, note, updated_by) VALUES (?, 'Cancelled', ?, ?)"
        );
        $trk->bind_param("isi", $id, $note, $uid);
        $trk->execute();
        $trk->close();
    }
    $stmt->close();
}

header("Location: /courier/customer/my_requests.php?cancelled=1");
exit;
