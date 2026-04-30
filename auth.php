<?php
require_once 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$tab='login'; $errors=[]; $vals=['name'=>'','email'=>'','phone'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['register'])) {
    $tab='register';
    $vals['name']=trim($_POST['name']??'');
    $vals['email']=trim($_POST['email']??'');
    $vals['phone']=trim($_POST['phone']??'');
    $pass=$_POST['password']??''; $pass2=$_POST['password2']??'';
    if (!$vals['name'])                                     $errors[]='Full name required.';
    if (!filter_var($vals['email'],FILTER_VALIDATE_EMAIL))  $errors[]='Valid email required.';
    if (strlen($pass)<6)                                    $errors[]='Password must be 6+ chars.';
    if ($pass!==$pass2)                                     $errors[]='Passwords do not match.';
    if (!$errors) {
        $db=getDB();
        $st=$db->prepare("SELECT user_id FROM users WHERE user_email=?");
        $st->execute([$vals['email']]);
        if ($st->fetch()) { $errors[]='Email already registered.'; }
        else {
            $db->prepare("INSERT INTO users (user_name,user_email,user_phone,password) VALUES (?,?,?,?)")
               ->execute([$vals['name'],$vals['email'],$vals['phone'],password_hash($pass,PASSWORD_DEFAULT)]);
            flash('Account created! Please sign in.');
            header('Location: auth.php?tab=login'); exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['login'])) {
    $tab='login';
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    $db=getDB();
    $st=$db->prepare("SELECT * FROM users WHERE user_email=?");
    $st->execute([$email]); $user=$st->fetch();
    if ($user && password_verify($pass,$user['password'])) {
        $_SESSION['user_id']=$user['user_id'];
        $_SESSION['user']=$user;
        header('Location: dashboard.php'); exit;
    }
    $errors[]='Invalid email or password.';
}

if (isset($_GET['tab'])) $tab=$_GET['tab'];
$flash=getFlash();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $tab==='register'?'Register':'Sign In' ?> — SpendSmart</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{p:{DEFAULT:'#0f172a',2:'#1e293b'},a:'#f59e0b',g:'#10b981'},fontFamily:{sans:['Inter','ui-sans-serif']}}}}</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);min-height:100vh}
.card-glass{background:rgba(255,255,255,.06);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.12)}
.inp{width:100%;padding:.6rem .875rem;border-radius:.5rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#fff;font-size:.875rem;outline:none;transition:.2s}
.inp::placeholder{color:rgba(255,255,255,.3)}
.inp:focus{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)}
</style>
</head>
<body class="font-sans text-white antialiased flex items-center justify-center min-h-screen p-4">

<div class="w-full max-w-md">
  <div class="text-center mb-7">
    <a href="index.php" class="font-bold text-2xl tracking-tight"><span class="text-a">Spend</span>Smart</a>
    <p class="text-white/40 text-sm mt-1">Track Smart, Spend Better</p>
  </div>

  <div class="card-glass rounded-2xl overflow-hidden">
    <div class="grid grid-cols-2 border-b border-white/10">
      <a href="auth.php?tab=login"
         class="py-3.5 text-center text-sm font-semibold transition <?= $tab==='login'?'text-a border-b-2 border-a bg-a/10':'text-white/40 hover:text-white' ?>">
        Sign In
      </a>
      <a href="auth.php?tab=register"
         class="py-3.5 text-center text-sm font-semibold transition <?= $tab==='register'?'text-a border-b-2 border-a bg-a/10':'text-white/40 hover:text-white' ?>">
        Create Account
      </a>
    </div>

    <div class="p-7">

      <?php if ($flash): ?>
      <div class="mb-5 px-4 py-3 rounded-lg text-sm font-medium bg-g/20 text-g border border-g/30">
        <?= e($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <?php if ($errors): ?>
      <div class="mb-5 px-4 py-3 rounded-lg bg-red-500/20 border border-red-500/30">
        <?php foreach ($errors as $err): ?>
        <p class="text-sm text-red-300"><?= e($err) ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($tab==='login'): ?>
      <form method="POST" novalidate>
        <input type="hidden" name="login" value="1">
        <div class="mb-4">
          <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Email</label>
          <input type="email" name="email" class="inp" placeholder="you@email.com" required autofocus>
        </div>
        <div class="mb-6">
          <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Password</label>
          <input type="password" name="password" class="inp" placeholder="Your password" required>
        </div>
        <button type="submit" class="w-full bg-a hover:bg-yellow-400 text-p font-bold py-3 rounded-xl text-sm transition">Sign In</button>
      </form>
      <p class="text-center text-xs text-white/40 mt-5">No account? <a href="auth.php?tab=register" class="text-a font-semibold hover:underline">Create one free</a></p>

      <?php else: ?>
      <form method="POST" novalidate>
        <input type="hidden" name="register" value="1">
        <div class="mb-4">
          <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Full Name</label>
          <input type="text" name="name" class="inp" value="<?= e($vals['name']) ?>" placeholder="Your full name" required autofocus>
        </div>
        <div class="mb-4">
          <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Email</label>
          <input type="email" name="email" class="inp" value="<?= e($vals['email']) ?>" placeholder="you@email.com" required>
        </div>
        <div class="mb-4">
          <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Phone <span class="normal-case font-normal text-white/30">optional</span></label>
          <input type="text" name="phone" class="inp" value="<?= e($vals['phone']) ?>" placeholder="+880...">
        </div>
        <div class="grid grid-cols-2 gap-3 mb-6">
          <div>
            <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Password</label>
            <input type="password" name="password" class="inp" placeholder="Min 6 chars" required>
          </div>
          <div>
            <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Confirm</label>
            <input type="password" name="password2" class="inp" placeholder="Repeat" required>
          </div>
        </div>
        <button type="submit" class="w-full bg-a hover:bg-yellow-400 text-p font-bold py-3 rounded-xl text-sm transition">Create Account</button>
      </form>
      <p class="text-center text-xs text-white/40 mt-5">Have an account? <a href="auth.php?tab=login" class="text-a font-semibold hover:underline">Sign in</a></p>
      <?php endif; ?>

    </div>
  </div>
  <p class="text-center text-xs text-white/30 mt-5"><a href="index.php" class="hover:text-white/60 transition">← Back to home</a></p>
</div>
</body>
</html>
