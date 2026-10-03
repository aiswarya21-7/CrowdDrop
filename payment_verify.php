<?php

require_once 'includes/config.php';
requireLogin('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /courier/customer/dashboard.php");
    exit;
}

$uid        = (int)$_SESSION['user_id'];
$courier_id = (int)($_POST['courier_id'] ?? 0);
$order_id   = $_POST['razorpay_order_id']   ?? '';
$payment_id = $_POST['razorpay_payment_id'] ?? '';
$signature  = $_POST['razorpay_signature']  ?? '';

$expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);

if (!hash_equals($expected, $signature)) {
    $_SESSION['pay_error'] = 'Payment verification failed. Please contact support.';
    error_log("Razorpay signature mismatch for courier_id=$courier_id order=$order_id");
    header("Location: /courier/customer/track.php?id=$courier_id");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM courier_requests WHERE id=? AND customer_id=? AND razorpay_order_id=?");
$stmt->bind_param("iis", $courier_id, $uid, $order_id);
$stmt->execute();
$courier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$courier) {
    $_SESSION['pay_error'] = 'Invalid payment session. Please try again.';
    header("Location: /courier/customer/track.php?id=$courier_id");
    exit;
}

$upd = $conn->prepare("
    UPDATE courier_requests
    SET razorpay_payment_id=?, payment_status='paid', status='payment_done'
    WHERE id=?
");
$upd->bind_param("si", $payment_id, $courier_id);
$upd->execute();
$upd->close();

$note = "Payment of ₹" . $courier['reward_amount'] . " received. Razorpay Payment ID: $payment_id";
$trk  = $conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'Payment Received',?,?)");
$trk->bind_param("isi", $courier_id, $note, $uid);
$trk->execute(); $trk->close();

$_SESSION['pay_success'] = "Payment of ₹" . number_format($courier['reward_amount'], 2) . " successful! The traveler will now proceed with delivery.";
header("Location: /courier/customer/track.php?id=$courier_id&paid=1");
exit;
