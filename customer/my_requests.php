<?php
require_once '../includes/config.php';
requireLogin('customer');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'My Parcels — CrowdDrop';

$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT cr.*, u.name AS partner_name
    FROM courier_requests cr
    LEFT JOIN users u ON cr.partner_id = u.id
    WHERE cr.customer_id = ?
    ORDER BY cr.created_at DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$requests = $stmt->get_result();
$stmt->close();

include '../includes/header.php';

$statusColors = [
    'pending'          => 'bg-amber-50 text-amber-600 border-amber-200',
    'accepted'         => 'bg-blue-50 text-blue-600 border-blue-200',
    'picked_up'        => 'bg-purple-50 text-purple-600 border-purple-200',
    'awaiting_payment' => 'bg-orange-50 text-orange-600 border-orange-200',
    'payment_done'     => 'bg-cyan-50 text-cyan-600 border-cyan-200',
    'in_transit'       => 'bg-indigo-50 text-indigo-600 border-indigo-200',
    'delivered'        => 'bg-green-50 text-green-600 border-green-200',
    'cancelled'        => 'bg-red-50 text-red-600 border-red-200',
];

$payColors = [
    'unpaid'   => 'text-slate-300',
    'pending'  => 'text-amber-500',
    'paid'     => 'text-blue-600',
    'released' => 'text-green-600',
];
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-800">My Parcels</h1>
        <p class="text-xs text-slate-400 mt-0.5">All your delivery requests</p>
    </div>
    <a href="/courier/customer/new_request.php"
       class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Request
    </a>
</div>

<?php if (isset($_GET['cancelled'])): ?>
<div class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg mb-4 flex items-center gap-2">
    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
    Request cancelled successfully.
</div>
<?php endif; ?>

<?php if (isset($_GET['created'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2 rounded-lg mb-4 flex items-center gap-2">
    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    Request created! Delivery partners matching your route will see it once admin approves it.
</div>
<?php endif; ?>

<div class="space-y-3">
    <?php $count = 0; while ($r = $requests->fetch_assoc()): $count++;
        $sc   = $statusColors[$r['status']]       ?? 'bg-slate-50 text-slate-600 border-slate-200';
        $pcls = $payColors[$r['payment_status']]  ?? 'text-slate-300';
        $needsPay = in_array($r['status'], ['picked_up','awaiting_payment']) && $r['payment_status'] !== 'paid';
    ?>
    <div class="bg-white border <?= $needsPay ? 'border-orange-300' : 'border-slate-200' ?> rounded-xl p-4 transition-colors">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0 mr-3">
                <div class="font-medium text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></div>
                <div class="text-xs text-slate-400 mt-0.5 truncate">
                    <?= htmlspecialchars($r['from_location']) ?> &rarr; <?= htmlspecialchars($r['to_location']) ?>
                </div>
            </div>
            <span class="text-xs px-2 py-1 rounded-full border font-medium whitespace-nowrap <?= $sc ?>">
                <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
            </span>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-400">
            <span class="font-medium text-slate-600">₹<?= number_format($r['reward_amount'], 2) ?></span>
            <?php if ($r['parcel_weight']): ?>
            <span><?= $r['parcel_weight'] ?> kg</span>
            <?php endif; ?>
            <span><?= date('d M Y', strtotime($r['created_at'])) ?></span>
            <?php if ($r['partner_name']): ?>
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <?= htmlspecialchars($r['partner_name']) ?>
            </span>
            <?php endif; ?>
            <span class="flex items-center gap-1 <?= $pcls ?>">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <?= ucfirst($r['payment_status']) ?>
            </span>
        </div>

        <?php if ($r['receiver_name'] || $r['receiver_email'] || $r['receiver_phone']): ?>
        <div class="mt-2 bg-slate-50 rounded-lg px-3 py-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <?php if ($r['receiver_name']): ?>
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <?= htmlspecialchars($r['receiver_name']) ?>
            </span>
            <?php endif; ?>
            <?php if ($r['receiver_phone']): ?>
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <?= htmlspecialchars($r['receiver_phone']) ?>
            </span>
            <?php endif; ?>
            <?php if ($r['receiver_email']): ?>
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <?= htmlspecialchars($r['receiver_email']) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="mt-3 flex items-center gap-2">
            <a href="/courier/customer/track.php?id=<?= (int)$r['id'] ?>"
               class="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Track
            </a>
            <?php if ($needsPay): ?>
            <a href="/courier/payment_create.php?courier_id=<?= (int)$r['id'] ?>"
               class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1 font-medium">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Pay ₹<?= number_format($r['reward_amount'], 2) ?>
            </a>
            <?php endif; ?>
            <?php if ($r['status'] === 'pending'): ?>
            <a href="/courier/customer/cancel_request.php?id=<?= (int)$r['id'] ?>"
               onclick="return confirm('Cancel this request? This cannot be undone.')"
               class="text-xs bg-red-50 text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100 transition-colors flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>

    <?php if ($count === 0): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-10 text-center">
        <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <p class="text-sm text-slate-400 mb-3">No parcels yet.</p>
        <a href="/courier/customer/new_request.php" class="text-xs bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition-colors">Create your first request</a>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
