<?php
require_once '../includes/config.php';
requireLogin('customer');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Dashboard — CrowdDrop';

$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT cr.*, u.name AS partner_name
    FROM courier_requests cr
    LEFT JOIN users u ON cr.partner_id = u.id
    WHERE cr.customer_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$requests = $stmt->get_result();

$stTotal = $conn->prepare("SELECT COUNT(*) AS c FROM courier_requests WHERE customer_id = ?");
$stTotal->bind_param("i", $uid); $stTotal->execute();
$total = (int)$stTotal->get_result()->fetch_assoc()['c'];

$stActive = $conn->prepare("SELECT COUNT(*) AS c FROM courier_requests WHERE customer_id = ? AND status NOT IN ('delivered','cancelled')");
$stActive->bind_param("i", $uid); $stActive->execute();
$active = (int)$stActive->get_result()->fetch_assoc()['c'];

$stDone = $conn->prepare("SELECT COUNT(*) AS c FROM courier_requests WHERE customer_id = ? AND status = 'delivered'");
$stDone->bind_param("i", $uid); $stDone->execute();
$delivered = (int)$stDone->get_result()->fetch_assoc()['c'];

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-800">My Dashboard</h1>
    <p class="text-xs text-slate-400 mt-0.5">Track your parcels and manage requests</p>
</div>

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= $total ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total Requests</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600"><?= $active ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Active</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600"><?= $delivered ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Delivered</div>
    </div>
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold text-slate-700 text-sm">Recent Requests</h2>
    <a href="/courier/customer/new_request.php" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors">+ New Request</a>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <?php if ($total === 0): ?>
    <div class="px-4 py-12 text-center">
        <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <p class="text-sm text-slate-400 mb-3">No delivery requests yet.</p>
        <a href="/courier/customer/new_request.php" class="text-xs bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700">Create your first request</a>
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Parcel</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Route</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Traveler</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php while ($r = $requests->fetch_assoc()):
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
                $sc = $statusColors[$r['status']] ?? 'bg-slate-50 text-slate-600';
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-700 text-sm"><?= htmlspecialchars($r['parcel_name']) ?></div>
                    <div class="text-xs text-slate-400">₹<?= number_format($r['reward_amount'], 2) ?></div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs text-slate-600"><?= htmlspecialchars($r['from_location']) ?></div>
                    <div class="text-xs text-slate-400">→ <?= htmlspecialchars($r['to_location']) ?></div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                    <?= $r['partner_name'] ? htmlspecialchars($r['partner_name']) : '<span class="text-slate-300">No traveler yet</span>' ?>
                </td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-1 rounded-full font-medium <?= $sc ?>">
                        <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <a href="/courier/customer/track.php?id=<?= (int)$r['id'] ?>" class="text-xs text-slate-600 hover:text-slate-900 underline">Track</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

