<?php
require_once '../includes/config.php';
require_once '../includes/geo.php';
requireLogin('customer');
$pageTitle = 'New Parcel Request — CrowdDrop';

$uid    = (int)$_SESSION['user_id'];
$errors = [];

$sender_stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$sender_stmt->bind_param("i", $uid); $sender_stmt->execute();
$sender_name = $sender_stmt->get_result()->fetch_assoc()['name'] ?? 'Sender';
$sender_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parcel_name    = trim($_POST['parcel_name']        ?? '');
    $parcel_weight  = floatval($_POST['parcel_weight']   ?? 0);
    $parcel_desc    = trim($_POST['parcel_description']  ?? '');
    $from           = trim($_POST['from_location']       ?? '');
    $to             = trim($_POST['to_location']         ?? '');
    $from_lat       = ($_POST['from_lat'] ?? '') !== '' ? floatval($_POST['from_lat']) : null;
    $from_lng       = ($_POST['from_lng'] ?? '') !== '' ? floatval($_POST['from_lng']) : null;
    $to_lat         = ($_POST['to_lat']   ?? '') !== '' ? floatval($_POST['to_lat'])   : null;
    $to_lng         = ($_POST['to_lng']   ?? '') !== '' ? floatval($_POST['to_lng'])   : null;
    $pickup_addr    = trim($_POST['pickup_address']      ?? '');
    $delivery_addr  = trim($_POST['delivery_address']    ?? '');
    $reward         = floatval($_POST['reward_amount']   ?? 0);
    $receiver_name  = trim($_POST['receiver_name']       ?? '');
    $receiver_phone = preg_replace('/\D/', '', trim($_POST['receiver_phone'] ?? ''));
    $receiver_email = trim($_POST['receiver_email']      ?? '');

    if (!$parcel_name)   $errors[] = 'Parcel name is required.';
    if (!$from || !$to)  $errors[] = 'Pickup and delivery cities are required.';
    if (!$pickup_addr)   $errors[] = 'Full pickup address is required.';
    if (!$delivery_addr) $errors[] = 'Full delivery address is required.';
    if ($reward <= 0)    $errors[] = 'Reward amount must be greater than 0.';
    if ($from_lat === null || $to_lat === null)
        $errors[] = 'Please select both locations from the dropdown to enable traveler matching.';

    if (!$receiver_name)  $errors[] = 'Receiver name is required.';
    if (!preg_match('/^\d{10}$/', $receiver_phone))
        $errors[] = 'Receiver phone must be exactly 10 digits.';
    if (!$receiver_email || !filter_var($receiver_email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid receiver email address is required (delivery OTP will be sent here).';

    if (empty($errors)) {
        $pickup_otp   = generateOTP();
        $delivery_otp = generateOTP();
        $ins = $conn->prepare(
            "INSERT INTO courier_requests
                (customer_id, parcel_name, parcel_weight, parcel_description,
                 from_location, to_location, from_lat, from_lng, to_lat, to_lng,
                 pickup_address, delivery_address,
                 receiver_name, receiver_phone, receiver_email,
                 reward_amount, pickup_otp, delivery_otp)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("isdsssddddsssssdss",
            $uid, $parcel_name, $parcel_weight, $parcel_desc,
            $from, $to, $from_lat, $from_lng, $to_lat, $to_lng,
            $pickup_addr, $delivery_addr,
            $receiver_name, $receiver_phone, $receiver_email,
            $reward, $pickup_otp, $delivery_otp
        );

        if ($ins->execute()) {
            $new_id = $conn->insert_id;
            $ins->close();

            $trk_note = "Parcel request submitted by sender.";
            $trk = $conn->prepare("INSERT INTO tracking_updates (courier_id,status,note,updated_by) VALUES (?,'Request Created',?,?)");
            $trk->bind_param("isi", $new_id, $trk_note, $uid);
            $trk->execute(); $trk->close();

            sendDeliveryOTP($receiver_email, $receiver_name, $delivery_otp, $parcel_name, $sender_name);

            header("Location: /courier/customer/my_requests.php?created=1");
            exit;
        } else {
            $errors[] = 'Failed to submit request. Please try again.';
            $ins->close();
        }
    }
}

include '../includes/header.php';
include '../includes/location_picker.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-800">New Parcel Request</h1>
        <p class="text-xs text-slate-400 mt-0.5">Fill in parcel details, receiver info and locations</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 space-y-1">
        <?php foreach ($errors as $e): ?>
        <p class="text-xs text-red-700 flex items-start gap-1.5">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <?= htmlspecialchars($e) ?>
        </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">

        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Parcel Details</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Parcel Name <span class="text-red-400">*</span></label>
                    <input type="text" name="parcel_name" required
                        value="<?= htmlspecialchars($_POST['parcel_name'] ?? '') ?>"
                        placeholder="e.g. Documents, Gift box"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Weight (kg) <span class="text-red-400">*</span></label>
                    <input type="number" name="parcel_weight" id="parcel_weight"
                        value="<?= htmlspecialchars($_POST['parcel_weight'] ?? '') ?>"
                        step="0.01" min="0.01" required placeholder="e.g. 2.5"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition"
                        oninput="calcReward()">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">
                        Reward (₹) <span class="text-red-400">*</span>
                        <span class="text-slate-400 font-normal">— auto-calculated @ ₹<?= RATE_PER_KG ?>/kg</span>
                    </label>
                    <div class="relative">
                        <input type="number" name="reward_amount" id="reward_amount"
                            value="<?= htmlspecialchars($_POST['reward_amount'] ?? '') ?>"
                            step="1" min="<?= MIN_REWARD ?>" required placeholder="Enter weight to auto-fill"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition pr-16">
                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none">₹/auto</span>
                    </div>
                    <p id="reward-hint" class="text-xs text-slate-400 mt-1">Min ₹<?= MIN_REWARD ?>. Enter weight above to auto-calculate.</p>
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Description</label>
                    <textarea name="parcel_description" rows="2"
                        placeholder="Brief description of contents (optional)"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition resize-none"><?= htmlspecialchars($_POST['parcel_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Receiver Details</h3>
            <p class="text-xs text-slate-400 mb-4">The delivery OTP will be emailed to the receiver so they can hand it to the traveler on arrival.</p>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Receiver Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="receiver_name" required
                        value="<?= htmlspecialchars($_POST['receiver_name'] ?? '') ?>"
                        placeholder="Name of the person receiving the parcel"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Receiver Phone <span class="text-red-400">*</span></label>
                    <input type="text" name="receiver_phone" id="recv_phone" required maxlength="10"
                        value="<?= htmlspecialchars($_POST['receiver_phone'] ?? '') ?>"
                        placeholder="10-digit mobile"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition"
                        oninput="this.value=this.value.replace(/\D/g,'').slice(0,10); validateRecvPhone(this)">
                    <p id="recv-phone-hint" class="text-xs mt-1 hidden"></p>
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">
                        Receiver Email <span class="text-red-400">*</span>
                        <span class="text-slate-400 font-normal">— OTP sent here</span>
                    </label>
                    <input type="email" name="receiver_email" required
                        value="<?= htmlspecialchars($_POST['receiver_email'] ?? '') ?>"
                        placeholder="receiver@example.com"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                </div>
            </div>
            <div class="mt-3 flex items-start gap-2 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                <svg class="w-3.5 h-3.5 text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <p class="text-xs text-blue-700">A 6-digit delivery OTP will be sent to the receiver's email when the request is created. The traveler will ask for this OTP to confirm delivery.</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Route &amp; Addresses</h3>
            <p class="text-xs text-slate-400 mb-4">Type a city or area and <strong class="text-slate-600">select from the dropdown</strong> — this lets us match you with travelers within 10 km of your locations.</p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Pickup City / Area <span class="text-red-400">*</span></label>
                    <div class="loc-wrap">
                        <input type="text" name="from_location" id="from_location" required
                            data-loc data-lat="from_lat" data-lng="from_lng"
                            value="<?= htmlspecialchars($_POST['from_location'] ?? '') ?>"
                            placeholder="Type and select…"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                        <span class="loc-verified hidden">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Location pinned
                        </span>
                    </div>
                    <input type="hidden" name="from_lat" id="from_lat">
                    <input type="hidden" name="from_lng" id="from_lng">
                </div>

                <div>
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Delivery City / Area <span class="text-red-400">*</span></label>
                    <div class="loc-wrap">
                        <input type="text" name="to_location" id="to_location" required
                            data-loc data-lat="to_lat" data-lng="to_lng"
                            value="<?= htmlspecialchars($_POST['to_location'] ?? '') ?>"
                            placeholder="Type and select…"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                        <span class="loc-verified hidden">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Location pinned
                        </span>
                    </div>
                    <input type="hidden" name="to_lat" id="to_lat">
                    <input type="hidden" name="to_lng" id="to_lng">
                </div>

                <div class="col-span-2">
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Full Pickup Address <span class="text-red-400">*</span></label>
                    <input type="text" name="pickup_address" required
                        value="<?= htmlspecialchars($_POST['pickup_address'] ?? '') ?>"
                        placeholder="House no, street, landmark"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                </div>

                <div class="col-span-2">
                    <label class="text-xs text-slate-500 font-medium block mb-1.5">Full Delivery Address <span class="text-red-400">*</span></label>
                    <input type="text" name="delivery_address" required
                        value="<?= htmlspecialchars($_POST['delivery_address'] ?? '') ?>"
                        placeholder="House no, street, landmark"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 transition">
                </div>
            </div>

            <div id="route-info" class="hidden mt-3 p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-700"></div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="/courier/customer/dashboard.php" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">Cancel</a>
            <button type="submit" class="px-5 py-2 text-sm bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition-colors font-medium">Submit Request</button>
        </div>
    </form>
</div>

<script>
const RATE_PER_KG = <?= RATE_PER_KG ?>;
const MIN_REWARD  = <?= MIN_REWARD ?>;

function calcReward() {
    const w   = parseFloat(document.getElementById('parcel_weight').value);
    const rwEl = document.getElementById('reward_amount');
    const hint = document.getElementById('reward-hint');
    if (isNaN(w) || w <= 0) {
        hint.textContent = 'Min ₹' + MIN_REWARD + '. Enter weight above to auto-calculate.';
        hint.className = 'text-xs text-slate-400 mt-1';
        return;
    }
    const calculated = Math.max(Math.round(w * RATE_PER_KG), MIN_REWARD);
    rwEl.value = calculated;
    hint.textContent = '₹' + RATE_PER_KG + '/kg × ' + w.toFixed(2) + ' kg = ₹' + calculated + ' (minimum ₹' + MIN_REWARD + ')';
    hint.className = 'text-xs text-green-600 mt-1';
}

function validateRecvPhone(el) {
    const hint = document.getElementById('recv-phone-hint');
    if (!el.value) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (el.value.length === 10) {
        hint.textContent = '✓ Valid'; hint.className = 'text-xs mt-1 text-green-600';
    } else {
        hint.textContent = el.value.length + '/10 digits'; hint.className = 'text-xs mt-1 text-amber-500';
    }
}

function haversineKm(lat1,lng1,lat2,lng2){
    const R=6371,r=Math.PI/180;
    const dL=(lat2-lat1)*r,dG=(lng2-lng1)*r;
    const a=Math.sin(dL/2)**2+Math.cos(lat1*r)*Math.cos(lat2*r)*Math.sin(dG/2)**2;
    return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}
function updateRouteInfo(){
    const fLat=parseFloat(document.getElementById('from_lat').value);
    const fLng=parseFloat(document.getElementById('from_lng').value);
    const tLat=parseFloat(document.getElementById('to_lat').value);
    const tLng=parseFloat(document.getElementById('to_lng').value);
    const box=document.getElementById('route-info');
    if(isNaN(fLat)||isNaN(tLat)){box.classList.add('hidden');return;}
    const d=haversineKm(fLat,fLng,tLat,tLng);
    box.innerHTML='<strong>Route distance:</strong> ~'+d.toFixed(1)+' km — travelers whose route passes within 10 km of each end will see your request.';
    box.classList.remove('hidden');
}
setInterval(updateRouteInfo, 600);

calcReward();
</script>

<?php include '../includes/footer.php'; ?>
