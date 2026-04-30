<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — SpendSmart</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: { brand:{ DEFAULT:'#1a3d2b', mid:'#2d5a3d', light:'#e8f0ea' } },
    fontFamily: { sans:['DM Sans','ui-sans-serif'] }
  }}
}
</script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="bg-stone-50 font-sans antialiased min-h-screen flex items-center justify-center p-6">
<div class="text-center">
  <div class="text-8xl font-bold text-brand-mid opacity-10 leading-none mb-4">404</div>
  <h1 class="text-2xl font-semibold text-stone-800 mb-2">Page not found</h1>
  <p class="text-stone-400 text-sm mb-8">
    The page you're looking for doesn't exist or was moved.
  </p>
  <div class="flex items-center justify-center gap-3">
    <a href="index.php"
       class="px-5 py-2.5 rounded-lg bg-brand-mid hover:bg-brand text-white
              text-sm font-medium transition">
      ← Go home
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="dashboard.php"
       class="px-5 py-2.5 rounded-lg bg-stone-100 hover:bg-stone-200
              text-stone-700 text-sm font-medium transition">
      Dashboard
    </a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>