<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    $dest = ['customer'=>'/courier/customer/dashboard.php','partner'=>'/courier/partner/dashboard.php','admin'=>'/courier/admin/dashboard.php'];
    header("Location: " . ($dest[$_SESSION['role']] ?? '/courier/index.php'));
    exit;
}

$pageTitle = 'Sign Up — CrowdDrop';
$errors  = [];
$success = false;
$f = ['name'=>'','email'=>'','phone'=>'','role'=>'customer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f['name']  = trim($_POST['name']  ?? '');
    $f['email'] = trim($_POST['email'] ?? '');
    $f['phone'] = trim($_POST['phone'] ?? '');
    $f['role']  = in_array($_POST['role'] ?? '', ['customer','partner']) ? $_POST['role'] : 'customer';
    $password   = $_POST['password']  ?? '';
    $password2  = $_POST['password2'] ?? '';

    $bank_account = trim($_POST['bank_account'] ?? '');
    $ifsc_code    = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $branch_name  = trim($_POST['branch_name']  ?? '');

    if (!$f['name'])
        $errors[] = 'Full name is required.';
    if (!$f['email'] || !filter_var($f['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid email address is required.';

    if (!preg_match('/^\d{10}$/', $f['phone']))
        $errors[] = 'Phone must be exactly 10 digits (numbers only).';

    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters long.';
    elseif (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Password must include at least one uppercase letter (A–Z).';
    elseif (!preg_match('/[a-z]/', $password))
        $errors[] = 'Password must include at least one lowercase letter (a–z).';
    elseif (!preg_match('/[0-9]/', $password))
        $errors[] = 'Password must include at least one number (0–9).';
    elseif (!preg_match('/[\W_]/', $password))
        $errors[] = 'Password must include at least one symbol (e.g. @, #, !, _).';

    if ($password !== $password2)
        $errors[] = 'Passwords do not match.';

    if ($f['role'] === 'partner') {
        if (!$bank_account || !preg_match('/^\d{9,18}$/', $bank_account))
            $errors[] = 'Valid bank account number is required (9–18 digits).';
        if (!$ifsc_code || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code))
            $errors[] = 'Valid IFSC code is required (e.g. SBIN0001234).';
        if (!$branch_name)
            $errors[] = 'Bank branch name is required.';
    }

    if (empty($_FILES['aadhaar_img']['tmp_name']) || $_FILES['aadhaar_img']['error'] === UPLOAD_ERR_NO_FILE)
        $errors[] = 'Aadhaar image is required.';
    if (empty($_FILES['photo_img']['tmp_name']) || $_FILES['photo_img']['error'] === UPLOAD_ERR_NO_FILE)
        $errors[] = 'Passport-size photo is required.';

    $allowed_mime = ['image/jpeg','image/jpg','image/png','application/pdf'];
    foreach (['aadhaar_img','photo_img'] as $fld) {
        if (!empty($_FILES[$fld]['tmp_name']) && $_FILES[$fld]['error'] === UPLOAD_ERR_OK) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$fld]['tmp_name']);
            if (!in_array($mime, $allowed_mime))
                $errors[] = ucfirst(str_replace('_',' ',$fld)) . ': only JPG, PNG or PDF allowed.';
            if ($_FILES[$fld]['size'] > 5*1024*1024)
                $errors[] = ucfirst(str_replace('_',' ',$fld)) . ': file too large (max 5 MB).';
        }
    }

    if (empty($errors)) {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s", $f['email']); $chk->execute();
        if ($chk->get_result()->num_rows > 0)
            $errors[] = 'That email is already registered. <a href="/courier/login.php" class="underline font-medium">Login?</a>';
        $chk->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ba   = $f['role'] === 'partner' ? $bank_account : null;
        $ic   = $f['role'] === 'partner' ? $ifsc_code    : null;
        $bn   = $f['role'] === 'partner' ? $branch_name  : null;

        $ins = $conn->prepare("INSERT INTO users (name,email,phone,bank_account,ifsc_code,branch_name,password,role,is_verified) VALUES (?,?,?,?,?,?,?,?,0)");
        $ins->bind_param("ssssssss", $f['name'], $f['email'], $f['phone'], $ba, $ic, $bn, $hash, $f['role']);
        if ($ins->execute()) {
            $new_id     = $conn->insert_id;
            $aadhaar_fn = uploadDocument('aadhaar_img', 'aadhaar', $new_id);
            $photo_fn   = uploadDocument('photo_img',   'photo',   $new_id);
            $upd = $conn->prepare("UPDATE users SET aadhaar_img=?, photo_img=? WHERE id=?");
            $upd->bind_param("ssi", $aadhaar_fn, $photo_fn, $new_id);
            $upd->execute(); $upd->close();
            $success = true;
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $ins->close();
    }
}

include 'includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center py-8">
<div class="w-full max-w-lg">

    <div class="flex justify-center mb-6">
        <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 pt-6 pb-2">
            <h1 class="text-lg font-bold text-slate-800">Create an account</h1>
            <p class="text-xs text-slate-400 mt-0.5">Join as a Sender to ship parcels, or as a Traveler to earn by delivering.</p>
        </div>

        <div class="px-6 pb-6 pt-4">

        <?php if ($success): ?>
            <div class="text-center py-6">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="font-semibold text-slate-800 text-base mb-1">Account created!</h2>
                <p class="text-xs text-slate-500 leading-relaxed max-w-xs mx-auto">
                    Your documents have been uploaded. Admin will review your Aadhaar image and photo, then activate your account.
                </p>
                <div class="mt-2 text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 inline-block">
                    You can login <strong>only after admin approves</strong> your account.
                </div>
                <br>
                <a href="/courier/login.php" class="inline-block mt-4 bg-slate-800 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-slate-700 transition-colors">Go to Login</a>
            </div>
        <?php else: ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 mb-4 space-y-1">
            <?php foreach ($errors as $e): ?>
            <p class="text-xs text-red-700 flex items-start gap-1.5">
                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span><?= $e ?></span>
            </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-5">
            <button type="button" id="btn-customer" onclick="setRole('customer')"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all <?= $f['role']==='customer'?'bg-white text-slate-800 shadow-sm':'text-slate-500' ?>">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Sender
            </button>
            <button type="button" id="btn-partner" onclick="setRole('partner')"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all <?= $f['role']==='partner'?'bg-white text-slate-800 shadow-sm':'text-slate-500' ?>">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Traveler
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" novalidate class="space-y-3.5">
            <input type="hidden" name="role" id="role-input" value="<?= htmlspecialchars($f['role']) ?>">
            <div id="role-desc" class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 leading-relaxed"></div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($f['name']) ?>" required placeholder="As on Aadhaar"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Email Address <span class="text-red-400">*</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars($f['email']) ?>" required placeholder="you@example.com"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Phone Number <span class="text-red-400">*</span></label>
                <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($f['phone']) ?>" required
                    maxlength="10" placeholder="10-digit mobile number"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition"
                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10); validatePhone(this)">
                <p id="phone-hint" class="text-xs mt-1 hidden"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Password <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <input type="password" name="password" id="pwd" required
                            placeholder="Create a strong password"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition pr-9"
                            oninput="checkPasswordStrength(this.value)">
                        <button type="button" onclick="toggleVis('pwd','eye1')" tabindex="-1"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500">
                            <svg id="eye1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-1.5 flex gap-1" id="strength-bars">
                        <div class="h-1 flex-1 rounded-full bg-slate-100" id="sb1"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-100" id="sb2"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-100" id="sb3"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-100" id="sb4"></div>
                    </div>
                    <p id="pwd-hint" class="text-xs mt-1 text-slate-400 leading-relaxed"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Confirm Password <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <input type="password" name="password2" id="pwd2" required placeholder="Repeat password"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition pr-9"
                            oninput="checkMatch()">
                        <button type="button" onclick="toggleVis('pwd2','eye2')" tabindex="-1"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500">
                            <svg id="eye2" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p id="match-hint" class="text-xs mt-1 hidden"></p>
                </div>
            </div>

            <div id="pwd-rules" class="bg-slate-50 border border-slate-100 rounded-lg px-3 py-2.5 space-y-1">
                <p class="text-xs font-medium text-slate-500 mb-1">Password must have:</p>
                <div class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                    <p class="text-xs flex items-center gap-1.5" id="r-len"><span class="w-3 h-3 rounded-full border border-slate-300 inline-block"></span> At least 6 characters</p>
                    <p class="text-xs flex items-center gap-1.5" id="r-upper"><span class="w-3 h-3 rounded-full border border-slate-300 inline-block"></span> Uppercase letter (A–Z)</p>
                    <p class="text-xs flex items-center gap-1.5" id="r-lower"><span class="w-3 h-3 rounded-full border border-slate-300 inline-block"></span> Lowercase letter (a–z)</p>
                    <p class="text-xs flex items-center gap-1.5" id="r-num"><span class="w-3 h-3 rounded-full border border-slate-300 inline-block"></span> Number (0–9)</p>
                    <p class="text-xs flex items-center gap-1.5 col-span-2" id="r-sym"><span class="w-3 h-3 rounded-full border border-slate-300 inline-block"></span> Symbol (@, #, !, _, etc.)</p>
                </div>
            </div>

            <div id="bank-fields" class="hidden border-t border-slate-100 pt-4 space-y-3">
                <p class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Bank Account Details <span class="text-red-400">*</span>
                </p>
                <p class="text-xs text-slate-400 -mt-1">Required to receive payment after successful deliveries.</p>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Account Number <span class="text-red-400">*</span></label>
                    <input type="text" name="bank_account" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>"
                        placeholder="9 to 18 digit account number"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-slate-300 transition"
                        oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1.5">IFSC Code <span class="text-red-400">*</span></label>
                        <input type="text" name="ifsc_code" value="<?= htmlspecialchars($_POST['ifsc_code'] ?? '') ?>"
                            placeholder="e.g. SBIN0001234" maxlength="11"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-slate-300 transition"
                            oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1.5">Branch Name <span class="text-red-400">*</span></label>
                        <input type="text" name="branch_name" value="<?= htmlspecialchars($_POST['branch_name'] ?? '') ?>"
                            placeholder="e.g. MG Road, Kochi"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 transition">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 space-y-3">
                <p class="text-xs font-semibold text-slate-600">Identity Documents <span class="text-red-400">*</span></p>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Aadhaar Card Image <span class="text-red-400">*</span> <span class="text-slate-400 font-normal">(JPG/PNG/PDF, max 5 MB)</span></label>
                    <label class="flex items-center gap-3 border-2 border-dashed border-slate-200 rounded-xl p-3 cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-all" id="aadhaar-label">
                        <div class="w-9 h-9 bg-slate-100 rounded-lg flex items-center justify-center shrink-0" id="aadhaar-icon">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-600" id="aadhaar-text">Click to upload Aadhaar image</p>
                            <p class="text-xs text-slate-400">Front side of Aadhaar card</p>
                        </div>
                        <input type="file" name="aadhaar_img" id="aadhaar_img" required accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                            onchange="previewFile(this,'aadhaar-text','aadhaar-label','aadhaar-preview')">
                    </label>
                    <img id="aadhaar-preview" src="" alt="" class="hidden mt-2 rounded-lg border border-slate-200 max-h-32 object-cover">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Passport-Size Photo <span class="text-red-400">*</span> <span class="text-slate-400 font-normal">(JPG/PNG, max 5 MB)</span></label>
                    <label class="flex items-center gap-3 border-2 border-dashed border-slate-200 rounded-xl p-3 cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-all" id="photo-label">
                        <div class="w-9 h-9 bg-slate-100 rounded-lg flex items-center justify-center shrink-0" id="photo-icon">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-600" id="photo-text">Click to upload passport photo</p>
                            <p class="text-xs text-slate-400">Clear face photo, white background preferred</p>
                        </div>
                        <input type="file" name="photo_img" id="photo_img" required accept=".jpg,.jpeg,.png" class="hidden"
                            onchange="previewFile(this,'photo-text','photo-label','photo-preview')">
                    </label>
                    <img id="photo-preview" src="" alt="" class="hidden mt-2 rounded-lg border border-slate-200 max-h-32 object-cover">
                </div>
                <div class="flex items-start gap-2 text-xs text-slate-400 bg-blue-50 border border-blue-100 px-3 py-2 rounded-lg">
                    <svg class="w-3.5 h-3.5 text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Documents are stored securely and reviewed only by admin for identity verification.
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-medium text-sm py-2.5 rounded-lg transition-colors">
                Create Account
            </button>
        </form>

        <div class="mt-4 pt-4 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-400">Already have an account? <a href="/courier/login.php" class="text-slate-700 font-semibold hover:underline">Sign in</a></p>
        </div>

        <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script>
const roleDescs = {
    customer: 'You will be able to send parcels and track your deliveries.',
    partner:  'You will be able to browse parcel requests matching your travel route and earn rewards as a traveler.'
};
function setRole(role) {
    document.getElementById('role-input').value = role;
    document.getElementById('role-desc').textContent = roleDescs[role];
    const c = document.getElementById('btn-customer'), p = document.getElementById('btn-partner');
    const bankFields = document.getElementById('bank-fields');
    if (role === 'customer') {
        c.classList.add('bg-white','text-slate-800','shadow-sm'); c.classList.remove('text-slate-500');
        p.classList.remove('bg-white','text-slate-800','shadow-sm'); p.classList.add('text-slate-500');
        bankFields.classList.add('hidden');
    } else {
        p.classList.add('bg-white','text-slate-800','shadow-sm'); p.classList.remove('text-slate-500');
        c.classList.remove('bg-white','text-slate-800','shadow-sm'); c.classList.add('text-slate-500');
        bankFields.classList.remove('hidden');
    }
}
setRole(document.getElementById('role-input').value);

function validatePhone(el) {
    const hint = document.getElementById('phone-hint');
    if (el.value.length === 0) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (el.value.length === 10) {
        hint.textContent = '✓ Valid phone number';
        hint.className = 'text-xs mt-1 text-green-600';
    } else {
        hint.textContent = `${el.value.length}/10 digits entered`;
        hint.className = 'text-xs mt-1 text-amber-500';
    }
}

function checkPasswordStrength(val) {
    const rules = {
        len:   val.length >= 6,
        upper: /[A-Z]/.test(val),
        lower: /[a-z]/.test(val),
        num:   /[0-9]/.test(val),
        sym:   /[\W_]/.test(val),
    };

    Object.entries(rules).forEach(([k, ok]) => {
        const el = document.getElementById('r-' + k);
        if (!el) return;
        const dot = el.querySelector('span');
        if (ok) {
            dot.className = 'w-3 h-3 rounded-full bg-green-500 inline-block';
            el.className = el.className.replace('text-slate-400','') + ' text-green-600';
        } else {
            dot.className = 'w-3 h-3 rounded-full border border-slate-300 inline-block';
            el.className = 'text-xs flex items-center gap-1.5 text-slate-400';
        }
    });

    const score = Object.values(rules).filter(Boolean).length;
    const bars  = ['sb1','sb2','sb3','sb4'];
    const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
    bars.forEach((id, i) => {
        const el = document.getElementById(id);
        el.className = 'h-1 flex-1 rounded-full ' + (i < score ? colors[Math.min(score-1,3)] : 'bg-slate-100');
    });

    const labels = ['','Weak','Fair','Good','Strong'];
    const hint = document.getElementById('pwd-hint');
    if (val.length === 0) {
        hint.textContent = '';
    } else if (score < 3) {
        const missing = [];
        if (!rules.len)   missing.push('6+ chars');
        if (!rules.upper) missing.push('uppercase');
        if (!rules.lower) missing.push('lowercase');
        if (!rules.num)   missing.push('number');
        if (!rules.sym)   missing.push('symbol');
        hint.textContent = 'Add: ' + missing.join(', ');
        hint.className = 'text-xs mt-1 text-amber-500';
    } else if (score === 5) {
        hint.textContent = '✓ Strong password';
        hint.className = 'text-xs mt-1 text-green-600';
    } else {
        hint.textContent = labels[score] + ' — all 5 rules needed';
        hint.className = 'text-xs mt-1 text-slate-400';
    }

    checkMatch();
}

function checkMatch() {
    const p1 = document.getElementById('pwd').value;
    const p2 = document.getElementById('pwd2').value;
    const hint = document.getElementById('match-hint');
    if (!p2) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (p1 === p2) {
        hint.textContent = '✓ Passwords match';
        hint.className = 'text-xs mt-1 text-green-600';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.className = 'text-xs mt-1 text-red-500';
    }
}

function toggleVis(inputId, iconId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function previewFile(input, textId, labelId, previewId) {
    const file = input.files[0]; if (!file) return;
    document.getElementById(textId).textContent = file.name;
    const label = document.getElementById(labelId);
    label.classList.add('border-green-400','bg-green-50');
    label.classList.remove('border-slate-200');
    const preview = document.getElementById(previewId);
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('hidden');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
