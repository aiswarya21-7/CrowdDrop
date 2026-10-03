<?php
require_once '../includes/config.php';
requireLogin('partner');
$pageTitle = 'Trip Report — CrowdDrop';

$uid       = (int)$_SESSION['user_id'];
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date))   $to_date   = date('Y-m-d');

$s = $conn->prepare("
    SELECT cr.*, u.name AS customer_name
    FROM courier_requests cr
    JOIN users u ON cr.customer_id=u.id
    WHERE cr.partner_id=? AND DATE(cr.updated_at) BETWEEN ? AND ?
    ORDER BY cr.updated_at DESC
");
$s->bind_param("iss", $uid, $from_date, $to_date);
$s->execute();
$deliveries = $s->get_result();

$sum = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered,
        COALESCE(SUM(CASE WHEN payment_status='released' THEN reward_amount ELSE 0 END),0) AS earned
    FROM courier_requests
    WHERE partner_id=? AND DATE(updated_at) BETWEEN ? AND ?
");
$sum->bind_param("iss", $uid, $from_date, $to_date);
$sum->execute();
$summary = $sum->get_result()->fetch_assoc();

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

<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Trip Report</h1>
        <p class="text-xs text-slate-400 mt-0.5">Your earnings and delivery history</p>
    </div>
    <button onclick="window.print()" class="text-xs border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Print</button>
</div>

<form method="GET" class="flex gap-3 mb-5 bg-white border border-slate-200 rounded-xl p-4 items-end">
    <div class="flex-1">
        <label class="text-xs text-slate-500 block mb-1">From Date</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>
    <div class="flex-1">
        <label class="text-xs text-slate-500 block mb-1">To Date</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>
    <button type="submit" class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition-colors">Generate</button>
</form>

<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= (int)$summary['total'] ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total in Period</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600"><?= (int)$summary['delivered'] ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Delivered</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800">₹<?= number_format((float)$summary['earned'], 2) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Earnings Released</div>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Parcel</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Sender</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Route</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Reward</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Payment</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php $rc=0; while ($r = $deliveries->fetch_assoc()): $rc++;
                $sc = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600';
                $pc = ['unpaid'=>'text-slate-300','pending'=>'text-amber-500','paid'=>'text-blue-600','released'=>'text-green-600'];
                $pcls = $pc[$r['payment_status']] ?? 'text-slate-400';
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['customer_name']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></td>
                <td class="px-4 py-3 text-xs font-medium text-slate-700">₹<?= number_format($r['reward_amount'],2) ?></td>
                <td class="px-4 py-3 text-xs font-medium <?= $pcls ?>"><?= ucfirst($r['payment_status']) ?></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-3 text-xs text-slate-400"><?= date('d M Y', strtotime($r['updated_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($rc===0): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-xs text-slate-400">No records in selected period.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
