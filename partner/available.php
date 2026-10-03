<?php
require_once '../includes/config.php';
require_once '../includes/geo.php';
requireLogin('partner');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Available Parcels — CrowdDrop';

$uid     = (int)$_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_route'])) {
    $from     = trim($_POST['from_location'] ?? '');
    $to       = trim($_POST['to_location']   ?? '');
    $date     = trim($_POST['travel_date']   ?? '');
    $from_lat = (isset($_POST['from_lat']) && $_POST['from_lat'] !== '') ? floatval($_POST['from_lat']) : null;
    $from_lng = (isset($_POST['from_lng']) && $_POST['from_lng'] !== '') ? floatval($_POST['from_lng']) : null;
    $to_lat   = (isset($_POST['to_lat'])   && $_POST['to_lat']   !== '') ? floatval($_POST['to_lat'])   : null;
    $to_lng   = (isset($_POST['to_lng'])   && $_POST['to_lng']   !== '') ? floatval($_POST['to_lng'])   : null;

    if (!$from || !$to || !$date) {
        $error = 'Please fill all route fields.';
    } elseif ($from_lat === null || $to_lat === null) {
        $error = 'Please select From and To locations from the dropdown so we can match you with nearby requests.';
    } else {
        $ins = $conn->prepare(
            "INSERT INTO partner_routes (partner_id, from_location, to_location, from_lat, from_lng, to_lat, to_lng, travel_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("issdddds", $uid, $from, $to, $from_lat, $from_lng, $to_lat, $to_lng, $date);
        $ins->execute();
        $ins->close();
        $success = 'Route saved! Matching requests within 10 km are shown below.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_id'])) {
    $accept_id = (int)$_POST['accept_id'];
    $chk = $conn->prepare("SELECT id FROM courier_requests WHERE id=? AND status='pending' AND admin_verified=1 AND partner_id IS NULL");
    $chk->bind_param("i", $accept_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $upd = $conn->prepare("UPDATE courier_requests SET partner_id=?, status='accepted' WHERE id=?");
        $upd->bind_param("ii", $uid, $accept_id);
        $upd->execute(); $upd->close();
        $note = 'Traveler accepted the request.';
        $trk  = $conn->prepare("INSERT INTO tracking_updates (courier_id, status, note, updated_by) VALUES (?, 'Accepted', ?, ?)");
        $trk->bind_param("isi", $accept_id, $note, $uid);
        $trk->execute(); $trk->close();
        $success = 'Request accepted! Go to My Deliveries to manage pickup.';
    } else {
        $error = 'This request is no longer available.';
    }
    $chk->close();
}

$all_stmt = $conn->prepare(
    "SELECT cr.*, u.name AS customer_name
     FROM courier_requests cr
     JOIN users u ON cr.customer_id = u.id
     WHERE cr.status = 'pending' AND cr.admin_verified = 1 AND cr.partner_id IS NULL
     ORDER BY cr.created_at DESC"
);
$all_stmt->execute();
$all_requests = $all_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_stmt->close();

$rt_stmt = $conn->prepare("SELECT * FROM partner_routes WHERE partner_id=? ORDER BY travel_date DESC LIMIT 10");
$rt_stmt->bind_param("i", $uid);
$rt_stmt->execute();
$my_routes = $rt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rt_stmt->close();
$active_route = null;
$active_route_id = (int)($_GET['route_id'] ?? 0);

foreach ($my_routes as $rt) {
    if ($rt['from_lat'] !== null) {
        if ($active_route === null) $active_route = $rt;  
        if ((int)$rt['id'] === $active_route_id) { $active_route = $rt; break; }
    }
}

$RADIUS_KM = 10;
$matched   = [];
$unmatched = []; 

foreach ($all_requests as $req) {
    if ($active_route && $active_route['from_lat'] !== null && $req['from_lat'] !== null) {
        $origin_ok  = withinRadius(
            (float)$active_route['from_lat'], (float)$active_route['from_lng'],
            (float)$req['from_lat'],          (float)$req['from_lng'],
            $RADIUS_KM
        );
        $dest_ok    = withinRadius(
            (float)$active_route['to_lat'],   (float)$active_route['to_lng'],
            (float)$req['to_lat'],            (float)$req['to_lng'],
            $RADIUS_KM
        );
        $date_ok = (date('Y-m-d', strtotime($req['created_at'])) <= $active_route['travel_date']);
        if ($origin_ok && $dest_ok && $date_ok) {
            $req['_dist_from'] = haversine(
                (float)$active_route['from_lat'], (float)$active_route['from_lng'],
                (float)$req['from_lat'],          (float)$req['from_lng']
            );
            $req['_dist_to'] = haversine(
                (float)$active_route['to_lat'],   (float)$active_route['to_lng'],
                (float)$req['to_lat'],            (float)$req['to_lng']
            );
            $matched[] = $req;
        } else {
            $unmatched[] = $req;
        }
    } else {
        $matched[] = $req;
    }
}

include '../includes/header.php';
include '../includes/location_picker.php';
?>

<div class="mb-5">
    <h1 class="text-xl font-bold text-slate-800">Available Parcels</h1>
    <p class="text-xs text-slate-400 mt-0.5">Add your travel route to see matching requests within 10 km</p>
</div>

<?php if ($error):   ?><div class="bg-red-50   border border-red-200   text-red-700   text-xs px-3 py-2 rounded-lg mb-4"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2 rounded-lg mb-4"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="grid grid-cols-3 gap-5">

    <div class="col-span-2 space-y-4">

        <?php if ($active_route && $active_route['from_lat'] !== null): ?>
        <div class="bg-slate-800 text-white rounded-xl px-4 py-3 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-slate-300 mb-0.5">Filtering for your route</div>
                <div class="text-sm font-medium"><?= htmlspecialchars($active_route['from_location']) ?> → <?= htmlspecialchars($active_route['to_location']) ?></div>
                <div class="text-xs text-slate-400 mt-0.5"><?= date('d M Y', strtotime($active_route['travel_date'])) ?> · showing requests within <?= $RADIUS_KM ?> km of each end</div>
            </div>
            <div class="text-2xl font-bold text-white"><?= count($matched) ?></div>
        </div>
        <?php else: ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-700">
            <strong>Add your travel route</strong> on the right to see requests near your path. Without a route, all pending requests are shown.
        </div>
        <?php endif; ?>

        <?php if (empty($matched)): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-8 text-center">
            <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </div>
            <p class="text-sm text-slate-500 font-medium">No matching requests found</p>
            <p class="text-xs text-slate-400 mt-1">No pending parcels are within <?= $RADIUS_KM ?> km of both ends of your route.</p>
        </div>
        <?php else: ?>
        <?php foreach ($matched as $r): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-4 hover:border-slate-300 transition-colors">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <div class="font-semibold text-slate-700"><?= htmlspecialchars($r['parcel_name']) ?></div>
                    <div class="text-xs text-slate-400 mt-0.5">by <?= htmlspecialchars($r['customer_name']) ?></div>
                </div>
                <div class="text-right shrink-0 ml-3">
                    <div class="text-lg font-bold text-green-600">₹<?= number_format($r['reward_amount'], 0) ?></div>
                    <div class="text-xs text-slate-400">reward</div>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs mb-2 flex-wrap">
                <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1.7 12.7A2 2 0 008.68 22h6.64a2 2 0 001.98-1.3L19 8"/></svg>
                    <?= htmlspecialchars($r['from_location']) ?>
                </span>
                <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <?= htmlspecialchars($r['to_location']) ?>
                </span>
            </div>

            <?php if (isset($r['_dist_from'])): ?>
            <div class="flex gap-3 text-xs text-slate-400 mb-2">
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Pickup <?= round($r['_dist_from'], 1) ?> km from your origin
                </span>
                <span class="text-slate-200">|</span>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Delivery <?= round($r['_dist_to'], 1) ?> km from your destination
                </span>
            </div>
            <?php endif; ?>

            <?php if ($r['parcel_description']): ?>
            <p class="text-xs text-slate-400 mb-2"><?= htmlspecialchars(substr($r['parcel_description'], 0, 120)) ?></p>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-3">
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <?php if ($r['parcel_weight']): ?><span><?= $r['parcel_weight'] ?> kg</span><?php endif; ?>
                    <span><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                </div>
                <form method="POST">
                    <input type="hidden" name="accept_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" onclick="return confirm('Accept this delivery?')"
                        class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors font-medium">
                        Accept Delivery
                    </button>
                </form>
            </div>

            <?php if ($r['receiver_name'] || $r['receiver_phone'] || $r['receiver_email']): ?>
            <div class="mt-2 border-t border-slate-100 pt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
                <?php if ($r['receiver_name']): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?= htmlspecialchars($r['receiver_name']) ?>
                </span>
                <?php endif; ?>
                <?php if ($r['receiver_phone']): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?= htmlspecialchars($r['receiver_phone']) ?>
                </span>
                <?php endif; ?>
                <?php if ($r['receiver_email']): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <?= htmlspecialchars($r['receiver_email']) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($active_route && !empty($unmatched)): ?>
        <details class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <summary class="px-4 py-3 text-xs text-slate-400 cursor-pointer hover:bg-slate-50">
                <?= count($unmatched) ?> request(s) outside your 10 km radius — click to show
            </summary>
            <div class="divide-y divide-slate-100">
            <?php foreach ($unmatched as $r): ?>
            <div class="px-4 py-3 opacity-60">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-slate-600 font-medium"><?= htmlspecialchars($r['parcel_name']) ?></span>
                        <span class="text-xs text-slate-400 ml-2"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></span>
                    </div>
                    <span class="text-xs text-slate-400">₹<?= number_format($r['reward_amount'], 0) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>

    </div>

    <div class="space-y-4">

        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="font-semibold text-slate-700 text-sm mb-1">Add Travel Route</h3>
            <p class="text-xs text-slate-400 mb-3">Select locations from the dropdown to enable proximity matching.</p>

            <form method="POST" class="space-y-2.5">
                <div>
                    <label class="text-xs text-slate-500 block mb-1">From <span class="text-red-400">*</span></label>
                    <div class="loc-wrap">
                        <input type="text" name="from_location" data-loc data-lat="r_from_lat" data-lng="r_from_lng"
                            required placeholder="Origin city/area"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <span class="loc-verified hidden">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Pinned
                        </span>
                    </div>
                    <input type="hidden" name="from_lat" id="r_from_lat">
                    <input type="hidden" name="from_lng" id="r_from_lng">
                </div>

                <div>
                    <label class="text-xs text-slate-500 block mb-1">To <span class="text-red-400">*</span></label>
                    <div class="loc-wrap">
                        <input type="text" name="to_location" data-loc data-lat="r_to_lat" data-lng="r_to_lng"
                            required placeholder="Destination city/area"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <span class="loc-verified hidden">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Pinned
                        </span>
                    </div>
                    <input type="hidden" name="to_lat" id="r_to_lat">
                    <input type="hidden" name="to_lng" id="r_to_lng">
                </div>

                <div>
                    <label class="text-xs text-slate-500 block mb-1">Travel Date <span class="text-red-400">*</span></label>
                    <input type="date" name="travel_date" required min="<?= date('Y-m-d') ?>"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>

                <button type="submit" name="save_route"
                    class="w-full bg-slate-800 text-white py-2 rounded-lg text-xs font-medium hover:bg-slate-700 transition-colors">
                    Save Route &amp; Find Matches
                </button>
            </form>
        </div>

        <?php if (!empty($my_routes)): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="font-semibold text-slate-700 text-sm mb-3">My Saved Routes</h3>
            <div class="space-y-2">
            <?php foreach ($my_routes as $rt): ?>
            <a href="?route_id=<?= (int)$rt['id'] ?>"
               class="block p-2.5 rounded-lg border transition-colors <?= (int)$rt['id'] === (int)($active_route['id'] ?? 0) ? 'border-slate-700 bg-slate-50' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50' ?>">
                <div class="text-xs font-medium text-slate-700">
                    <?= htmlspecialchars($rt['from_location']) ?> → <?= htmlspecialchars($rt['to_location']) ?>
                </div>
                <div class="text-xs text-slate-400 mt-0.5"><?= date('d M Y', strtotime($rt['travel_date'])) ?>
                    <?php if ($rt['from_lat']): ?>
                    <span class="ml-1 flex items-center gap-0.5 text-green-500">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg> geocoded
                    </span>
                    <?php else: ?>
                    <span class="ml-1 flex items-center gap-0.5 text-amber-400">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg> no coords
                    </span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
