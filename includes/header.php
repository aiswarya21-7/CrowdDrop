<?php
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'CrowdDrop' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .nav-link { color: #475569; font-size: 0.875rem; font-weight: 500; transition: color 0.15s; }
        .nav-link:hover { color: #0f172a; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
<nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">
            <a href="/courier/index.php" class="flex items-center gap-2">
                <div class="w-7 h-7 bg-slate-800 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <span class="font-semibold text-slate-800 text-sm">CrowdDrop</span>
            </a>
            <div class="flex items-center gap-5">
                <?php if (isLoggedIn()): ?>
                    <?php if ($role === 'customer'): ?>
                        <a href="/courier/customer/dashboard.php" class="nav-link">Dashboard</a>
                        <a href="/courier/customer/new_request.php" class="nav-link">New Request</a>
                        <a href="/courier/customer/my_requests.php" class="nav-link">My Parcels</a>
                    <?php elseif ($role === 'partner'): ?>
                        <a href="/courier/partner/dashboard.php" class="nav-link">Dashboard</a>
                        <a href="/courier/partner/available.php" class="nav-link">Find Parcels</a>
                        <a href="/courier/partner/my_deliveries.php" class="nav-link">My Trips</a>
                        <a href="/courier/partner/report.php" class="nav-link">Report</a>
                        <a href="/courier/partner/payments.php" class="nav-link">Payments</a>
                    <?php elseif ($role === 'admin'): ?>
                        <a href="/courier/admin/dashboard.php" class="nav-link">Dashboard</a>
                        <a href="/courier/admin/users.php" class="nav-link">Users</a>
                        <a href="/courier/admin/couriers.php" class="nav-link">Couriers</a>
                        <a href="/courier/admin/report.php" class="nav-link">Report</a>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 pl-3 border-l border-slate-200">
                        <span class="text-xs text-slate-500"><?= htmlspecialchars($name) ?></span>
                        <a href="/courier/logout.php" class="text-xs text-red-500 hover:text-red-700 font-medium">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="/courier/login.php" class="nav-link">Login</a>
                    <a href="/courier/register.php" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors font-medium">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
