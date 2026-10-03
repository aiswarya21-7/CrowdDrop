<?php
require_once '../includes/config.php';
requireLogin('partner');
require_once __DIR__ . '/../auto_update_requests.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Traveler Dashboard — CrowdDrop';

$uid = (int)$_SESSION['user_id'];

$s = $conn->prepare("SELECT COUNT(*) AS c FROM courier_requests WHERE partner_id=? AND status NOT IN ('delivered','cancelled')");
$s->bind_param("i",$uid); $s->execute();
$active = (int)$s->get_result()->fetch_assoc()['c']; $s->close();

$s = $conn->prepare("SELECT COUNT(*) AS c FROM courier_requests WHERE partner_id=? AND status='delivered'");
$s->bind_param("i",$uid); $s->execute();
$delivered = (int)$s->get_result()->fetch_assoc()['c']; $s->close();

$s = $conn->prepare("SELECT COALESCE(SUM(reward_amount),0) AS total FROM courier_requests WHERE partner_id=? AND status='delivered' AND payment_status='released'");
$s->bind_param("i",$uid); $s->execute();
$earnings = (float)$s->get_result()->fetch_assoc()['total']; $s->close();

$s = $conn->prepare("
    SELECT cr.*, u.name AS customer_name
    FROM courier_requests cr
    JOIN users u ON cr.customer_id=u.id
    WHERE cr.partner_id=?
    ORDER BY cr.updated_at DESC LIMIT 6
");
$s->bind_param("i",$uid); $s->execute();
$my_deliveries = $s->get_result();

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
?>

<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-800">Traveler Dashboard</h1>
    <p class="text-xs text-slate-400 mt-0.5">Manage your deliveries and earnings</p>
</div>

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600"><?= $active ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Active Deliveries</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600"><?= $delivered ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Completed</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800">₹<?= number_format($earnings, 2) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total Earned</div>
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-6">
    <a href="/courier/partner/available.php" class="bg-slate-800 text-white rounded-xl p-4 hover:bg-slate-700 transition-colors block">
        <div class="font-semibold text-sm mb-1">Find Parcels</div>
        <div class="text-xs text-slate-300">Browse requests matching your route</div>
    </a>
    <a href="/courier/partner/my_deliveries.php" class="bg-white border border-slate-200 rounded-xl p-4 hover:bg-slate-50 transition-colors block">
        <div class="font-semibold text-sm text-slate-700 mb-1">My Trips</div>
        <div class="text-xs text-slate-400">View and update accepted deliveries</div>
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-700 text-sm">Recent Activity</h2>
        <a href="/courier/partner/my_deliveries.php" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg> View all</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Parcel</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Route</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Reward</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Payment</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php $count=0; while ($r = $my_deliveries->fetch_assoc()): $count++;
                $sc = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600';
                $pc = ['unpaid'=>'text-slate-300','pending'=>'text-amber-500','paid'=>'text-blue-600','released'=>'text-green-600'];
                $pcls = $pc[$r['payment_status']] ?? 'text-slate-400';
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($r['customer_name']) ?></div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></td>
                <td class="px-4 py-3 text-xs font-medium text-slate-600">₹<?= number_format($r['reward_amount'],2) ?></td>
                <td class="px-4 py-3 text-xs font-medium <?= $pcls ?>"><?= ucfirst($r['payment_status']) ?></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-3">
                    <?php if (!in_array($r['status'],['delivered','cancelled'])): ?>
                    <a href="/courier/partner/delivery.php?id=<?= (int)$r['id'] ?>" class="text-xs text-slate-500 hover:text-slate-800 underline">Manage</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count===0): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-xs text-slate-400">No deliveries yet. <a href="/courier/partner/available.php" class="underline">Find one</a></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
