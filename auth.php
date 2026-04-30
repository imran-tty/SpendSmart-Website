<?php
require_once 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$tab    = $_GET['tab'] ?? 'login';
$errors = [];
$vals   = ['name'=>'','email'=>'','phone'=>''];

// ── REGISTER ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $tab           = 'register';
    $vals['name']  = trim($_POST['name']  ?? '');
    $vals['email'] = trim($_POST['email'] ?? '');
    $vals['phone'] = trim($_POST['phone'] ?? '');
    $pass          = $_POST['password']   ?? '';
    $pass2         = $_POST['password2']  ?? '';

    if (!$vals['name'])                                     $errors[] = 'Full name is required.';
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (strlen($pass) < 6)                                  $errors[] = 'Password must be 6+ characters.';
    if ($pass !== $pass2)                                   $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db = getDB();
        $st = $db->prepare("SELECT user_id FROM users WHERE user_email=?");
        $st->execute([$vals['email']]);
        if ($st->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $db->prepare("INSERT INTO users (user_name,user_email,user_phone,password) VALUES (?,?,?,?)")
               ->execute([$vals['name'], $vals['email'], $vals['phone'],
                          password_hash($pass, PASSWORD_DEFAULT)]);
            flash('Account created! Please sign in.');
            header('Location: auth.php?tab=login'); exit;
        }
    }
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $tab   = 'login';
    $email = trim($_POST['email']  ?? '');
    $pass  = $_POST['password']    ?? '';
    $db    = getDB();
    $st    = $db->prepare("SELECT * FROM users WHERE user_email=?");
    $st->execute([$email]);
    $user  = $st->fetch();
    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user']    = $user;
        header('Location: dashboard.php'); exit;
    }
    $errors[] = 'Invalid email or password.';
}

$flash = getFlash();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $tab==='register' ? 'Register' : 'Sign In' ?> — SpendSmart</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: { brand:{ DEFAULT:'#1a3d2b', mid:'#2d5a3d', light:'#e8f0ea' } },
    fontFamily: { sans:['DM Sans','ui-sans-serif'] }
  }}
}
</script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>
// Client-side validation
function validateForm(type) {
  const errs = [];
  if (type === 'register') {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('reg_email').value.trim();
    const pass  = document.getElementById('reg_pass').value;
    const pass2 = document.getElementById('reg_pass2').value;
    if (!name)                        errs.push('Full name is required.');
    if (!email.includes('@'))         errs.push('Enter a valid email.');
    if (pass.length < 6)              errs.push('Password must be 6+ characters.');
    if (pass !== pass2)               errs.push('Passwords do not match.');
  } else {
    const email = document.getElementById('login_email').value.trim();
    const pass  = document.getElementById('login_pass').value;
    if (!email) errs.push('Email is required.');
    if (!pass)  errs.push('Password is required.');
  }
  if (errs.length) {
    document.getElementById('js_errors').innerHTML =
      errs.map(e => `<p>${e}</p>`).join('');
    document.getElementById('js_error_box').classList.remove('hidden');
    return false;
  }
  return true;
}
</script>
</head>
<body class="bg-stone-50 font-sans antialiased min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="index.php" class="text-brand-mid font-semibold text-2xl tracking-tight">SpendSmart</a>
    <p class="text-stone-400 text-sm mt-1">Track Smart, Spend Better</p>
  </div>

  <!-- Card -->
  <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">

    <!-- Tabs -->
    <div class="grid grid-cols-2 border-b border-stone-200">
      <a href="auth.php?tab=login"
         class="py-3.5 text-center text-sm font-medium transition
                <?= $tab==='login'
                    ? 'text-brand-mid border-b-2 border-brand-mid bg-brand-light/30'
                    : 'text-stone-400 hover:text-stone-600' ?>">
        Sign In
      </a>
      <a href="auth.php?tab=register"
         class="py-3.5 text-center text-sm font-medium transition
                <?= $tab==='register'
                    ? 'text-brand-mid border-b-2 border-brand-mid bg-brand-light/30'
                    : 'text-stone-400 hover:text-stone-600' ?>">
        Create Account
      </a>
    </div>

    <div class="p-7">

      <!-- JS errors -->
      <div id="js_error_box" class="hidden mb-5 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <div id="js_errors" class="text-sm text-red-700 space-y-0.5"></div>
      </div>

      <!-- Server errors -->
      <?php if ($flash): ?>
      <div class="mb-5 px-4 py-3 rounded-lg text-sm font-medium
                  <?= $flash['type']==='danger'
                      ? 'bg-red-50 text-red-700 border border-red-200'
                      : 'bg-brand-light text-brand-mid border border-green-200' ?>">
        <?= e($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <?php if ($errors): ?>
      <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <?php foreach ($errors as $err): ?>
        <p class="text-sm text-red-700"><?= e($err) ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php
      $inp = "w-full px-3.5 py-2.5 rounded-lg border border-stone-200 bg-stone-50
              text-sm text-stone-800 placeholder-stone-300
              focus:outline-none focus:ring-2 focus:ring-brand-mid/20 focus:border-brand-mid transition";
      $lbl = "block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5";
      ?>

      <!-- LOGIN -->
      <?php if ($tab === 'login'): ?>
      <form method="POST" novalidate onsubmit="return validateForm('login')">
        <input type="hidden" name="login" value="1">
        <div class="mb-4">
          <label class="<?= $lbl ?>">Email</label>
          <input id="login_email" type="email" name="email" required autofocus
                 placeholder="you@email.com" class="<?= $inp ?>">
        </div>
        <div class="mb-6">
          <label class="<?= $lbl ?>">Password</label>
          <input id="login_pass" type="password" name="password" required
                 placeholder="Your password" class="<?= $inp ?>">
        </div>
        <button type="submit"
                class="w-full bg-brand-mid hover:bg-brand text-white font-medium
                       py-3 rounded-xl text-sm transition active:scale-[.98]">
          Sign In
        </button>
      </form>
      <p class="text-center text-xs text-stone-400 mt-5">
        No account?
        <a href="auth.php?tab=register" class="text-brand-mid font-medium hover:underline">
          Create one free
        </a>
      </p>

      <!-- REGISTER -->
      <?php else: ?>
      <form method="POST" novalidate onsubmit="return validateForm('register')">
        <input type="hidden" name="register" value="1">
        <div class="mb-4">
          <label class="<?= $lbl ?>">Full Name</label>
          <input id="name" type="text" name="name" required autofocus
                 value="<?= e($vals['name']) ?>" placeholder="Your full name"
                 class="<?= $inp ?>">
        </div>
        <div class="mb-4">
          <label class="<?= $lbl ?>">Email</label>
          <input id="reg_email" type="email" name="email" required
                 value="<?= e($vals['email']) ?>" placeholder="you@email.com"
                 class="<?= $inp ?>">
        </div>
        <div class="mb-4">
          <label class="<?= $lbl ?>">
            Phone
            <span class="normal-case font-normal text-stone-300 ml-1">optional</span>
          </label>
          <input type="text" name="phone"
                 value="<?= e($vals['phone']) ?>" placeholder="+880..."
                 class="<?= $inp ?>">
        </div>
        <div class="grid grid-cols-2 gap-3 mb-6">
          <div>
            <label class="<?= $lbl ?>">Password</label>
            <input id="reg_pass" type="password" name="password" required
                   placeholder="Min 6 chars" class="<?= $inp ?>">
          </div>
          <div>
            <label class="<?= $lbl ?>">Confirm</label>
            <input id="reg_pass2" type="password" name="password2" required
                   placeholder="Repeat" class="<?= $inp ?>">
          </div>
        </div>
        <button type="submit"
                class="w-full bg-brand-mid hover:bg-brand text-white font-medium
                       py-3 rounded-xl text-sm transition active:scale-[.98]">
          Create Account
        </button>
      </form>
      <p class="text-center text-xs text-stone-400 mt-5">
        Already have an account?
        <a href="auth.php?tab=login" class="text-brand-mid font-medium hover:underline">
          Sign in
        </a>
      </p>
      <?php endif; ?>

    </div>
  </div>

  <p class="text-center text-xs text-stone-400 mt-6">
    <a href="index.php" class="hover:text-stone-600 transition">← Back to home</a>
  </p>

</div>
</body>
</html>