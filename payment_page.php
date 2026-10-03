<?php
require_once 'includes/config.php';
requireLogin('customer');
$pageTitle = 'Pay for Delivery — CrowdDrop';

$courier_id = (int)($_GET['courier_id'] ?? 0);
$uid        = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT cr.*, u.name AS partner_name
    FROM courier_requests cr
    JOIN users u ON cr.partner_id = u.id
    WHERE cr.id=? AND cr.customer_id=?
");
$stmt->bind_param("ii", $courier_id, $uid);
$stmt->execute();
$courier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$courier || !$courier['razorpay_order_id']) {
    header("Location: /courier/customer/track.php?id=$courier_id");
    exit;
}

if ($courier['payment_status'] === 'paid' || $courier['payment_status'] === 'released') {
    header("Location: /courier/customer/track.php?id=$courier_id&paid=1");
    exit;
}

$sender_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id=?");
$sender_stmt->bind_param("i", $uid);
$sender_stmt->execute();
$sender = $sender_stmt->get_result()->fetch_assoc();
$sender_stmt->close();

$amount_paise = (int)round($courier['reward_amount'] * 100);

include 'includes/header.php';
?>

<div class="max-w-md mx-auto">
    <div class="mb-5">
        <a href="/courier/customer/track.php?id=<?= $courier_id ?>" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back to Tracking</a>
        <h1 class="text-xl font-bold text-slate-800 mt-2">Pay Delivery Reward</h1>
        <p class="text-xs text-slate-400 mt-0.5">Your parcel has been picked up. Complete payment to proceed.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-4">
        <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
            <div>
                <div class="font-semibold text-slate-700"><?= htmlspecialchars($courier['parcel_name']) ?></div>
                <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($courier['from_location']) ?> → <?= htmlspecialchars($courier['to_location']) ?></div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-slate-800">₹<?= number_format($courier['reward_amount'], 2) ?></div>
                <div class="text-xs text-slate-400">delivery reward</div>
            </div>
        </div>

        <div class="space-y-2 text-xs">
            <div class="flex justify-between text-slate-500">
                <span>Traveler</span>
                <span class="font-medium text-slate-700"><?= htmlspecialchars($courier['partner_name']) ?></span>
            </div>
            <div class="flex justify-between text-slate-500">
                <span>Order ID</span>
                <span class="font-mono text-slate-500"><?= htmlspecialchars($courier['razorpay_order_id']) ?></span>
            </div>
            <div class="flex justify-between text-slate-500">
                <span>Payment held until delivery</span>
                <span class="text-green-600 font-medium">Escrow protected</span>
            </div>
        </div>

        <div class="mt-4 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 text-xs text-amber-700 flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Your payment is held securely. The traveler receives the money only after successful delivery.
        </div>
    </div>

    <button id="pay-btn"
        class="w-full bg-slate-800 hover:bg-slate-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        Pay ₹<?= number_format($courier['reward_amount'], 2) ?> via Razorpay
    </button>

    <p class="text-center text-xs text-slate-400 mt-3">Secured by Razorpay · UPI / Cards / Net Banking / Wallets</p>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('pay-btn').addEventListener('click', function() {
    const options = {
        key:         '<?= RAZORPAY_KEY_ID ?>',
        amount:      <?= $amount_paise ?>,
        currency:    'INR',
        name:        'CrowdDrop',
        description: 'Delivery reward — <?= addslashes($courier['parcel_name']) ?>',
        order_id:    '<?= htmlspecialchars($courier['razorpay_order_id']) ?>',
        prefill: {
            name:    '<?= addslashes($sender['name']) ?>',
            email:   '<?= addslashes($sender['email']) ?>',
            contact: '<?= addslashes($sender['phone']) ?>',
        },
        theme: { color: '#1e293b' },
        handler: function(response) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/courier/payment_verify.php';
            const fields = {
                razorpay_order_id:   response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature:  response.razorpay_signature,
                courier_id:          '<?= $courier_id ?>',
            };
            for (const [k,v] of Object.entries(fields)) {
                const i = document.createElement('input');
                i.type='hidden'; i.name=k; i.value=v;
                form.appendChild(i);
            }
            document.body.appendChild(form);
            form.submit();
        },
        modal: {
            ondismiss: function() {
                document.getElementById('pay-btn').textContent = 'Pay ₹<?= number_format($courier['reward_amount'],2) ?> via Razorpay';
            }
        }
    };
    const rzp = new Razorpay(options);
    rzp.open();
});
</script>

<?php include 'includes/footer.php'; ?>
