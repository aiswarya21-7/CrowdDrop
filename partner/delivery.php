<?php
require_once '../includes/config.php';
requireLogin('partner');
$pageTitle = 'Manage Delivery — CrowdDrop';

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

if (!$id) { header("Location: /courier/partner/my_deliveries.php"); exit; }

function loadCourier($conn, $id, $uid) {
    $s = $conn->prepare("
        SELECT cr.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
        FROM courier_requests cr JOIN users u ON cr.customer_id=u.id
        WHERE cr.id=? AND cr.partner_id=?
    ");
    $s->bind_param("ii", $id, $uid);
    $s->execute();
    $res = $s->get_result(); $s->close();
    return $res->num_rows ? $res->fetch_assoc() : null;
}

$r = loadCourier($conn, $id, $uid);
if (!$r) {
    include '../includes/header.php';
    echo '<div class="max-w-md mx-auto mt-10 bg-red-50 border border-red-200 rounded-xl p-6 text-center">
            <p class="font-semibold text-red-700 mb-1">Access Denied</p>
            <p class="text-xs text-red-500 mb-4">This delivery is not assigned to you.</p>
            <a href="/courier/partner/my_deliveries.php" class="text-xs text-slate-600 underline"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back</a></div>';
    include '../includes/footer.php'; exit;
}

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fresh  = loadCourier($conn, $id, $uid);

    if ($action === 'verify_pickup') {
        $entered = trim($_POST['otp'] ?? '');
        if ($entered === $fresh['pickup_otp'] && $fresh['status'] === 'accepted') {
            $upd = $conn->prepare("UPDATE courier_requests SET status='picked_up', pickup_otp_verified=1 WHERE id=?");
            $upd->bind_param("i",$id); $upd->execute(); $upd->close();
            $note='Parcel picked up. OTP verified. Awaiting sender payment.';
            $trk=$conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'Picked Up',?,?)");
            $trk->bind_param("isi",$id,$note,$uid); $trk->execute(); $trk->close();
            $success='Pickup verified! Sender has been prompted to pay. You can start transit once payment is confirmed.';
        } else {
            $error='Incorrect pickup OTP. Please try again.';
        }
        $r = loadCourier($conn, $id, $uid);
    }

    if ($action === 'start_transit') {
        if ($fresh['status'] === 'payment_done' && $fresh['payment_status'] === 'paid') {
            $upd=$conn->prepare("UPDATE courier_requests SET status='in_transit' WHERE id=?");
            $upd->bind_param("i",$id); $upd->execute(); $upd->close();
            $note='Parcel is in transit. Payment confirmed.';
            $trk=$conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'In Transit',?,?)");
            $trk->bind_param("isi",$id,$note,$uid); $trk->execute(); $trk->close();
            $success='Status updated to In Transit!';
        } else {
            $error='Cannot start transit — payment has not been received yet.';
        }
        $r = loadCourier($conn, $id, $uid);
    }

    if ($action === 'verify_delivery') {
        $entered = trim($_POST['otp'] ?? '');
        if ($entered === $fresh['delivery_otp'] && $fresh['status'] === 'in_transit') {
            $upd=$conn->prepare("UPDATE courier_requests SET status='delivered', delivery_otp_verified=1, payment_status='released' WHERE id=?");
            $upd->bind_param("i",$id); $upd->execute(); $upd->close();

            $note='Parcel delivered. OTP verified. Payment of ₹'.$fresh['reward_amount'].' released to traveler.';
            $trk=$conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'Delivered',?,?)");
            $trk->bind_param("isi",$id,$note,$uid); $trk->execute(); $trk->close();

            $success='Delivery confirmed! ₹'.number_format($fresh['reward_amount'],2).' has been released to your account.';
        } else {
            $error='Incorrect delivery OTP. Please try again.';
        }
        $r = loadCourier($conn, $id, $uid);
    }

    if ($action === 'update_note') {
        $note_text    = trim($_POST['note']         ?? '');
        $status_label = trim($_POST['status_label'] ?? 'Update');
        if ($note_text) {
            $trk=$conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,?,?,?)");
            $trk->bind_param("issi",$id,$status_label,$note_text,$uid);
            $trk->execute(); $trk->close();
            $success='Update posted.';
        } else { $error='Please enter a note.'; }
    }
}

$trk_stmt=$conn->prepare("SELECT tu.*,u.name AS by_name FROM tracking_updates tu JOIN users u ON tu.updated_by=u.id WHERE tu.courier_id=? ORDER BY tu.created_at DESC");
$trk_stmt->bind_param("i",$id); $trk_stmt->execute();
$updates=$trk_stmt->get_result();

include '../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5">
        <a href="/courier/partner/my_deliveries.php" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back</a>
        <div class="flex items-center justify-between mt-2">
            <div>
                <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($r['parcel_name']) ?></h1>
                <p class="text-xs text-slate-400"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></p>
            </div>
            <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-medium">
                <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
            </span>
        </div>
    </div>

    <?php if ($error):   ?><div class="bg-red-50   border border-red-200   text-red-700   text-xs px-4 py-2.5 rounded-lg mb-4"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 text-xs px-4 py-2.5 rounded-lg mb-4"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($r['status'] === 'accepted'): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-bold">1</span>
            <h3 class="font-semibold text-blue-800 text-sm">Verify Pickup OTP</h3>
        </div>
        <p class="text-xs text-blue-600 mb-3">Ask the sender for their pickup OTP and enter it to confirm you've collected the parcel.</p>
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="action" value="verify_pickup">
            <input type="text" name="otp" maxlength="6" required placeholder="Enter 6-digit OTP"
                class="border border-blue-300 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono tracking-widest text-center">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors font-medium">Verify</button>
        </form>
        <p class="text-xs text-blue-400 mt-2">
            Demo only — <button onclick="alert('Pickup OTP: <?= htmlspecialchars($r['pickup_otp']) ?>')" class="underline">reveal OTP</button>
        </p>
    </div>
    <?php endif; ?>

    <?php if (in_array($r['status'], ['picked_up','awaiting_payment']) && $r['payment_status'] !== 'paid'): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-5 h-5 bg-amber-500 text-white rounded-full text-xs flex items-center justify-center font-bold">2</span>
            <h3 class="font-semibold text-amber-800 text-sm">Waiting for Sender Payment</h3>
        </div>
        <p class="text-xs text-amber-700 mb-2">You've picked up the parcel. The sender has been prompted to pay <strong>₹<?= number_format($r['reward_amount'],2) ?></strong> via Razorpay.</p>
        <div class="flex items-center gap-2 text-xs text-amber-600">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Waiting for payment… refresh this page to check.
        </div>
        <a href="" class="mt-2 inline-block text-xs text-amber-600 underline">Refresh page</a>
    </div>
    <?php endif; ?>

    <?php if (in_array($r['status'], ['payment_done']) && $r['payment_status'] === 'paid'): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-5 h-5 bg-green-600 text-white rounded-full text-xs flex items-center justify-center font-bold">2</span>
            <h3 class="font-semibold text-green-800 text-sm">Payment Received — Start Transit</h3>
        </div>
        <p class="text-xs text-green-700 mb-3">
            <svg class="w-3.5 h-3.5 inline mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            Sender has paid <strong>₹<?= number_format($r['reward_amount'],2) ?></strong>. The payment is held securely. Click below to start your journey.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="start_transit">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors font-medium">
                Mark In Transit
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'in_transit'): ?>
    <div class="bg-purple-50 border border-purple-200 rounded-xl p-5 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-5 h-5 bg-purple-600 text-white rounded-full text-xs flex items-center justify-center font-bold">3</span>
            <h3 class="font-semibold text-purple-800 text-sm">Verify Delivery OTP</h3>
        </div>
        <p class="text-xs text-purple-600 mb-3">Ask the recipient for their delivery OTP to complete delivery and receive your payment.</p>
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="action" value="verify_delivery">
            <input type="text" name="otp" maxlength="6" required placeholder="Enter 6-digit OTP"
                class="border border-purple-300 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-purple-300 font-mono tracking-widest text-center">
            <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 transition-colors font-medium">Confirm Delivery</button>
        </form>
        <p class="text-xs text-purple-400 mt-2">
            Demo only — <button onclick="alert('Delivery OTP: <?= htmlspecialchars($r['delivery_otp']) ?>')" class="underline">reveal OTP</button>
        </p>
    </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'delivered'): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-4">
        <div class="flex items-center gap-3">
            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="font-semibold text-green-700">Delivery Completed!</p>
                <p class="text-xs text-green-600 mt-0.5">₹<?= number_format($r['reward_amount'],2) ?> has been released to your account.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit'])): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4">
        <h3 class="font-semibold text-slate-700 text-sm mb-3">Post a Tracking Update</h3>
        <form method="POST" class="space-y-2">
            <input type="hidden" name="action" value="update_note">
            <input type="text" name="status_label" placeholder="Status label (e.g. At checkpoint)"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200">
            <textarea name="note" rows="2" required placeholder="Add a note visible to the sender…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200 resize-none"></textarea>
            <button type="submit" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors">Post Update</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4">
        <h3 class="font-semibold text-slate-700 text-sm mb-3">Parcel & Payment Info</h3>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <div><span class="text-slate-400">Sender:</span> <span class="text-slate-700"><?= htmlspecialchars($r['customer_name']) ?></span></div>
            <div><span class="text-slate-400">Phone:</span> <span class="text-slate-700"><?= htmlspecialchars($r['customer_phone'] ?? '—') ?></span></div>
            <?php if (!empty($r['receiver_name'])): ?>
            <div><span class="text-slate-400">Receiver:</span> <span class="text-slate-700"><?= htmlspecialchars($r['receiver_name']) ?></span></div>
            <div><span class="text-slate-400">Receiver Phone:</span> <span class="text-slate-700"><?= htmlspecialchars($r['receiver_phone'] ?? '—') ?></span></div>
            <?php endif; ?>
            <div><span class="text-slate-400">Pickup:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pickup_address']) ?></span></div>
            <div><span class="text-slate-400">Delivery:</span> <span class="text-slate-700"><?= htmlspecialchars($r['delivery_address']) ?></span></div>
            <div><span class="text-slate-400">Reward:</span> <span class="text-slate-700 font-semibold">₹<?= number_format($r['reward_amount'],2) ?></span></div>
            <div><span class="text-slate-400">Payment:</span>
                <?php
                    $pc=['unpaid'=>'text-slate-400','pending'=>'text-amber-600','paid'=>'text-blue-600','released'=>'text-green-600'];
                    $pc2=$pc[$r['payment_status']] ?? 'text-slate-400';
                ?>
                <span class="<?= $pc2 ?> font-medium"><?= ucfirst($r['payment_status']) ?></span>
            </div>
            <?php if ($r['razorpay_payment_id']): ?>
            <div class="col-span-2"><span class="text-slate-400">Razorpay ID:</span> <span class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($r['razorpay_payment_id']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold text-slate-700 text-sm mb-3">Tracking Log</h3>
        <div class="space-y-3">
            <?php while ($u=$updates->fetch_assoc()): ?>
            <div class="flex gap-2 text-xs">
                <div class="w-2 h-2 bg-slate-300 rounded-full mt-1 shrink-0"></div>
                <div>
                    <span class="font-medium text-slate-600"><?= htmlspecialchars($u['status']) ?></span>
                    <?php if ($u['note']): ?> — <span class="text-slate-400"><?= htmlspecialchars($u['note']) ?></span><?php endif; ?>
                    <div class="text-slate-300 mt-0.5"><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit'])): ?>
<div id="loc-banner" class="fixed bottom-4 right-4 z-50 max-w-xs">
    <div id="loc-active" class="hidden bg-slate-800 text-white text-xs rounded-xl px-4 py-3 shadow-lg flex items-center gap-2">
        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse shrink-0"></span>
        <span>Sharing your location with sender</span>
    </div>
    <div id="loc-error" class="hidden bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl px-4 py-3 shadow-lg flex items-start gap-2">
        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <span id="loc-error-msg">Location access denied. Sender cannot track you.</span>
    </div>
    <div id="loc-asking" class="bg-blue-50 border border-blue-200 text-blue-700 text-xs rounded-xl px-4 py-3 shadow-lg flex items-center gap-2">
        <svg class="w-3.5 h-3.5 shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span>Requesting location permission…</span>
    </div>
</div>

<script>
(function() {
    const COURIER_ID = <?= (int)$r['id'] ?>;
    let watchId = null;

    function showBanner(state, msg) {
        document.getElementById('loc-active').classList.add('hidden');
        document.getElementById('loc-error').classList.add('hidden');
        document.getElementById('loc-asking').classList.add('hidden');
        if (state === 'active') {
            document.getElementById('loc-active').classList.remove('hidden');
        } else if (state === 'error') {
            document.getElementById('loc-error-msg').textContent = msg || 'Location unavailable.';
            document.getElementById('loc-error').classList.remove('hidden');
        } else if (state === 'asking') {
            document.getElementById('loc-asking').classList.remove('hidden');
        }
    }

    function pushLocation(pos) {
        const { latitude: lat, longitude: lng, accuracy } = pos.coords;
        showBanner('active');
        fetch('/courier/location_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `courier_id=${COURIER_ID}&lat=${lat}&lng=${lng}&accuracy=${accuracy}`
        }).catch(() => {}); // silent fail — don't alert traveler
    }

    function onError(err) {
        if (err.code === 1) {
            showBanner('error', 'Location access denied. Allow location in browser settings for sender tracking.');
        } else {
            showBanner('error', 'Location unavailable. Move to an open area.');
        }
    }

    if (!navigator.geolocation) {
        showBanner('error', 'Your browser does not support GPS. Sender cannot track your location.');
        return;
    }

    showBanner('asking');

    watchId = navigator.geolocation.watchPosition(pushLocation, onError, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 10000
    });
    navigator.geolocation.getCurrentPosition(pushLocation, onError, {
        enableHighAccuracy: true,
        timeout: 10000
    });
    window.addEventListener('beforeunload', () => {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    });
})();
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
