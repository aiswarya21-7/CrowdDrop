<?php
require_once '../includes/config.php';
requireLogin('admin');
$pageTitle = 'Users — Admin';

if (isset($_GET['verify'])) {
    $vid = intval($_GET['verify']);
    $stmt = $conn->prepare("UPDATE users SET is_verified=1 WHERE id=? AND role != 'admin'");
    $stmt->bind_param("i", $vid);
    $stmt->execute();
    $stmt->close();
    header("Location: users.php?msg=verified");
    exit;
}

if (isset($_GET['revoke'])) {
    $rid = intval($_GET['revoke']);
    $stmt = $conn->prepare("UPDATE users SET is_verified=0 WHERE id=? AND role != 'admin'");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->close();
    header("Location: users.php?msg=revoked");
    exit;
}

$role_filter  = $_GET['role'] ?? '';
$only_pending = isset($_GET['pending']);

$allowed_roles = ['customer', 'partner'];
if (!in_array($role_filter, $allowed_roles)) $role_filter = '';

$where = "role != 'admin'";
if ($role_filter)  $where .= " AND role='" . $role_filter . "'";  
if ($only_pending) $where .= " AND is_verified=0";

$users = $conn->query("SELECT * FROM users WHERE $where ORDER BY is_verified ASC, created_at DESC");

$pending_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE role != 'admin' AND is_verified=0")->fetch_assoc()['c'];

include '../includes/header.php';
?>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-800">User Management</h1>
        <p class="text-xs text-slate-400 mt-0.5">Review Aadhaar details and approve accounts</p>
    </div>
    <?php if ($pending_count > 0): ?>
    <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-3 py-1 rounded-full">
        <?= $pending_count ?> pending approval<?= $pending_count > 1 ? 's' : '' ?>
    </span>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2 rounded-lg mb-4">
    <?php
    $msgs = ['verified'=>'Account verified — user can now log in.','revoked'=>'Account access revoked.'];
    echo $msgs[$_GET['msg']] ?? 'Done.';
    ?>
</div>
<?php endif; ?>

<div class="flex gap-2 mb-4 flex-wrap">
    <a href="users.php"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= !$role_filter && !$only_pending ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        All Users
    </a>
    <a href="users.php?pending=1"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= $only_pending ? 'bg-amber-500 text-white border-amber-500' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Pending <?= $pending_count > 0 ? "($pending_count)" : '' ?>
    </a>
    <a href="users.php?role=customer"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= $role_filter==='customer' && !$only_pending ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Senders
    </a>
    <a href="users.php?role=partner"
       class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= $role_filter==='partner' && !$only_pending ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        Travelers
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Name</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Email / Phone</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Documents</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Bank Details</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Role</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Status</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Joined</th>
                <th class="text-left text-xs text-slate-500 font-medium px-4 py-3">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php $count = 0; while ($u = $users->fetch_assoc()): $count++; ?>
            <tr class="hover:bg-slate-50 <?= !$u['is_verified'] ? 'bg-amber-50/30' : '' ?>">
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-700 text-sm"><?= htmlspecialchars($u['name']) ?></div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs text-slate-600"><?= htmlspecialchars($u['email']) ?></div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($u['phone'] ?? '—') ?></div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <?php if ($u['aadhaar_img']): ?>
                        <a href="<?= UPLOAD_URL . htmlspecialchars($u['aadhaar_img']) ?>" target="_blank"
                           class="text-xs bg-blue-50 text-blue-600 border border-blue-200 px-2 py-1 rounded-lg hover:bg-blue-100 transition-colors">
                            View Aadhaar
                        </a>
                        <?php else: ?>
                        <span class="text-xs text-slate-300">Not uploaded</span>
                        <?php endif; ?>
                        <?php if ($u['photo_img']): ?>
                        <a href="<?= UPLOAD_URL . htmlspecialchars($u['photo_img']) ?>" target="_blank">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($u['photo_img']) ?>"
                                 class="w-8 h-8 rounded-full object-cover border border-slate-200"
                                 title="Passport photo">
                        </a>
                        <?php else: ?>
                        <span class="text-xs text-slate-300">No photo</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <?php if ($u['role'] === 'partner' && !empty($u['bank_account'])): ?>
                    <div class="text-xs space-y-0.5">
                        <div class="font-mono text-slate-600">****<?= substr(htmlspecialchars($u['bank_account']), -4) ?></div>
                        <div class="text-slate-400"><?= htmlspecialchars($u['ifsc_code'] ?? '') ?></div>
                        <div class="text-slate-400 truncate max-w-[100px]"><?= htmlspecialchars($u['branch_name'] ?? '') ?></div>
                    </div>
                    <?php elseif ($u['role'] === 'partner'): ?>
                    <span class="text-xs text-amber-500">Not provided</span>
                    <?php else: ?>
                    <span class="text-xs text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        <?= $u['role']==='partner' ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $u['role']==='customer'?'Sender':($u['role']==='partner'?'Traveler':'Admin') ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <?php if ($u['is_verified']): ?>
                        <span class="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Verified
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs text-amber-600 font-medium">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            Pending
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-xs text-slate-400"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td class="px-4 py-3">
                    <?php if (!$u['is_verified']): ?>
                        <a href="users.php?verify=<?= $u['id'] ?>"
                           class="text-xs bg-green-600 text-white px-2.5 py-1 rounded-lg hover:bg-green-700 transition-colors font-medium">
                            Verify
                        </a>
                    <?php else: ?>
                        <a href="users.php?revoke=<?= $u['id'] ?>"
                           onclick="return confirm('Revoke access for <?= htmlspecialchars(addslashes($u['name'])) ?>?')"
                           class="text-xs text-red-400 hover:text-red-600 font-medium">
                            Revoke
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="8" class="px-4 py-10 text-center text-xs text-slate-400">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
