<?php
require_once '../includes/config.php';
requireLogin('partner');
$pageTitle = 'My Trips — CrowdDrop';

$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT cr.*, u.name AS customer_name
    FROM courier_requests cr
    JOIN users u ON cr.customer_id = u.id
    WHERE cr.partner_id = ?
    ORDER BY cr.updated_at DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$deliveries = $stmt->get_result();
$stmt->close();

include '../includes/header.php';

$statusColors = [
    'accepted'         => 'bg-blue-50 text-blue-600',
    'picked_up'        => 'bg-purple-50 text-purple-600',
    'awaiting_payment' => 'bg-orange-50 text-orange-600',
    'payment_done'     => 'bg-cyan-50 text-cyan-600',
    'in_transit'       => 'bg-indigo-50 text-indigo-600',
    'delivered'        => 'bg-green-50 text-green-600',
    'cancelled'        => 'bg-red-50 text-red-600',
];

$payColors = [
    'unpaid'   => 'text-slate-300',
    'pending'  => 'text-amber-500',
    'paid'     => 'text-blue-600',
    'released' => 'text-green-600',
];
?>

<div class="mb-5">
    <h1 class="text-xl font-bold text-slate-800">My Trips</h1>
    <p class="text-xs text-slate-400 mt-0.5">All accepted and completed trips</p>
</div>

<div class="space-y-3">
    <?php $count = 0; while ($r = $deliveries->fetch_assoc()): $count++;
        $sc   = $statusColors[$r['status']]      ?? 'bg-slate-50 text-slate-600';
        $pcls = $payColors[$r['payment_status']] ?? 'text-slate-300';
        $waitingPay = in_array($r['status'], ['picked_up','awaiting_payment']) && $r['payment_status'] !== 'paid';
    ?>
    <div class="bg-white border <?= $waitingPay ? 'border-amber-200' : 'border-slate-200' ?> rounded-xl p-4">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0 mr-3">
                <div class="font-medium text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></div>
                <div class="text-xs text-slate-400 mt-0.5 truncate">
                    <?= htmlspecialchars($r['customer_name']) ?> &middot;
                    <?= htmlspecialchars($r['from_location']) ?> &rarr; <?= htmlspecialchars($r['to_location']) ?>
                </div>
            </div>
            <span class="text-xs px-2 py-1 rounded-full font-medium whitespace-nowrap <?= $sc ?>">
                <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
            </span>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-400">
            <span class="font-medium text-slate-600">₹<?= number_format($r['reward_amount'], 2) ?></span>
            <span><?= date('d M Y', strtotime($r['created_at'])) ?></span>
            <span class="flex items-center gap-1 <?= $pcls ?>">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <?= ucfirst($r['payment_status']) ?>
            </span>
            <?php if ($waitingPay): ?>
            <span class="flex items-center gap-1 text-amber-600 font-medium">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Waiting for sender payment
            </span>
            <?php endif; ?>
        </div>

        <?php if (!in_array($r['status'], ['delivered','cancelled'])): ?>
        <a href="/courier/partner/delivery.php?id=<?= (int)$r['id'] ?>"
           class="mt-3 inline-flex items-center gap-1.5 text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Manage Delivery
        </a>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>

    <?php if ($count === 0): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-10 text-center">
        <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </div>
        <p class="text-sm text-slate-400 mb-3">No deliveries yet.</p>
        <a href="available.php" class="text-xs bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition-colors">Find a delivery</a>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
