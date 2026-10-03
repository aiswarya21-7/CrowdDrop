<?php
require_once '../includes/config.php';
requireLogin('admin');
$pageTitle = 'Courier Requests — Admin';

if (isset($_GET['approve'])) {
    $aid = (int)$_GET['approve'];
    $s = $conn->prepare("UPDATE courier_requests SET admin_verified=1 WHERE id=?");
    $s->bind_param("i", $aid); $s->execute(); $s->close();
    header("Location: couriers.php?msg=approved"); exit;
}
if (isset($_GET['cancel'])) {
    $cid = (int)$_GET['cancel'];
    $s = $conn->prepare("UPDATE courier_requests SET status='cancelled' WHERE id=?");
    $s->bind_param("i", $cid); $s->execute(); $s->close();
    header("Location: couriers.php?msg=cancelled"); exit;
}

$status_filter = $_GET['status'] ?? '';
$only_pending  = isset($_GET['unverified']);

$sql  = "SELECT cr.*, uc.name AS cname, uc.email AS cemail, up.name AS pname
         FROM courier_requests cr
         JOIN users uc ON cr.customer_id = uc.id
         LEFT JOIN users up ON cr.partner_id = up.id
         WHERE 1=1";
$params = []; $types = "";

if ($status_filter) { $sql .= " AND cr.status=?"; $params[] = $status_filter; $types .= "s"; }
if ($only_pending)  { $sql .= " AND cr.admin_verified=0"; }
$sql .= " ORDER BY cr.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();

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
        <h1 class="text-xl font-bold text-slate-800">Courier Requests</h1>
        <p class="text-xs text-slate-400 mt-0.5">Review, approve and manage all delivery requests</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2 rounded-lg mb-4">
    Request <?= htmlspecialchars($_GET['msg']) ?> successfully.
</div>
<?php endif; ?>

<div class="flex gap-2 mb-4 flex-wrap">
    <?php
    $tabs = [
        '' => 'All', 'pending' => 'Pending', 'accepted' => 'Accepted',
        'picked_up' => 'Picked Up', 'awaiting_payment' => 'Awaiting Payment',
        'payment_done' => 'Payment Done', 'in_transit' => 'In Transit',
        'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
    ];
    foreach ($tabs as $val => $label):
        $active = (!$only_pending && $status_filter === $val);
    ?>
    <a href="couriers.php<?= $val ? '?status='.$val : '' ?>"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= $active ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
    <a href="couriers.php?unverified=1"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= $only_pending ? 'bg-amber-500 text-white border-amber-500' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Needs Approval
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">#</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Parcel</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Sender</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Receiver</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Route</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Traveler</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Reward</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Payment</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php $count = 0; while ($r = $requests->fetch_assoc()): $count++;
                $sc = $statusColors[$r['status']] ?? 'bg-slate-50 text-slate-600';
                $pc = ['unpaid'=>'text-slate-400','pending'=>'text-amber-500','paid'=>'text-blue-600','released'=>'text-green-600'];
                $pcls = $pc[$r['payment_status']] ?? 'text-slate-400';
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-xs text-slate-400"><?= $r['id'] ?></td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-700 text-sm"><?= htmlspecialchars($r['parcel_name']) ?></div>
                    <?php if ($r['parcel_weight']): ?><div class="text-xs text-slate-400"><?= $r['parcel_weight'] ?>kg</div><?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs text-slate-600"><?= htmlspecialchars($r['cname']) ?></div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($r['cemail']) ?></div>
                </td>
                <td class="px-4 py-3">
                    <?php if (!empty($r['receiver_name'])): ?>
                    <div class="text-xs text-slate-600"><?= htmlspecialchars($r['receiver_name']) ?></div>
                    <?php if ($r['receiver_phone']): ?>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($r['receiver_phone']) ?></div>
                    <?php endif; ?>
                    <?php else: ?><span class="text-xs text-slate-300">—</span><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= $r['pname'] ? htmlspecialchars($r['pname']) : '<span class="text-slate-300">—</span>' ?></td>
                <td class="px-4 py-3 text-xs font-medium text-slate-700">₹<?= number_format($r['reward_amount'],2) ?></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-3 text-xs font-medium <?= $pcls ?>"><?= ucfirst($r['payment_status']) ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="admin_track.php?id=<?= $r['id'] ?>" class="text-xs text-slate-500 hover:text-slate-700 underline">View</a>
                        <?php if (!$r['admin_verified'] && $r['status'] === 'pending'): ?>
                        <a href="couriers.php?approve=<?= $r['id'] ?>" class="text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700 transition-colors">Approve</a>
                        <?php endif; ?>
                        <?php if (!in_array($r['status'], ['delivered','cancelled'])): ?>
                        <a href="couriers.php?cancel=<?= $r['id'] ?>" onclick="return confirm('Cancel this request?')" class="text-xs text-red-400 hover:text-red-600">Cancel</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="9" class="px-4 py-8 text-center text-xs text-slate-400">No requests found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
