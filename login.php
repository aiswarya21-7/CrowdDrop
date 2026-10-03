<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    $dest = [
        'customer' => '/courier/customer/dashboard.php',
        'partner'  => '/courier/partner/dashboard.php',
        'admin'    => '/courier/admin/dashboard.php',
    ];
    header("Location: " . ($dest[$_SESSION['role']] ?? '/courier/index.php'));
    exit;
}

$pageTitle = 'Login — CrowdDrop';
$error = '';
$pending = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, role, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (!password_verify($password, $user['password'])) {
                $error = 'Incorrect email or password.';
            } elseif (!$user['is_verified'] && $user['role'] !== 'admin') {
                $pending = true;
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['role']    = $user['role'];

                $dest = [
                    'customer' => '/courier/customer/dashboard.php',
                    'partner'  => '/courier/partner/dashboard.php',
                    'admin'    => '/courier/admin/dashboard.php',
                ];
                header("Location: " . ($dest[$user['role']] ?? '/courier/index.php'));
                exit;
            }
        } else {
            $error = 'No account found with that email.';
        }
        $stmt->close();
    }
}

$pageTitle = 'Login — CrowdDrop';
include 'includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center">
<div class="w-full max-w-sm">

    <div class="flex justify-center mb-6">
        <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 pt-6 pb-2">
            <h1 class="text-lg font-bold text-slate-800">Welcome back</h1>
            <p class="text-xs text-slate-400 mt-0.5">Sign in to your CrowdDrop account</p>
        </div>

        <div class="px-6 pb-6 pt-4">

            <?php if ($pending): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Account pending verification</p>
                        <p class="text-xs text-amber-700 mt-1">Your account has been created and is awaiting admin approval. You will be able to login once the admin verifies your Aadhaar and account details.</p>
                        <p class="text-xs text-amber-500 mt-2">Please check back later.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2.5 rounded-lg mb-4 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-2.5 rounded-lg mb-4">
                Account created! You can login after admin verifies your account.
            </div>
            <?php endif; ?>

            <form method="POST" novalidate class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Email address</label>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                        autocomplete="email"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent transition"
                        placeholder="you@example.com"
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            id="pwd"
                            required
                            autocomplete="current-password"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent transition pr-10"
                            placeholder="••••••••"
                        >
                        <button type="button" onclick="togglePwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full bg-slate-800 hover:bg-slate-700 text-white font-medium text-sm py-2.5 rounded-lg transition-colors mt-1"
                >
                    Sign in
                </button>
            </form>

            <div class="mt-5 pt-4 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-400">
                    Don't have an account?
                    <a href="/courier/register.php" class="text-slate-700 font-semibold hover:underline">Sign up</a>
                </p>
            </div>
        </div>
    </div>

</div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('pwd');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
