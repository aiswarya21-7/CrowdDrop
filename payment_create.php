<?php
require_once 'includes/config.php';
requireLogin('customer');

$courier_id = (int)($_GET['courier_id'] ?? 0);
$uid        = (int)$_SESSION['user_id'];

if (!$courier_id) {
    header("Location: /courier/customer/dashboard.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM courier_requests
    WHERE id=? AND customer_id=? AND status IN ('picked_up','awaiting_payment') AND payment_status IN ('unpaid','pending')
");
$stmt->bind_param("ii", $courier_id, $uid);
$stmt->execute();
$courier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$courier) {
    $_SESSION['pay_error'] = 'Payment is not applicable for this request, or it has already been paid.';
    header("Location: /courier/customer/track.php?id=$courier_id");
    exit;
}

if ($courier['razorpay_order_id']) {
    header("Location: /courier/payment_page.php?courier_id=$courier_id");
    exit;
}

$amount_paise = (int)round($courier['reward_amount'] * 100); 

$payload = json_encode([
    'amount'          => $amount_paise,
    'currency'        => 'INR',
    'receipt'         => 'courier_' . $courier_id,
    'payment_capture' => 1,
    'notes'           => [
        'courier_id'   => $courier_id,
        'customer_id'  => $uid,
    ],
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Razorpay order creation failed: HTTP $http_code — $response");
    $_SESSION['pay_error'] = "Payment gateway error. Please try again. (Code: $http_code)";
    header("Location: /courier/customer/track.php?id=$courier_id");
    exit;
}

$order = json_decode($response, true);

$upd = $conn->prepare("UPDATE courier_requests SET razorpay_order_id=?, payment_status='pending', status='awaiting_payment' WHERE id=?");
$upd->bind_param("si", $order['id'], $courier_id);
$upd->execute();
$upd->close();

$note = "Payment initiated. Razorpay Order: " . $order['id'];
$trk = $conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'Payment Initiated',?,?)");
$trk->bind_param("isi", $courier_id, $note, $uid);
$trk->execute(); $trk->close();

header("Location: /courier/payment_page.php?courier_id=$courier_id");
exit;
