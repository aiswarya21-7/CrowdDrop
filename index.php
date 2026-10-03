<?php
require_once 'includes/config.php';
require_once __DIR__ . '/auto_update_requests.php';
$pageTitle = 'CrowdDrop — Community Delivery';
include 'includes/header.php';
?>

<div class="text-center py-16">
    <h1 class="text-4xl font-bold text-slate-800 mb-4">Send parcels through<br>everyday travelers</h1>
 
    <div class="flex justify-center gap-3">
        <a href="/courier/register.php?role=customer" class="bg-slate-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-700 transition-colors">Send a Parcel</a>
        <a href="/courier/register.php?role=partner" class="bg-white text-slate-700 border border-slate-200 px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Earn as Traveler</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
