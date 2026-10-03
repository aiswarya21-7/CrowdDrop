<?php
require_once '../includes/config.php';
requireLogin('admin');
$pageTitle = 'Track Delivery — Admin';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: /courier/admin/couriers.php"); exit; }

$stmt = $conn->prepare("
    SELECT cr.*,
           uc.name AS cname, uc.email AS cemail, uc.phone AS cphone,
           up.name AS pname, up.email AS pemail, up.phone AS pphone
    FROM courier_requests cr
    JOIN users uc ON cr.customer_id = uc.id
    LEFT JOIN users up ON cr.partner_id = up.id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    include '../includes/header.php';
    echo '<div class="max-w-md mx-auto mt-10 bg-red-50 border border-red-200 rounded-xl p-6 text-center">
            <p class="font-semibold text-red-700 mb-1">Not Found</p>
            <p class="text-xs text-red-500 mb-4">This courier request does not exist.</p>
            <a href="/courier/admin/couriers.php" class="text-xs text-slate-600 underline">← Back to Courier Requests</a>
          </div>';
    include '../includes/footer.php';
    exit;
}
$r = $res->fetch_assoc();
$stmt->close();

$trk = $conn->prepare("
    SELECT tu.*, u.name AS by_name, u.role AS by_role
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

<div class="max-w-3xl mx-auto">
    <div class="mb-5">
        <a href="/courier/admin/couriers.php" class="text-xs text-slate-400 hover:text-slate-600"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back to Courier Requests</a>
        <div class="flex items-center justify-between mt-2">
            <div>
                <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($r['parcel_name']) ?></h1>
                <p class="text-xs text-slate-400"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs bg-slate-100 text-slate-700 px-3 py-1 rounded-full font-medium">
                    <?= ucfirst(str_replace('_',' ', $r['status'])) ?>
                </span>
                <?php
                    $pc = ['unpaid'=>'bg-slate-100 text-slate-500','pending'=>'bg-amber-100 text-amber-700',
                           'paid'=>'bg-blue-100 text-blue-700','released'=>'bg-green-100 text-green-700'];
                    $pcls = $pc[$r['payment_status']] ?? 'bg-slate-100 text-slate-500';
                ?>
                <span class="text-xs <?= $pcls ?> px-3 py-1 rounded-full font-medium flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <?= ucfirst($r['payment_status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-5">
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Sender</h3>
            <div class="space-y-1 text-xs">
                <div><span class="text-slate-400">Name:</span> <span class="text-slate-700"><?= htmlspecialchars($r['cname']) ?></span></div>
                <div><span class="text-slate-400">Email:</span> <span class="text-slate-700"><?= htmlspecialchars($r['cemail']) ?></span></div>
                <div><span class="text-slate-400">Phone:</span> <span class="text-slate-700"><?= htmlspecialchars($r['cphone'] ?? '—') ?></span></div>
                <div><span class="text-slate-400">Pickup at:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pickup_address']) ?></span></div>
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Traveler</h3>
            <?php if ($r['pname']): ?>
            <div class="space-y-1 text-xs">
                <div><span class="text-slate-400">Name:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pname']) ?></span></div>
                <div><span class="text-slate-400">Email:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pemail']) ?></span></div>
                <div><span class="text-slate-400">Phone:</span> <span class="text-slate-700"><?= htmlspecialchars($r['pphone'] ?? '—') ?></span></div>
                <div><span class="text-slate-400">Deliver to:</span> <span class="text-slate-700"><?= htmlspecialchars($r['delivery_address']) ?></span></div>
            </div>
            <?php else: ?><p class="text-xs text-slate-400">No traveler yet</p><?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Parcel & Payment Details</h3>
        <div class="grid grid-cols-3 gap-3 text-xs">
            <div><span class="text-slate-400">Weight:</span> <span class="text-slate-700"><?= $r['parcel_weight'] ? $r['parcel_weight'].' kg' : '—' ?></span></div>
            <div><span class="text-slate-400">Reward:</span> <span class="text-slate-700 font-semibold">₹<?= number_format($r['reward_amount'],2) ?></span></div>
            <div><span class="text-slate-400">Payment:</span> <span class="text-slate-700"><?= ucfirst($r['payment_status']) ?></span></div>
            <div><span class="text-slate-400">Admin Verified:</span> <span class="<?= $r['admin_verified']?'text-green-500':'text-amber-500' ?>"><?= $r['admin_verified']?'Yes':'Pending' ?></span></div>
            <div><span class="text-slate-400">Pickup OTP:</span> <span class="font-mono text-slate-700"><?= $r['pickup_otp'] ?> <?= $r['pickup_otp_verified'] ? '<svg class="w-3.5 h-3.5 text-green-500 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : '' ?></span></div>
            <div><span class="text-slate-400">Delivery OTP:</span> <span class="font-mono text-slate-700"><?= $r['delivery_otp'] ?> <?= $r['delivery_otp_verified'] ? '<svg class="w-3.5 h-3.5 text-green-500 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : '' ?></span></div>
            <?php if ($r['razorpay_order_id']): ?>
            <div class="col-span-2"><span class="text-slate-400">Razorpay Order:</span> <span class="font-mono text-slate-600"><?= htmlspecialchars($r['razorpay_order_id']) ?></span></div>
            <?php endif; ?>
            <?php if ($r['razorpay_payment_id']): ?>
            <div class="col-span-2"><span class="text-slate-400">Payment ID:</span> <span class="font-mono text-slate-600"><?= htmlspecialchars($r['razorpay_payment_id']) ?></span></div>
            <?php endif; ?>
        </div>
        <?php if ($r['parcel_description']): ?>
        <div class="mt-3 text-xs"><span class="text-slate-400">Description:</span> <span class="text-slate-600"><?= htmlspecialchars($r['parcel_description']) ?></span></div>
        <?php endif; ?>
    </div>

    <?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit']) && $r['partner_id']): ?>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-slate-700 text-sm">Live Traveler Location</h3>
                <span id="loc-status-badge" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-400">Waiting…</span>
            </div>
            <span id="loc-updated" class="text-xs text-slate-300"></span>
        </div>
        <div id="traveler-map" style="height:320px;width:100%;background:#f1f5f9;"></div>
        <div id="loc-unavailable" class="hidden px-5 py-8 text-center">
            <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="text-xs text-slate-400">Location not available yet. Traveler must have the delivery page open.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h3 class="font-semibold text-slate-700 text-sm mb-4">Full Tracking Timeline</h3>
        <?php
        $all = [];
        while ($u = $updates->fetch_assoc()) $all[] = $u;
        $total = count($all);
        ?>
        <?php if (!$total): ?>
        <p class="text-xs text-slate-400">No updates yet.</p>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach (array_reverse($all) as $i => $u): ?>
            <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <?php
                        $dot = ['admin'=>'bg-amber-400','partner'=>'bg-blue-400','customer'=>'bg-slate-400'];
                        $dc  = $dot[$u['by_role']] ?? 'bg-slate-300';
                    ?>
                    <div class="w-2.5 h-2.5 <?= $dc ?> rounded-full mt-0.5 shrink-0"></div>
                    <?php if ($i < $total - 1): ?><div class="w-px bg-slate-200 flex-1 mt-1 min-h-[1.5rem]"></div><?php endif; ?>
                </div>
                <div class="pb-3">
                    <div class="font-medium text-sm text-slate-700"><?= htmlspecialchars($u['status']) ?></div>
                    <?php if ($u['note']): ?><div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($u['note']) ?></div><?php endif; ?>
                    <div class="text-xs text-slate-300 mt-0.5"><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?> · <?= htmlspecialchars($u['by_name']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($r['status'], ['accepted','picked_up','awaiting_payment','payment_done','in_transit']) && $r['partner_id']): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function() {
    const COURIER_ID = <?= (int)$r['id'] ?>;
    const TRAVELER   = <?= json_encode($r['pname'] ?? 'Traveler') ?>;
    const POLL_MS    = 6000;
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
            html: `<div style="background:#1e293b;width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);"><div style="transform:rotate(45deg);color:#fff;text-align:center;line-height:30px;font-size:14px;">🚶</div></div>`,
            iconSize: [36, 36], iconAnchor: [18, 36]
        });
        marker = L.marker([lat, lng], { icon }).addTo(map);
        marker.bindPopup(`<strong>${TRAVELER}</strong><br>Current traveler location`).openPopup();
    }

    function updateMarker(lat, lng, accuracy) {
        if (!map) { initMap(lat, lng); return; }
        marker.setLatLng([lat, lng]);
        if (circle) map.removeLayer(circle);
        if (accuracy && accuracy < 1000) {
            circle = L.circle([lat, lng], {
                radius: accuracy, color: '#3b82f6',
                fillColor: '#3b82f620', weight: 1, fillOpacity: 0.15
            }).addTo(map);
        }
        map.panTo([lat, lng], { animate: true, duration: 1 });
    }

    function timeAgo(dateStr) {
        const secs = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (secs < 10)   return 'just now';
        if (secs < 60)   return secs + 's ago';
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
                    badge.innerHTML = '<span style="width:6px;height:6px;background:#22c55e;border-radius:50%;display:inline-block;animation:pulse 1s infinite;margin-right:4px;"></span>Live';
                    badge.className = 'text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-600 flex items-center';
                }
            }).catch(() => {});
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
