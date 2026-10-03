<?php
require_once '../includes/config.php';
requireLogin('partner');
$pageTitle = 'My Payments — CrowdDrop';

$uid = (int)$_SESSION['user_id'];

$bstmt = $conn->prepare("SELECT bank_account, ifsc_code, branch_name, name, phone, email FROM users WHERE id=?");
$bstmt->bind_param("i", $uid); $bstmt->execute();
$me = $bstmt->get_result()->fetch_assoc(); $bstmt->close();

$pstmt = $conn->prepare("
    SELECT cr.*, u.name AS customer_name
    FROM courier_requests cr
    JOIN users u ON cr.customer_id = u.id
    WHERE cr.partner_id = ? AND cr.payment_status != 'unpaid'
    ORDER BY cr.updated_at DESC
");
$pstmt->bind_param("i", $uid); $pstmt->execute();
$payments = $pstmt->get_result();

$sstmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN payment_status='released' THEN reward_amount ELSE 0 END), 0) AS released,
        COALESCE(SUM(CASE WHEN payment_status='paid'     THEN reward_amount ELSE 0 END), 0) AS in_escrow,
        COUNT(CASE WHEN payment_status='released' THEN 1 END)                              AS count_released
    FROM courier_requests WHERE partner_id=?
");
$sstmt->bind_param("i", $uid); $sstmt->execute();
$summary = $sstmt->get_result()->fetch_assoc(); $sstmt->close();

$bank_success = ''; $bank_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bank'])) {
    $ba = trim($_POST['bank_account'] ?? '');
    $ic = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $bn = trim($_POST['branch_name']  ?? '');

    if (!$ba || !preg_match('/^\d{9,18}$/', $ba))
        $bank_error = 'Valid bank account number required (9–18 digits).';
    elseif (!$ic || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ic))
        $bank_error = 'Valid IFSC code required (e.g. SBIN0001234).';
    elseif (!$bn)
        $bank_error = 'Branch name is required.';
    else {
        $upd = $conn->prepare("UPDATE users SET bank_account=?, ifsc_code=?, branch_name=? WHERE id=?");
        $upd->bind_param("sssi", $ba, $ic, $bn, $uid);
        $upd->execute(); $upd->close();
        $bank_success = 'Bank details updated successfully.';
        $me['bank_account'] = $ba; $me['ifsc_code'] = $ic; $me['branch_name'] = $bn;
    }
}

include '../includes/header.php';

$payColors = [
    'pending'  => 'bg-amber-50 text-amber-600',
    'paid'     => 'bg-blue-50 text-blue-600',
    'released' => 'bg-green-50 text-green-600',
];
?>

<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-800">My Payments</h1>
    <p class="text-xs text-slate-400 mt-0.5">Earnings, payment history and bank account details</p>
</div>

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600">₹<?= number_format((float)$summary['released'], 2) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Total Released</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600">₹<?= number_format((float)$summary['in_escrow'], 2) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">In Hold</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <div class="text-2xl font-bold text-slate-800"><?= (int)$summary['count_released'] ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Completed Deliveries Paid</div>
    </div>
</div>

<div class="grid grid-cols-3 gap-5">

    <div class="col-span-2">
        <h2 class="font-semibold text-slate-700 text-sm mb-3">Payment History</h2>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Parcel</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Sender</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Route</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Amount</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Status</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Razorpay ID</th>
                        <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $count = 0; while ($p = $payments->fetch_assoc()): $count++;
                        $pcls = $payColors[$p['payment_status']] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-700"><?= htmlspecialchars($p['parcel_name']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($p['customer_name']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <?= htmlspecialchars($p['from_location']) ?> &rarr; <?= htmlspecialchars($p['to_location']) ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold text-slate-700">₹<?= number_format($p['reward_amount'], 2) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $pcls ?>">
                                <?= ucfirst($p['payment_status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-400">
                            <?= $p['razorpay_payment_id'] ? htmlspecialchars(substr($p['razorpay_payment_id'],0,18)).'…' : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($count === 0): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-xs text-slate-400">No payment records yet. Payments appear once a sender pays for their delivery.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h2 class="font-semibold text-slate-700 text-sm mb-3">Bank Account</h2>

        <?php if ($me['bank_account']): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span class="text-xs font-semibold text-green-700">Bank account linked</span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div>
                    <span class="text-slate-400">Account:</span>
                    <span class="text-slate-700 font-mono ml-1">
                        ****<?= substr(htmlspecialchars($me['bank_account']), -4) ?>
                    </span>
                </div>
                <div><span class="text-slate-400">IFSC:</span> <span class="text-slate-700 font-mono ml-1"><?= htmlspecialchars($me['ifsc_code']) ?></span></div>
                <div><span class="text-slate-400">Branch:</span> <span class="text-slate-700 ml-1"><?= htmlspecialchars($me['branch_name']) ?></span></div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-xs text-amber-700 flex items-start gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            No bank account linked. Add your details below to receive payments.
        </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-slate-600 mb-3"><?= $me['bank_account'] ? 'Update' : 'Add' ?> Bank Details</h3>

            <?php if ($bank_error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg mb-3"><?= htmlspecialchars($bank_error) ?></div>
            <?php endif; ?>
            <?php if ($bank_success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2 rounded-lg mb-3"><?= htmlspecialchars($bank_success) ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-2.5">
                <input type="hidden" name="update_bank" value="1">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Account Number <span class="text-red-400">*</span></label>
                    <input type="text" name="bank_account" value="<?= htmlspecialchars($me['bank_account'] ?? '') ?>"
                        placeholder="9–18 digit account number" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-slate-200"
                        oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">IFSC Code <span class="text-red-400">*</span></label>
                    <input type="text" name="ifsc_code" value="<?= htmlspecialchars($me['ifsc_code'] ?? '') ?>"
                        placeholder="e.g. SBIN0001234" maxlength="11" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-mono uppercase focus:outline-none focus:ring-2 focus:ring-slate-200"
                        oninput="this.value=this.value.toUpperCase()">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Branch Name <span class="text-red-400">*</span></label>
                    <input type="text" name="branch_name" value="<?= htmlspecialchars($me['branch_name'] ?? '') ?>"
                        placeholder="e.g. MG Road, Kochi" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <button type="submit" class="w-full bg-slate-800 text-white text-xs font-medium py-2 rounded-lg hover:bg-slate-700 transition-colors">
                    Save Bank Details
                </button>
            </form>

            <p class="text-xs text-slate-400 mt-3 leading-relaxed">
                Your bank details are used to process payment releases after successful deliveries. Keep them accurate.
            </p>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
