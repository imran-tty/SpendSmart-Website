<?php
require_once 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SpendSmart — Track Smart, Spend Better</title>
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
<style>
  .fade { opacity:0; transform:translateY(16px); animation:fadeUp .6s ease forwards; }
  .fade:nth-child(2){animation-delay:.1s}
  .fade:nth-child(3){animation-delay:.2s}
  .fade:nth-child(4){animation-delay:.3s}
  .fade:nth-child(5){animation-delay:.4s}
  @keyframes fadeUp { to { opacity:1; transform:none; } }
</style>
</head>
<body class="bg-stone-50 font-sans antialiased">

<!-- NAV -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-stone-200">
  <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
    <span class="text-brand-mid font-semibold text-lg tracking-tight">SpendSmart</span>
    <div class="flex items-center gap-3">
      <a href="auth.php?tab=login"
         class="text-sm font-medium text-stone-500 hover:text-stone-800 transition">
        Sign in
      </a>
      <a href="auth.php?tab=register"
         class="text-sm font-medium bg-brand-mid text-white px-4 py-2 rounded-lg hover:bg-brand transition">
        Get started
      </a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="min-h-screen grid lg:grid-cols-2">

  <!-- Left -->
  <div class="bg-brand-mid flex flex-col justify-center px-10 py-24 lg:py-0">
    <div class="max-w-md">
      <p class="fade text-xs font-semibold tracking-widest text-green-300 uppercase mb-4">
        Personal Finance Tracker
      </p>
      <h1 class="fade text-5xl font-semibold text-white leading-tight tracking-tight mb-5">
        Track Smart,<br>Spend Better.
      </h1>
      <p class="fade text-green-100 text-base leading-relaxed mb-10">
        Log every expense, set monthly budgets, and see exactly
        where your money goes — all in one clean dashboard.
      </p>
      <ul class="space-y-3">
        <?php
        $feats = [
          ['M9 12l2 2 4-4',                                        'Log expenses with custom categories'],
          ['M18 20V10M12 20V4M6 20v-6',                            'Pie &amp; bar charts auto-generated'],
          ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10',         'Budget alerts at 90% usage'],
          ['M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',  'Filterable spending history'],
        ];
        foreach ($feats as [$ico, $txt]):
        ?>
        <li class="fade flex items-center gap-3 text-green-100 text-sm">
          <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="<?= $ico ?>"/>
            </svg>
          </span>
          <?= $txt ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Right -->
  <div class="flex items-center justify-center px-8 py-24 lg:py-0 mt-14 lg:mt-0">
    <div class="w-full max-w-sm">

      <!-- Preview card -->
      <div class="bg-white rounded-2xl border border-stone-200 shadow-sm p-5 mb-8">
        <div class="flex justify-between text-xs text-stone-400 mb-3">
          <span class="font-semibold uppercase tracking-wider">This month</span>
          <span><?= date('F Y') ?></span>
        </div>
        <div class="text-3xl font-semibold font-mono text-stone-800 mb-3">৳ 13,680</div>
        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden mb-1">
          <div class="h-full bg-amber-400 rounded-full" style="width:91%"></div>
        </div>
        <div class="flex justify-between text-xs text-stone-400 mb-5">
          <span>91% of budget used</span><span>Limit ৳ 15,000</span>
        </div>
        <?php
        $cats = [
          ['Food',      '৳ 6,840', 50, '#2d5a3d'],
          ['Transport', '৳ 3,420', 25, '#2980b9'],
          ['Bills',     '৳ 2,460', 18, '#c0392b'],
          ['Other',     '৳ 960',    7, '#d4860a'],
        ];
        foreach ($cats as [$n,$a,$p,$c]):
        ?>
        <div class="flex items-center gap-2 mb-2">
          <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= $c ?>"></span>
          <span class="text-xs text-stone-500 w-16"><?= $n ?></span>
          <div class="flex-1 h-1 bg-stone-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= $p ?>%;background:<?= $c ?>"></div>
          </div>
          <span class="text-xs font-mono text-stone-400 w-14 text-right"><?= $a ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <h2 class="text-xl font-semibold mb-1 tracking-tight">Get started free</h2>
      <p class="text-sm text-stone-400 mb-5">No credit card. No ads. Just your data.</p>

      <a href="auth.php?tab=register"
         class="block w-full text-center bg-brand-mid hover:bg-brand text-white font-medium
                py-3 rounded-xl text-sm transition mb-3">
        Create Account
      </a>
      <a href="auth.php?tab=login"
         class="block w-full text-center bg-stone-100 hover:bg-stone-200 text-stone-700
                font-medium py-3 rounded-xl text-sm transition">
        Sign In
      </a>
    </div>
  </div>
</section>

<!-- FEATURES STRIP -->
<section class="bg-white border-t border-stone-100 py-14">
  <div class="max-w-5xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
    <?php
    $pts = [
      ['৳', 'Log expenses',    'in seconds'],
      ['◑', 'Visual charts',   'auto-generated'],
      ['◉', 'Budget alerts',   'at 90% threshold'],
      ['≡', 'Filter history',  'by month &amp; category'],
    ];
    foreach ($pts as [$ic,$h,$s]):
    ?>
    <div>
      <div class="text-2xl text-brand-mid font-mono mb-2"><?= $ic ?></div>
      <div class="text-sm font-semibold text-stone-800"><?= $h ?></div>
      <div class="text-xs text-stone-400 mt-0.5"><?= $s ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-stone-100 py-6 text-center text-xs text-stone-400">
  SpendSmart &nbsp;·&nbsp; Web Programming Final Project &nbsp;·&nbsp; PHP &amp; MySQL
</footer>

</body>
</html>