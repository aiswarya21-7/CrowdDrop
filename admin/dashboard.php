<?php
require_once '../includes/config.php';
requireLogin('admin');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Admin Dashboard — CrowdDrop';

$total_users    = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role!='admin'")->fetch_assoc()['c'];
$pending_users  = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role!='admin' AND is_verified=0")->fetch_assoc()['c'];
$pending_approv = (int)$conn->query("SELECT COUNT(*) AS c FROM courier_requests WHERE admin_verified=0 AND status='pending'")->fetch_assoc()['c'];
$total_requests = (int)$conn->query("SELECT COUNT(*) AS c FROM courier_requests")->fetch_assoc()['c'];
$active         = (int)$conn->query("SELECT COUNT(*) AS c FROM courier_requests WHERE status IN ('accepted','picked_up','awaiting_payment','payment_done','in_transit')")->fetch_assoc()['c'];
$delivered      = (int)$conn->query("SELECT COUNT(*) AS c FROM courier_requests WHERE status='delivered'")->fetch_assoc()['c'];
$revenue        = (float)$conn->query("SELECT COALESCE(SUM(reward_amount),0) AS t FROM courier_requests WHERE payment_status='released'")->fetch_assoc()['t'];

$recent = $conn->query("
    SELECT cr.*, uc.name AS cname, up.name AS pname
    FROM courier_requests cr
    JOIN users uc ON cr.customer_id=uc.id
    LEFT JOIN users up ON cr.partner_id=up.id
    ORDER BY cr.created_at DESC LIMIT 8
");

include '../includes/header.php';

$statusColors = [
    'pending'          => 'bg-amber-50 text-amber-600',
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
    <h1 class="text-xl font-bold text-slate-800">Admin Dashboard</h1>
    <p class="text-xs text-slate-400 mt-0.5">Platform overview and management</p>
</div>

<?php if ($pending_users > 0 || $pending_approv > 0): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-5 flex items-center gap-3">
    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    <span class="text-xs text-amber-700">
        <?php if ($pending_users): ?>
            <strong><?= $pending_users ?></strong> user account(s) awaiting Aadhaar & photo verification.
            <a href="users.php?pending=1" class="underline font-medium"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg> Review</a>
        <?php endif; ?>
        <?php if ($pending_approv): ?>
            &nbsp;&nbsp;<strong><?= $pending_approv ?></strong> courier request(s) need approval.
            <a href="couriers.php?unverified=1" class="underline font-medium"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg> Approve</a>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= $total_users ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total Users</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-amber-500"><?= $pending_users ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Pending Verification</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600"><?= $active ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Active Deliveries</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600"><?= $delivered ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Delivered</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= $total_requests ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total Requests</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800">₹<?= number_format($revenue,0) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Revenue Released</div>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-700 text-sm">Recent Courier Requests</h2>
        <a href="couriers.php" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg> View all</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Parcel</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Sender</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Route</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Traveler</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Payment</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-2">Approved</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while ($r = $recent->fetch_assoc()):
                $sc = $statusColors[$r['status']] ?? 'bg-slate-50 text-slate-600';
                $pc = ['unpaid'=>'text-slate-300','pending'=>'text-amber-500','paid'=>'text-blue-600','released'=>'text-green-600'];
                $pcls = $pc[$r['payment_status']] ?? 'text-slate-400';
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['cname']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= $r['pname'] ? htmlspecialchars($r['pname']) : '—' ?></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-3 text-xs font-medium <?= $pcls ?>"><?= ucfirst($r['payment_status']) ?></td>
                <td class="px-4 py-3">
                    <?php if ($r['admin_verified']): ?>
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                        <a href="couriers.php?approve=<?= $r['id'] ?>" class="text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700 transition-colors">Approve</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
