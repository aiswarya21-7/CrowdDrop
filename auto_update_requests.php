<?php
if (!isset($conn)) {
    require_once __DIR__ . '/includes/config.php';
}

$conn->query(
    "UPDATE courier_requests
     SET created_at = DATE_ADD(created_at, INTERVAL 1 DAY)
     WHERE status = 'pending'
       AND DATE(created_at) < CURDATE()"
);
