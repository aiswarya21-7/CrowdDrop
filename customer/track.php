<?php
require_once '../includes/config.php';
requireLogin('customer');
$pageTitle = 'Track Parcel — CrowdDrop';

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: /courier/customer/my_requests.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT cr.*, u.name AS partner_name, u.phone AS partner_phone
    FROM courier_requests cr
    LEFT JOIN users u ON cr.partner_id = u.id
    WHERE cr.id = ? AND cr.customer_id = ?
");
$stmt->bind_param("ii", $id, $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    include '../includes/header.php';
    echo '<div class="max-w-md mx-auto mt-10 bg-red-50 border border-red-200 rounded-xl p-6 text-center">
            <p class="font-semibold text-red-700 mb-1">Access Denied</p>
            <p class="text-xs text-red-500 mb-4">This parcel does not exist or does not belong to your account.</p>
            <a href="/courier/customer/my_requests.php" class="text-xs text-slate-600 underline"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back to My Parcels</a>
          </div>';
    include '../includes/footer.php';
    exit;
}

$r = $result->fetch_assoc();
$stmt->close();

$trk = $conn->prepare("
    SELECT tu.*, u.name AS by_name
    FROM tracking_updates tu
    JOIN users u ON tu.updated_by = u.id
    WHERE tu.courier_id = ?
    ORDER BY tu.created_at ASC
");
$trk->bind_param("i", $id);
$trk->execute();
$updates = $trk->get_result();

include '../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <?php if (!empty($_SESSION['pay_success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 text-xs px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <?= htmlspecialchars($_SESSION['pay_success']) ?>
    </div>
    <?php unset($_SESSION['pay_success']); endif; ?>
    <?php if (!empty($_SESSION['pay_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-xs px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <?= htmlspecialchars($_SESSION['pay_error']) ?>
    </div>
    <?php unset($_SESSION['pay_error']); endif; ?>
    <div class="mb-5">
        <a href="/courier/customer/my_requests.php" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back to My Parcels</a>
        <div class="flex items-center justify-between mt-2">
            <div>
                <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($r['parcel_name']) ?></h1>
                <p class="text-xs text-slate-400"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></p>
            </div>
            <?php
                $statusColors = [
                    'pending'    => 'bg-amber-50 text-amber-700 border-amber-200',
                    'accepted'   => 'bg-blue-50 text-blue-700 border-blue-200',
                    'picked_up'  => 'bg-purple-50 text-purple-700 border-purple-200',
                    'in_transit' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'delivered'  => 'bg-green-50 text-green-700 border-green-200',
                    'cancelled'  => 'bg-red-50 text-red-700 border-red-200',
                ];
                $sc = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
            ?>
            <span class="text-xs px-3 py-1.5 rounded-full font-semibold border <?= $sc ?>">
                <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-xs text-slate-400 mb-1">Traveler</div>
            <div class="font-semibold text-slate-700 text-sm">
                <?= $r['partner_name'] ? htmlspecialchars($r['partner_name']) : '<span class="text-slate-400 font-normal">No traveler yet</span>' ?>
            </div>
            <?php if ($r['partner_phone']): ?>
            <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($r['partner_phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-xs text-slate-400 mb-1">Receiver</div>
            <?php if (!empty($r['receiver_name'])): ?>
            <div class="font-semibold text-slate-700 text-sm"><?= htmlspecialchars($r['receiver_name']) ?></div>
            <?php if ($r['receiver_phone']): ?>
            <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($r['receiver_phone']) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-xs text-slate-400 font-normal">Not specified</div>
            <?php endif; ?>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-xs text-slate-400 mb-1">Reward Offered</div>
            <div class="font-semibold text-slate-700 text-sm">₹<?= number_format($r['reward_amount'], 2) ?></div>
            <?php if ($r['parcel_weight']): ?>
            <div class="text-xs text-slate-400 mt-0.5"><?= $r['parcel_weight'] ?> kg</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (in_array($r['status'], ['picked_up','awaiting_payment']) && $r['payment_status'] !== 'paid'): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="text-sm font-semibold text-amber-800">Payment Required</h3>
        </div>
        <p class="text-xs text-amber-700 mb-3">Your parcel has been picked up by the traveler. Please pay the delivery reward to proceed. The money is held securely and released to the traveler only after successful delivery.</p>
        <a href="/courier/payment_create.php?courier_id=<?= (int)$r['id'] ?>"
           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            Pay ₹<?= number_format($r['reward_amount'], 2) ?> Now
        </a>
    </div>
    <?php endif; ?>

    <?php if ($r['payment_status'] === 'paid' || $r['payment_status'] === 'released'): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="text-sm font-semibold text-green-700">
                Payment <?= $r['payment_status'] === 'released' ? 'Released to Traveler' : 'Confirmed' ?>
            </p>
            <p class="text-xs text-green-600">₹<?= number_format($r['reward_amount'],2) ?> — <?= $r['payment_status'] === 'released' ? 'Delivery complete, traveler paid.' : 'Held securely until delivery.' ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'accepted' && !$r['pickup_otp_verified']): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <h3 class="text-sm font-semibold text-blue-800">Pickup OTP</h3>
        </div>
        <p class="text-xs text-blue-600 mb-3">Your traveler is coming to collect your parcel. Share this OTP with them to confirm handover.</p>
        <button
            onclick="this.classList.add('hidden'); document.getElementById('pickup-otp-val').classList.remove('hidden')"
            class="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
            Reveal Pickup OTP
        </button>
        <div id="pickup-otp-val" class="hidden mt-3">
            <span class="text-3xl font-bold font-mono tracking-widest text-blue-800"><?= htmlspecialchars($r['pickup_otp']) ?></span>
            <p class="text-xs text-blue-400 mt-1">Show this to your traveler only.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'in_transit' && !$r['delivery_otp_verified']): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <h3 class="text-sm font-semibold text-green-800">Delivery OTP</h3>
        </div>
        <p class="text-xs text-green-600 mb-3">Your parcel is on the way! Share this OTP with the traveler when they arrive to complete the delivery.</p>
        <button
            onclick="this.classList.add('hidden'); document.getElementById('delivery-otp-val').classList.remove('hidden')"
            class="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium">
            Reveal Delivery OTP
        </button>
        <div id="delivery-otp-val" class="hidden mt-3">
            <span class="text-3xl font-bold font-mono tracking-widest text-green-800"><?= htmlspecialchars($r['delivery_otp']) ?></span>
            <p class="text-xs text-green-400 mt-1">Share this only with your traveler.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($r['status'] === 'delivered'): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="font-semibold text-green-700 text-sm">Parcel Delivered!</p>
            <p class="text-xs text-green-500">Your parcel was successfully delivered.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit']) && $r['partner_id']): ?>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-slate-700 text-sm">Live Traveler Location</h3>
                <span id="loc-status-badge" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-400">Waiting…</span>
            </div>
            <span id="loc-updated" class="text-xs text-slate-300"></span>
        </div>
        <div id="traveler-map" style="height:300px;width:100%;background:#f1f5f9;"></div>
        <div id="loc-unavailable" class="hidden px-5 py-8 text-center">
            <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="text-xs text-slate-400">Location not available yet. The traveler must have the delivery page open for tracking.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-4">
        <h3 class="font-semibold text-slate-700 text-sm mb-4">Tracking Timeline</h3>
        <?php
        $updates_arr = [];
        while ($u = $updates->fetch_assoc()) $updates_arr[] = $u;
        $total_updates = count($updates_arr);
        ?>
        <?php if ($total_updates === 0): ?>
            <p class="text-xs text-slate-400">No tracking updates yet.</p>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach (array_reverse($updates_arr) as $i => $u): ?>
            <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="w-2.5 h-2.5 <?= $i === 0 ? 'bg-slate-700' : 'bg-slate-300' ?> rounded-full mt-0.5 shrink-0"></div>
                    <?php if ($i < $total_updates - 1): ?>
                    <div class="w-px bg-slate-200 flex-1 mt-1 min-h-[1.5rem]"></div>
                    <?php endif; ?>
                </div>
                <div class="pb-3">
                    <div class="font-medium text-sm text-slate-700"><?= htmlspecialchars($u['status']) ?></div>
                    <?php if ($u['note']): ?>
                    <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($u['note']) ?></div>
                    <?php endif; ?>
                    <div class="text-xs text-slate-300 mt-0.5"><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h3 class="font-semibold text-slate-700 text-sm mb-3">Parcel Details</h3>
        <div class="grid grid-cols-2 gap-3 text-xs">
            <div><span class="text-slate-400">Weight:</span> <span class="text-slate-700"><?= $r['parcel_weight'] ? $r['parcel_weight'].' kg' : '—' ?></span></div>
            <div><span class="text-slate-400">Reward:</span> <span class="text-slate-700">₹<?= number_format($r['reward_amount'], 2) ?></span></div>
            <div><span class="text-slate-400">Pickup from:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pickup_address']) ?></span></div>
            <div><span class="text-slate-400">Deliver to:</span> <span class="text-slate-700"><?= htmlspecialchars($r['delivery_address']) ?></span></div>
            <?php if ($r['parcel_description']): ?>
            <div class="col-span-2"><span class="text-slate-400">Description:</span> <span class="text-slate-700"><?= htmlspecialchars($r['parcel_description']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit']) && $r['partner_id']): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function() {
    const COURIER_ID  = <?= (int)$r['id'] ?>;
    const POLL_MS     = 6000; 
    const TRAVELER    = <?= json_encode($r['partner_name']) ?>;

    let map = null, marker = null, circle = null, initialised = false;

    function initMap(lat, lng) {
        if (initialised) return;
        initialised = true;

        document.getElementById('traveler-map').style.display = 'block';
        document.getElementById('loc-unavailable').classList.add('hidden');

        map = L.map('traveler-map', { zoomControl: true }).setView([lat, lng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        const icon = L.divIcon({
            className: '',
            html: `<div style="
                background:#1e293b;width:36px;height:36px;border-radius:50% 50% 50% 0;
                transform:rotate(-45deg);border:3px solid #fff;
                box-shadow:0 2px 8px rgba(0,0,0,.3);">
                <div style="transform:rotate(45deg);color:#fff;text-align:center;line-height:30px;font-size:14px;">🚶</div>
            </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36],
        });

        marker = L.marker([lat, lng], { icon }).addTo(map);
        marker.bindPopup(`<strong>${TRAVELER}</strong><br>Your traveler is here`).openPopup();
    }

    function updateMarker(lat, lng, accuracy) {
        if (!map) { initMap(lat, lng); return; }
        marker.setLatLng([lat, lng]);

        if (circle) map.removeLayer(circle);
        if (accuracy && accuracy < 1000) {
            circle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#3b82f6', fillColor: '#3b82f620',
                weight: 1, fillOpacity: 0.15
            }).addTo(map);
        }

        map.panTo([lat, lng], { animate: true, duration: 1 });
    }

    function timeAgo(dateStr) {
        const secs = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (secs < 10)  return 'just now';
        if (secs < 60)  return secs + 's ago';
        if (secs < 3600) return Math.floor(secs/60) + 'm ago';
        return Math.floor(secs/3600) + 'h ago';
    }

    function poll() {
        fetch(`/courier/location_update.php?courier_id=${COURIER_ID}`)
            .then(r => r.json())
            .then(data => {
                const badge   = document.getElementById('loc-status-badge');
                const updated = document.getElementById('loc-updated');

                if (!data.found) {
                    document.getElementById('traveler-map').style.display = 'none';
                    document.getElementById('loc-unavailable').classList.remove('hidden');
                    badge.textContent = 'No signal';
                    badge.className = 'text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-400';
                    return;
                }

                document.getElementById('loc-unavailable').classList.add('hidden');
                document.getElementById('traveler-map').style.display = 'block';
                updateMarker(data.lat, data.lng, data.accuracy);
                updated.textContent = 'Updated ' + timeAgo(data.updated);

                if (data.stale) {
                    badge.textContent = 'Signal lost';
                    badge.className = 'text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-600';
                } else {
                    badge.textContent = 'Live';
                    badge.className = 'text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-600 flex items-center gap-1';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 bg-green-500 rounded-full inline-block animate-pulse"></span> Live';
                }
            })
            .catch(() => {});
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

