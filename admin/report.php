<?php
require_once '../includes/config.php';
requireLogin('admin');
$pageTitle = 'Reports — Admin';

$from_date     = $_GET['from_date'] ?? date('Y-m-01');
$to_date       = $_GET['to_date']   ?? date('Y-m-d');
$status_filter = $_GET['status']    ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date))   $to_date   = date('Y-m-d');

$sql    = "SELECT cr.*, uc.name AS cname, up.name AS pname FROM courier_requests cr JOIN users uc ON cr.customer_id=uc.id LEFT JOIN users up ON cr.partner_id=up.id WHERE DATE(cr.created_at) BETWEEN ? AND ?";
$params = [$from_date, $to_date];
$types  = "ss";
if ($status_filter) { $sql .= " AND cr.status=?"; $params[] = $status_filter; $types .= "s"; }
$sql .= " ORDER BY cr.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();

$sum = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN status NOT IN ('delivered','cancelled') THEN 1 ELSE 0 END) AS active,
        COALESCE(SUM(CASE WHEN payment_status='released' THEN reward_amount ELSE 0 END),0) AS total_released,
        COALESCE(SUM(CASE WHEN payment_status='paid' THEN reward_amount ELSE 0 END),0) AS total_held
    FROM courier_requests
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$sum->bind_param("ss", $from_date, $to_date);
$sum->execute();
$stats = $sum->get_result()->fetch_assoc();

$ps = $conn->prepare("
    SELECT u.name, COUNT(*) AS total,
           SUM(CASE WHEN cr.status='delivered' THEN 1 ELSE 0 END) AS delivered,
           COALESCE(SUM(CASE WHEN cr.payment_status='released' THEN cr.reward_amount ELSE 0 END),0) AS earned
    FROM courier_requests cr
    JOIN users u ON cr.partner_id=u.id
    WHERE DATE(cr.created_at) BETWEEN ? AND ? AND cr.partner_id IS NOT NULL
    GROUP BY cr.partner_id
    ORDER BY earned DESC
    LIMIT 10
");
$ps->bind_param("ss", $from_date, $to_date);
$ps->execute();
$partner_stats = $ps->get_result();

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

<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Platform Report</h1>
        <p class="text-xs text-slate-400 mt-0.5">Delivery statistics and payment analytics</p>
    </div>
    <button onclick="window.print()" class="text-xs border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Print</button>
</div>

<form method="GET" class="flex gap-3 mb-5 bg-white border border-slate-200 rounded-xl p-4 items-end">
    <div class="flex-1">
        <label class="text-xs text-slate-500 block mb-1">From</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>
    <div class="flex-1">
        <label class="text-xs text-slate-500 block mb-1">To</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>
    <div class="flex-1">
        <label class="text-xs text-slate-500 block mb-1">Status</label>
        <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            <option value="">All Statuses</option>
            <?php foreach (['pending','accepted','picked_up','awaiting_payment','payment_done','in_transit','delivered','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $status_filter===$st?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm hover:bg-slate-700 transition-colors">Generate</button>
</form>

<div class="grid grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= (int)$stats['total'] ?></div>
        <div class="text-xs text-slate-400">Total</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600"><?= (int)$stats['active'] ?></div>
        <div class="text-xs text-slate-400">Active</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600"><?= (int)$stats['delivered'] ?></div>
        <div class="text-xs text-slate-400">Delivered</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-red-400"><?= (int)$stats['cancelled'] ?></div>
        <div class="text-xs text-slate-400">Cancelled</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-xl font-bold text-green-700">₹<?= number_format((float)$stats['total_released'],0) ?></div>
        <div class="text-xs text-slate-400">Released</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-xl font-bold text-blue-700">₹<?= number_format((float)$stats['total_held'],0) ?></div>
        <div class="text-xs text-slate-400">In Hold</div>
    </div>
</div>

<div class="grid grid-cols-3 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold text-slate-700 text-sm mb-3">Top Travelers</h3>
        <?php $pc=0; while ($p = $partner_stats->fetch_assoc()): $pc++; ?>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0">
            <div>
                <div class="text-xs font-medium text-slate-700"><?= htmlspecialchars($p['name']) ?></div>
                <div class="text-xs text-slate-400"><?= (int)$p['delivered'] ?>/<?= (int)$p['total'] ?> delivered</div>
            </div>
            <div class="text-xs font-semibold text-green-600">₹<?= number_format((float)$p['earned'],0) ?></div>
        </div>
        <?php endwhile; ?>
        <?php if ($pc===0): ?><p class="text-xs text-slate-400">No data</p><?php endif; ?>
    </div>

    <div class="col-span-2 bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="font-semibold text-slate-700 text-sm">Delivery Details</h3>
        </div>
        <div class="overflow-auto max-h-72">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Parcel</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Sender</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Traveler</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Reward</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Payment</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Status</th>
                        <th class="text-left text-slate-500 font-medium px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $rc=0; while ($r = $requests->fetch_assoc()): $rc++;
                        $sc = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600';
                        $pc2 = ['unpaid'=>'text-slate-300','pending'=>'text-amber-500','paid'=>'text-blue-600','released'=>'text-green-600'];
                        $pcls = $pc2[$r['payment_status']] ?? 'text-slate-400';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></td>
                        <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($r['cname']) ?></td>
                        <td class="px-3 py-2 text-slate-500"><?= $r['pname'] ? htmlspecialchars($r['pname']) : '—' ?></td>
                        <td class="px-3 py-2 font-medium text-slate-700">₹<?= number_format($r['reward_amount'],2) ?></td>
                        <td class="px-3 py-2 font-medium <?= $pcls ?>"><?= ucfirst($r['payment_status']) ?></td>
                        <td class="px-3 py-2"><span class="px-1.5 py-0.5 rounded font-medium <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                        <td class="px-3 py-2 text-slate-400"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($rc===0): ?>
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-400">No records in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
