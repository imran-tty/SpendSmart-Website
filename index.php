<?php
require_once 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SpendSmart — Track Smart, Spend Better</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{p:{DEFAULT:'#0f172a',2:'#1e293b',3:'#334155'},a:'#f59e0b',b:'#06b6d4',g:'#10b981'},fontFamily:{sans:['Inter','ui-sans-serif'],mono:['JetBrains Mono','ui-monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);min-height:100vh}
.glow{box-shadow:0 0 40px rgba(245,158,11,.15)}
.card-glass{background:rgba(255,255,255,.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1)}
.badge-glow{box-shadow:0 0 20px rgba(245,158,11,.4)}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.float{animation:float 4s ease-in-out infinite}
</style>
</head>
<body class="font-sans text-white antialiased">

<nav class="fixed top-0 inset-x-0 z-50 border-b border-white/10" style="background:rgba(15,23,42,.8);backdrop-filter:blur(12px)">
  <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
    <span class="font-bold text-lg tracking-tight"><span class="text-a">Spend</span>Smart</span>
    <div class="flex gap-3">
      <a href="auth.php?tab=login" class="text-sm font-medium text-white/70 hover:text-white transition px-3 py-1.5">Sign in</a>
      <a href="auth.php?tab=register" class="text-sm font-semibold bg-a text-p px-4 py-1.5 rounded-lg hover:bg-yellow-400 transition">Get started</a>
    </div>
  </div>
</nav>

<section class="min-h-screen flex items-center pt-14">
  <div class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-2 gap-16 items-center">

    <div>
      <div class="inline-flex items-center gap-2 bg-a/10 border border-a/30 rounded-full px-4 py-1.5 mb-6">
        <span class="w-2 h-2 bg-a rounded-full animate-pulse"></span>
        <span class="text-a text-xs font-semibold tracking-wider uppercase">Personal Finance Tracker</span>
      </div>
      <h1 class="text-5xl font-bold leading-tight mb-5">
        Track Smart,<br><span class="text-a">Spend Better.</span>
      </h1>
      <p class="text-white/60 text-lg leading-relaxed mb-10">
        Log expenses, grow your savings, and visualise your money — all in one beautiful dashboard.
      </p>
      <div class="grid grid-cols-2 gap-3 mb-10">
        <?php
        $feats=[
          ['💰','Expense Tracking','Log with categories'],
          ['📊','Visual Charts','Pie & bar analysis'],
          ['🏦','Savings Vault','Deposit & withdraw'],
          ['🔔','Budget Alerts','90% threshold warning'],
        ];
        foreach($feats as [$ic,$h,$s]):
        ?>
        <div class="card-glass rounded-xl p-3.5 flex items-start gap-3">
          <span class="text-xl"><?= $ic ?></span>
          <div>
            <div class="text-sm font-semibold text-white"><?= $h ?></div>
            <div class="text-xs text-white/50 mt-0.5"><?= $s ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="flex gap-3">
        <a href="auth.php?tab=register" class="bg-a hover:bg-yellow-400 text-p font-bold px-6 py-3 rounded-xl transition text-sm">Create Free Account</a>
        <a href="auth.php?tab=login" class="card-glass hover:bg-white/10 text-white font-medium px-6 py-3 rounded-xl transition text-sm">Sign In</a>
      </div>
    </div>

    <!-- Preview card -->
    <div class="float">
      <div class="card-glass glow rounded-2xl p-6 max-w-sm mx-auto">
        <div class="flex justify-between items-center mb-4">
          <div>
            <div class="text-xs text-white/50 uppercase tracking-wider mb-1">This Month</div>
            <div class="text-3xl font-bold font-mono text-a">৳ 13,680</div>
          </div>
          <div class="bg-a/20 border border-a/30 rounded-xl px-3 py-1.5 text-center">
            <div class="text-a text-xs font-bold"><?= date('M Y') ?></div>
          </div>
        </div>
        <div class="h-2 bg-white/10 rounded-full overflow-hidden mb-1">
          <div class="h-full bg-gradient-to-r from-a to-yellow-300 rounded-full" style="width:91%"></div>
        </div>
        <div class="flex justify-between text-xs text-white/40 mb-5">
          <span>91% used</span><span>Limit ৳15,000</span>
        </div>
        <?php
        $cats=[['Food','৳6,840',50,'#ef4444'],['Travel','৳3,420',25,'#06b6d4'],['Bills','৳2,460',18,'#8b5cf6'],['Other','৳960',7,'#f59e0b']];
        foreach($cats as [$n,$a,$p,$c]):
        ?>
        <div class="flex items-center gap-2 mb-2.5">
          <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= $c ?>"></span>
          <span class="text-xs text-white/70 w-14"><?= $n ?></span>
          <div class="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= $p ?>%;background:<?= $c ?>"></div>
          </div>
          <span class="text-xs font-mono text-white/60 w-14 text-right"><?= $a ?></span>
        </div>
        <?php endforeach; ?>
        <div class="mt-4 pt-4 border-t border-white/10 flex justify-between items-center">
          <div>
            <div class="text-xs text-white/40">Savings Vault</div>
            <div class="text-lg font-bold text-g font-mono">৳ 24,500</div>
          </div>
          <div class="bg-g/20 border border-g/30 text-g text-xs font-semibold px-3 py-1.5 rounded-lg">+৳ 2,000 this month</div>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="border-t border-white/10 py-12" style="background:rgba(255,255,255,.02)">
  <div class="max-w-4xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
    <?php
    $pts=[['৳','Log Expenses','in seconds'],['📈','Auto Charts','every month'],['🏦','Save Money','anytime'],['🔒','Secure','password hashed']];
    foreach($pts as [$ic,$h,$s]):
    ?>
    <div>
      <div class="text-3xl mb-2"><?= $ic ?></div>
      <div class="text-sm font-semibold text-white"><?= $h ?></div>
      <div class="text-xs text-white/40 mt-0.5"><?= $s ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<footer class="border-t border-white/10 py-6 text-center text-xs text-white/30">
  SpendSmart · Web Programming Final Project · PHP & MySQL
</footer>
</body>
</html>
