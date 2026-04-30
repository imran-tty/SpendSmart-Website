<?php
require_once 'db.php';
requireLogin();

$db   = getDB();
$uid  = uid();
$page = $_GET['p'] ?? 'home';
$m    = (int)date('n');
$y    = (int)date('Y');
$user = $_SESSION['user'];

// ── SEED DEFAULT CATEGORIES ───────────────────────────────────
$chk = $db->prepare("SELECT COUNT(*) FROM categories WHERE user_id=?");
$chk->execute([$uid]);
if ((int)$chk->fetchColumn() === 0) {
    $defaults = [
        ['Food',          '#e74c3c'],
        ['Medical',       '#3498db'],
        ['Travel',        '#f39c12'],
        ['Education',     '#9b59b6'],
        ['Personal',      '#1abc9c'],
        ['Family',        '#e67e22'],
        ['Entertainment', '#e91e63'],
        ['Living Cost',   '#2d5a3d'],
    ];
    $ins = $db->prepare("INSERT INTO categories (user_id,category_name,category_color) VALUES (?,?,?)");
    foreach ($defaults as [$name,$color]) $ins->execute([$uid,$name,$color]);
}

// ── ALL POST ACTIONS ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_expense' || $act === 'edit_expense') {
        $title  = trim($_POST['title']       ?? '');
        $amount = (float)($_POST['amount']   ?? 0);
        $cat    = ($_POST['category_id']     ?? '') ?: null;
        $date   = $_POST['expense_date']     ?? date('Y-m-d');
        $eid    = (int)($_POST['expense_id'] ?? 0);
        if (!$title || $amount <= 0) {
            flash('Title and valid amount required.', 'danger');
        } elseif ($eid) {
            $db->prepare("UPDATE expenses SET title=?,amount=?,category_id=?,expense_date=? WHERE expense_id=? AND user_id=?")
               ->execute([$title,$amount,$cat,$date,$eid,$uid]);
            flash('Expense updated.');
        } else {
            $db->prepare("INSERT INTO expenses (user_id,category_id,title,amount,expense_date) VALUES (?,?,?,?,?)")
               ->execute([$uid,$cat,$title,$amount,$date]);
            flash('Expense added.');
        }
        header("Location: dashboard.php?p=expenses"); exit;
    }

    if ($act === 'del_expense') {
        $db->prepare("DELETE FROM expenses WHERE expense_id=? AND user_id=?")
           ->execute([(int)$_POST['expense_id'],$uid]);
        flash('Expense deleted.');
        header("Location: dashboard.php?p=expenses"); exit;
    }

    if ($act === 'add_cat' || $act === 'edit_cat') {
        $name  = trim($_POST['cat_name'] ?? '');
        $color = $_POST['cat_color']     ?? '#2d5a3d';
        $cid   = (int)($_POST['cat_id'] ?? 0);
        if (!$name) {
            flash('Category name required.', 'danger');
        } elseif ($cid) {
            $db->prepare("UPDATE categories SET category_name=?,category_color=? WHERE category_id=? AND user_id=?")
               ->execute([$name,$color,$cid,$uid]);
            flash('Category updated.');
        } else {
            $db->prepare("INSERT INTO categories (user_id,category_name,category_color) VALUES (?,?,?)")
               ->execute([$uid,$name,$color]);
            flash('Category created.');
        }
        header("Location: dashboard.php?p=categories"); exit;
    }

    if ($act === 'del_cat') {
        $cid = (int)$_POST['cat_id'];
        $db->prepare("UPDATE expenses SET category_id=NULL WHERE category_id=? AND user_id=?")->execute([$cid,$uid]);
        $db->prepare("DELETE FROM categories WHERE category_id=? AND user_id=?")->execute([$cid,$uid]);
        flash('Category deleted.');
        header("Location: dashboard.php?p=categories"); exit;
    }

    if ($act === 'save_profile') {
        $name  = trim($_POST['user_name']  ?? '');
        $email = trim($_POST['user_email'] ?? '');
        $phone = trim($_POST['user_phone'] ?? '');
        $errs  = [];
        if (!$name) $errs[] = 'Name required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Valid email required.';
        if (!$errs) {
            $chk = $db->prepare("SELECT user_id FROM users WHERE user_email=? AND user_id!=?");
            $chk->execute([$email,$uid]);
            if ($chk->fetch()) { flash('Email already taken.','danger'); }
            else {
                $db->prepare("UPDATE users SET user_name=?,user_email=?,user_phone=? WHERE user_id=?")
                   ->execute([$name,$email,$phone,$uid]);
                $_SESSION['user']['user_name']  = $name;
                $_SESSION['user']['user_email'] = $email;
                $_SESSION['user']['user_phone'] = $phone;
                $user = $_SESSION['user'];
                flash('Profile updated.');
            }
        } else { flash(implode(' ',$errs),'danger'); }
        header("Location: dashboard.php?p=profile"); exit;
    }

    if ($act === 'save_password') {
        $cur  = $_POST['cur_pass']  ?? '';
        $new  = $_POST['new_pass']  ?? '';
        $new2 = $_POST['new_pass2'] ?? '';
        $st   = $db->prepare("SELECT password FROM users WHERE user_id=?");
        $st->execute([$uid]); $hash = $st->fetchColumn();
        if (!password_verify($cur,$hash))  flash('Current password incorrect.','danger');
        elseif (strlen($new) < 6)          flash('New password needs 6+ characters.','danger');
        elseif ($new !== $new2)            flash('Passwords do not match.','danger');
        else {
            $db->prepare("UPDATE users SET password=? WHERE user_id=?")
               ->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
            flash('Password changed.');
        }
        header("Location: dashboard.php?p=profile"); exit;
    }

    if ($act === 'save_budget') {
        $limit = (float)($_POST['monthly_limit'] ?? 0);
        $bm    = (int)($_POST['bmonth'] ?? $m);
        $by    = (int)($_POST['byear']  ?? $y);
        $db->prepare("INSERT INTO budgets (user_id,monthly_limit,month,year) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE monthly_limit=VALUES(monthly_limit)")
           ->execute([$uid,$limit,$bm,$by]);
        flash('Budget saved.');
        header("Location: dashboard.php?p=profile"); exit;
    }
}

// ── SHARED ────────────────────────────────────────────────────
$st = $db->prepare("SELECT monthly_limit FROM budgets WHERE user_id=? AND month=? AND year=?");
$st->execute([$uid,$m,$y]);
$budget = (float)($st->fetchColumn() ?: 0);

$st = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$st->execute([$uid,$m,$y]);
$monthTotal = (float)$st->fetchColumn();
$budgetPct  = $budget > 0 ? min(($monthTotal/$budget)*100,100) : 0;

$st = $db->prepare("SELECT * FROM categories WHERE user_id=? ORDER BY category_name");
$st->execute([$uid]);
$allCats = $st->fetchAll();

$flash  = getFlash();
$COLORS = ['#e74c3c','#3498db','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#2d5a3d','#16a085','#795548'];
$inp    = "w-full px-3 py-2.5 rounded-lg border border-stone-200 bg-stone-50 text-sm text-stone-800 placeholder-stone-300 focus:outline-none focus:ring-2 focus:ring-brand-mid/20 focus:border-brand-mid transition";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SpendSmart</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'#1a3d2b',mid:'#2d5a3d',light:'#e8f0ea'}},fontFamily:{sans:['DM Sans','ui-sans-serif'],mono:['DM Mono','ui-monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-stone-50 font-sans antialiased">
<div class="flex min-h-screen">

<!-- ── SIDEBAR ─────────────────────────────────────────────── -->
<aside class="w-56 bg-white border-r border-stone-200 fixed top-0 left-0 bottom-0 flex flex-col z-40">
  <div class="px-5 py-5 border-b border-stone-100">
    <div class="text-brand-mid font-semibold text-base tracking-tight">SpendSmart</div>
    <div class="text-stone-400 text-xs mt-0.5">Track Smart, Spend Better</div>
  </div>
  <nav class="flex-1 px-3 py-3 space-y-0.5">
    <?php
    $links = [
      ['home',       'Dashboard',  'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10'],
      ['expenses',   'Expenses',   'M12 1v22 M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
      ['categories', 'Categories', 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'],
      ['profile',    'Profile',    'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
    ];
    foreach ($links as [$key,$label,$ico]):
      $a = $page===$key;
    ?>
    <a href="dashboard.php?p=<?= $key ?>"
       class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition
              <?= $a?'bg-brand-light text-brand-mid':'text-stone-500 hover:bg-stone-100 hover:text-stone-800' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="<?= $ico ?>"/>
      </svg>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="px-4 py-4 border-t border-stone-100">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-full bg-brand-light text-brand-mid flex items-center justify-center text-xs font-semibold flex-shrink-0">
        <?= strtoupper(substr($user['user_name'],0,1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-medium text-stone-700 truncate"><?= e($user['user_name']) ?></div>
        <a href="logout.php" class="text-xs text-stone-400 hover:text-red-500 transition">Sign out</a>
      </div>
    </div>
  </div>
</aside>

<!-- ── MAIN ────────────────────────────────────────────────── -->
<main class="ml-56 flex-1 p-8">

<?php if ($flash): ?>
<div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium border
            <?= $flash['type']==='danger'?'bg-red-50 text-red-700 border-red-200':'bg-brand-light text-brand-mid border-green-200' ?>">
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════
// HOME
// ════════════════════════════════════════════════════════════
if ($page === 'home'):

$st=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=?");
$st->execute([$uid]); $allTotal=(float)$st->fetchColumn();

$st=$db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$st->execute([$uid,$m,$y]); $expCount=(int)$st->fetchColumn();

// !! HAVING fixes "Invalid use of group function" error !!
$st=$db->prepare("SELECT c.category_name, c.category_color,
                  COALESCE(SUM(e.amount),0) AS total
                  FROM categories c
                  LEFT JOIN expenses e ON e.category_id=c.category_id
                    AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=?
                  WHERE c.user_id=?
                  GROUP BY c.category_id
                  HAVING total > 0
                  ORDER BY total DESC");
$st->execute([$m,$y,$uid]); $catData=$st->fetchAll();

$barL=[]; $barA=[];
for ($i=5;$i>=0;$i--) {
    $dt=new DateTime("first day of -$i month"); $barL[]=$dt->format('M');
    $s2=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
    $s2->execute([$uid,(int)$dt->format('n'),(int)$dt->format('Y')]); $barA[]=(float)$s2->fetchColumn();
}

$st=$db->prepare("SELECT e.*,c.category_name,c.category_color FROM expenses e
                  LEFT JOIN categories c ON c.category_id=e.category_id
                  WHERE e.user_id=? ORDER BY e.expense_date DESC,e.created_at DESC LIMIT 5");
$st->execute([$uid]); $recent=$st->fetchAll();
$bf=$budgetPct>=100?'bg-red-400':($budgetPct>=90?'bg-amber-400':'bg-brand-mid');
?>

<div class="flex justify-between items-center mb-7">
  <div>
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
    <p class="text-sm text-stone-400 mt-0.5"><?= date('F Y') ?></p>
  </div>
  <a href="dashboard.php?p=expenses&act=add"
     class="flex items-center gap-1.5 bg-brand-mid hover:bg-brand text-white text-sm font-medium px-4 py-2.5 rounded-lg transition">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Add Expense
  </a>
</div>

<?php if ($budget>0 && $budgetPct>=90): ?>
<div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium border
            <?= $budgetPct>=100?'bg-red-50 text-red-700 border-red-200':'bg-amber-50 text-amber-700 border-amber-200' ?>">
  <?= $budgetPct>=100?'⚠ Budget exceeded! You spent more than your monthly limit.':'⚠ You\'ve used '.round($budgetPct).'% of your '.date('F').' budget.' ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $rem=$budget>0?max($budget-$monthTotal,0):null;
  $stats=[
    ['This Month',       tk($monthTotal), $budget>0&&$monthTotal>$budget?'text-red-500':'text-stone-800', true],
    ['Remaining Budget', $rem!==null?tk($rem):'—', $rem===null?'text-stone-400':($rem==0?'text-red-500':'text-brand-mid'), false],
    ['Expenses / Month', $expCount, 'text-stone-800', false],
    ['All-Time Spent',   tk($allTotal), 'text-stone-800', false],
  ];
  foreach ($stats as [$lbl,$val,$vcls,$showBar]):
  ?>
  <div class="bg-white rounded-xl border border-stone-200 p-4">
    <div class="text-xs font-semibold text-stone-400 uppercase tracking-wider mb-2"><?= $lbl ?></div>
    <div class="text-2xl font-semibold font-mono <?= $vcls ?>"><?= $val ?></div>
    <?php if ($showBar && $budget>0): ?>
    <div class="mt-2.5 h-1.5 bg-stone-100 rounded-full overflow-hidden">
      <div class="h-full rounded-full <?= $bf ?>" style="width:<?= $budgetPct ?>%"></div>
    </div>
    <div class="flex justify-between text-xs text-stone-400 mt-1">
      <span>৳0</span><span>Limit <?= tk($budget) ?></span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

  <!-- PIE -->
  <div class="bg-white rounded-xl border border-stone-200 p-5">
    <div class="text-sm font-semibold text-stone-700 mb-0.5">Spending by Category</div>
    <div class="text-xs text-stone-400 mb-4"><?= date('F Y') ?> · Total: <?= tk($monthTotal) ?></div>
    <?php if ($catData): ?>
    <div class="relative h-48 mb-4"><canvas id="pieChart"></canvas></div>
    <div class="space-y-2 mt-2">
      <?php foreach ($catData as $cd):
        $pct = $monthTotal>0 ? round(($cd['total']/$monthTotal)*100,1) : 0;
      ?>
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= e($cd['category_color']) ?>"></span>
        <span class="text-xs text-stone-600 flex-1 truncate"><?= e($cd['category_name']) ?></span>
        <div class="w-20 h-1.5 bg-stone-100 rounded-full overflow-hidden flex-shrink-0">
          <div class="h-full rounded-full" style="width:<?= $pct ?>%;background:<?= e($cd['category_color']) ?>"></div>
        </div>
        <span class="text-xs font-mono text-stone-400 w-8 text-right"><?= $pct ?>%</span>
        <span class="text-xs font-mono font-medium text-stone-700 w-20 text-right"><?= tk($cd['total']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="h-48 flex items-center justify-center text-sm text-stone-400">No expenses this month.</div>
    <?php endif; ?>
  </div>

  <!-- BAR -->
  <div class="bg-white rounded-xl border border-stone-200 p-5">
    <div class="text-sm font-semibold text-stone-700 mb-0.5">Last 6 Months</div>
    <div class="text-xs text-stone-400 mb-4">Monthly spending trend</div>
    <div class="relative h-48"><canvas id="barChart"></canvas></div>
  </div>
</div>

<!-- Recent -->
<div class="bg-white rounded-xl border border-stone-200 p-5">
  <div class="flex justify-between items-center mb-4">
    <div class="text-sm font-semibold text-stone-700">Recent Expenses</div>
    <a href="dashboard.php?p=expenses" class="text-xs text-brand-mid hover:underline">View all →</a>
  </div>
  <?php if ($recent): ?>
  <table class="w-full text-sm">
    <thead><tr class="border-b border-stone-100">
      <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider pb-2.5">Title</th>
      <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider pb-2.5">Category</th>
      <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider pb-2.5">Date</th>
      <th class="text-right text-xs font-semibold text-stone-400 uppercase tracking-wider pb-2.5">Amount</th>
    </tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
    <tr class="border-b border-stone-50 hover:bg-stone-50">
      <td class="py-3 font-medium text-stone-700"><?= e($r['title']) ?></td>
      <td class="py-3">
        <?php if ($r['category_name']): ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-stone-100 text-xs font-medium text-stone-600">
          <span class="w-1.5 h-1.5 rounded-full" style="background:<?= e($r['category_color']) ?>"></span>
          <?= e($r['category_name']) ?>
        </span>
        <?php else: ?><span class="text-stone-300">—</span><?php endif; ?>
      </td>
      <td class="py-3 text-stone-400 font-mono text-xs"><?= date('d M',strtotime($r['expense_date'])) ?></td>
      <td class="py-3 text-right font-mono font-medium text-stone-700"><?= tk($r['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-center py-10 text-sm text-stone-400">
    No expenses yet. <a href="dashboard.php?p=expenses&act=add" class="text-brand-mid hover:underline">Add first →</a>
  </div>
  <?php endif; ?>
</div>

<script>
const co={maintainAspectRatio:false,plugins:{legend:{labels:{font:{family:'DM Sans',size:11},color:'#78716c',boxWidth:9,padding:10}}}};
<?php if ($catData): ?>
new Chart(document.getElementById('pieChart'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_column($catData,'category_name')) ?>,
    datasets:[{
      data:<?= json_encode(array_column($catData,'total')) ?>,
      backgroundColor:<?= json_encode(array_column($catData,'category_color')) ?>,
      borderWidth:3, borderColor:'#fff', hoverOffset:8
    }]
  },
  options:{...co, cutout:'58%',
    plugins:{
      legend:{display:false},
      tooltip:{callbacks:{label:function(ctx){
        const tot=ctx.dataset.data.reduce((a,b)=>a+b,0);
        const pct=tot>0?((ctx.raw/tot)*100).toFixed(1):0;
        return ' ৳ '+ctx.raw.toLocaleString()+' ('+pct+'%)';
      }}}
    }
  }
});
<?php endif; ?>
new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{
    labels:<?= json_encode($barL) ?>,
    datasets:[{
      data:<?= json_encode($barA) ?>,
      backgroundColor:['#e74c3c','#3498db','#f39c12','#9b59b6','#1abc9c','#2d5a3d'],
      borderWidth:0, borderRadius:6
    }]
  },
  options:{...co,
    plugins:{legend:{display:false}},
    scales:{
      y:{beginAtZero:true,grid:{color:'#f5f5f4'},ticks:{color:'#a8a29e',font:{family:'DM Mono',size:10}}},
      x:{grid:{display:false},ticks:{color:'#a8a29e',font:{family:'DM Sans',size:11}}}
    }
  }
});
</script>

<?php
// ════════════════════════════════════════════════════════════
// EXPENSES
// ════════════════════════════════════════════════════════════
elseif ($page==='expenses'):
$sub=$_GET['act']??'list'; $editing=null;
if ($sub==='edit'&&isset($_GET['id'])){
    $st=$db->prepare("SELECT * FROM expenses WHERE expense_id=? AND user_id=?");
    $st->execute([(int)$_GET['id'],$uid]); $editing=$st->fetch();
}
$fm=$_GET['month']??''; $fc=$_GET['cat']??'';
$pg=max(1,(int)($_GET['pg']??1)); $pp=15;
$wh="WHERE e.user_id=?"; $pr=[$uid];
if($fm){[$fy,$fmo]=explode('-',$fm);$wh.=" AND YEAR(e.expense_date)=? AND MONTH(e.expense_date)=?";$pr[]=(int)$fy;$pr[]=(int)$fmo;}
if($fc){$wh.=" AND e.category_id=?";$pr[]=(int)$fc;}
$st=$db->prepare("SELECT COUNT(*) FROM expenses e $wh");$st->execute($pr);
$tot=(int)$st->fetchColumn();$pages=max(1,ceil($tot/$pp));$off=($pg-1)*$pp;
$st=$db->prepare("SELECT e.*,c.category_name,c.category_color FROM expenses e LEFT JOIN categories c ON c.category_id=e.category_id $wh ORDER BY e.expense_date DESC,e.created_at DESC LIMIT $pp OFFSET $off");
$st->execute($pr);$expenses=$st->fetchAll();
$st=$db->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e $wh");$st->execute($pr);$fSum=(float)$st->fetchColumn();
?>

<div class="flex justify-between items-center mb-7">
  <div>
    <h1 class="text-xl font-semibold tracking-tight">Expenses</h1>
    <p class="text-sm text-stone-400 mt-0.5"><?= $tot ?> records · <?= tk($fSum) ?></p>
  </div>
  <a href="dashboard.php?p=expenses&act=add"
     class="flex items-center gap-1.5 bg-brand-mid hover:bg-brand text-white text-sm font-medium px-4 py-2.5 rounded-lg transition">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Add Expense
  </a>
</div>

<?php if ($sub==='add'||$sub==='edit'): ?>
<div class="bg-white rounded-xl border border-stone-200 p-6 max-w-lg">
  <p class="text-sm font-semibold text-stone-700 mb-5"><?= $editing?'Edit Expense':'New Expense' ?></p>
  <form method="POST" action="dashboard.php">
    <input type="hidden" name="act" value="<?= $editing?'edit_expense':'add_expense' ?>">
    <?php if ($editing): ?><input type="hidden" name="expense_id" value="<?= $editing['expense_id'] ?>"><?php endif; ?>
    <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Title</label>
    <input type="text" name="title" value="<?= e($editing['title']??'') ?>" placeholder="e.g. Lunch, Rickshaw" required autofocus class="<?= $inp ?> mb-4">
    <div class="grid grid-cols-2 gap-3 mb-4">
      <div>
        <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Amount (৳)</label>
        <input type="number" name="amount" step="0.01" min="0.01" value="<?= $editing?$editing['amount']:'' ?>" placeholder="0.00" required class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Date</label>
        <input type="date" name="expense_date" value="<?= $editing?$editing['expense_date']:date('Y-m-d') ?>" required class="<?= $inp ?>">
      </div>
    </div>
    <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">
      Category <span class="normal-case font-normal text-stone-300 ml-1">optional</span>
    </label>
    <select name="category_id" class="<?= $inp ?> mb-5">
      <option value="">— No category —</option>
      <?php foreach ($allCats as $c): ?>
      <option value="<?= $c['category_id'] ?>" <?= ($editing&&$editing['category_id']==$c['category_id'])?'selected':'' ?>><?= e($c['category_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-brand-mid hover:bg-brand text-white transition"><?= $editing?'Update':'Save Expense' ?></button>
      <a href="dashboard.php?p=expenses" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-stone-100 hover:bg-stone-200 text-stone-700 transition">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<form method="GET" action="dashboard.php" class="flex gap-2.5 mb-5 flex-wrap">
  <input type="hidden" name="p" value="expenses">
  <input type="month" name="month" value="<?= e($fm) ?>" class="px-3 py-2 rounded-lg border border-stone-200 bg-white text-sm text-stone-700 focus:outline-none focus:border-brand-mid transition">
  <select name="cat" class="px-3 py-2 rounded-lg border border-stone-200 bg-white text-sm text-stone-700 focus:outline-none focus:border-brand-mid transition">
    <option value="">All Categories</option>
    <?php foreach ($allCats as $c): ?>
    <option value="<?= $c['category_id'] ?>" <?= $fc==$c['category_id']?'selected':'' ?>><?= e($c['category_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="px-4 py-2 rounded-lg bg-stone-100 hover:bg-stone-200 text-sm font-medium text-stone-700 transition">Filter</button>
  <?php if ($fm||$fc): ?>
  <a href="dashboard.php?p=expenses" class="px-4 py-2 rounded-lg bg-stone-100 hover:bg-stone-200 text-sm font-medium text-stone-600 transition">Clear</a>
  <?php endif; ?>
</form>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
  <?php if ($expenses): ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="border-b border-stone-100 bg-stone-50">
        <tr>
          <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Title</th>
          <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Category</th>
          <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Date</th>
          <th class="text-right text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Amount</th>
          <th class="px-5 py-3"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($expenses as $exp): ?>
      <tr class="border-b border-stone-50 hover:bg-stone-50">
        <td class="px-5 py-3.5 font-medium text-stone-700"><?= e($exp['title']) ?></td>
        <td class="px-5 py-3.5">
          <?php if ($exp['category_name']): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-stone-100 text-xs font-medium text-stone-600">
            <span class="w-1.5 h-1.5 rounded-full" style="background:<?= e($exp['category_color']) ?>"></span>
            <?= e($exp['category_name']) ?>
          </span>
          <?php else: ?><span class="text-stone-300">—</span><?php endif; ?>
        </td>
        <td class="px-5 py-3.5 text-stone-400 font-mono text-xs"><?= date('d M Y',strtotime($exp['expense_date'])) ?></td>
        <td class="px-5 py-3.5 text-right font-mono font-medium text-stone-700"><?= tk($exp['amount']) ?></td>
        <td class="px-5 py-3.5 text-right whitespace-nowrap">
          <a href="dashboard.php?p=expenses&act=edit&id=<?= $exp['expense_id'] ?>" class="text-xs font-medium text-stone-400 hover:text-brand-mid px-2 py-1 rounded hover:bg-brand-light transition mr-1">Edit</a>
          <form method="POST" action="dashboard.php" class="inline" onsubmit="return confirm('Delete this expense?')">
            <input type="hidden" name="act" value="del_expense">
            <input type="hidden" name="expense_id" value="<?= $exp['expense_id'] ?>">
            <button type="submit" class="text-xs font-medium text-stone-400 hover:text-red-500 px-2 py-1 rounded hover:bg-red-50 transition">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages>1): ?>
  <div class="flex justify-center gap-1 py-4 border-t border-stone-100">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="dashboard.php?p=expenses&pg=<?= $i ?>&month=<?= e($fm) ?>&cat=<?= e($fc) ?>"
       class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $i===$pg?'bg-brand-mid text-white':'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div class="text-center py-14 text-sm text-stone-400">
    No expenses found.
    <?php if (!$fm&&!$fc): ?><a href="dashboard.php?p=expenses&act=add" class="text-brand-mid hover:underline ml-1">Add first →</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════
// CATEGORIES
// ════════════════════════════════════════════════════════════
elseif ($page==='categories'):
$sub=$_GET['act']??'list'; $editCat=null;
if ($sub==='edit'&&isset($_GET['id'])){
    $st=$db->prepare("SELECT * FROM categories WHERE category_id=? AND user_id=?");
    $st->execute([(int)$_GET['id'],$uid]); $editCat=$st->fetch();
}
$st=$db->prepare("SELECT c.*,COUNT(e.expense_id) AS cnt,COALESCE(SUM(e.amount),0) AS spent
    FROM categories c LEFT JOIN expenses e ON e.category_id=c.category_id
    WHERE c.user_id=? GROUP BY c.category_id ORDER BY c.category_name");
$st->execute([$uid]); $cats=$st->fetchAll();
?>

<div class="flex justify-between items-center mb-7">
  <div>
    <h1 class="text-xl font-semibold tracking-tight">Categories</h1>
    <p class="text-sm text-stone-400 mt-0.5"><?= count($cats) ?> total</p>
  </div>
  <a href="dashboard.php?p=categories&act=add"
     class="flex items-center gap-1.5 bg-brand-mid hover:bg-brand text-white text-sm font-medium px-4 py-2.5 rounded-lg transition">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    New Category
  </a>
</div>

<?php if ($sub==='add'||$sub==='edit'): ?>
<div class="bg-white rounded-xl border border-stone-200 p-6 max-w-sm">
  <p class="text-sm font-semibold text-stone-700 mb-5"><?= $editCat?'Edit':'New' ?> Category</p>
  <form method="POST" action="dashboard.php">
    <input type="hidden" name="act" value="<?= $editCat?'edit_cat':'add_cat' ?>">
    <?php if ($editCat): ?><input type="hidden" name="cat_id" value="<?= $editCat['category_id'] ?>"><?php endif; ?>
    <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Name</label>
    <input type="text" name="cat_name" value="<?= e($editCat['category_name']??'') ?>" placeholder="e.g. Food, Travel" required autofocus class="<?= $inp ?> mb-4">
    <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-2">Colour</label>
    <input type="hidden" name="cat_color" id="catColor" value="<?= e($editCat['category_color']??'#e74c3c') ?>">
    <div class="flex flex-wrap gap-2 mb-5" id="colorPicker">
      <?php foreach ($COLORS as $c):
        $sel=($editCat&&$editCat['category_color']===$c)||(!$editCat&&$c==='#e74c3c');
      ?>
      <div onclick="pickColor('<?= $c ?>')" data-c="<?= $c ?>"
           class="w-6 h-6 rounded-full cursor-pointer transition-transform hover:scale-110 <?= $sel?'ring-2 ring-offset-1 ring-stone-700':'' ?>"
           style="background:<?= $c ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="flex gap-3">
      <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-brand-mid hover:bg-brand text-white transition"><?= $editCat?'Update':'Create' ?></button>
      <a href="dashboard.php?p=categories" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-stone-100 hover:bg-stone-200 text-stone-700 transition">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
  <?php if ($cats): ?>
  <table class="w-full text-sm">
    <thead class="border-b border-stone-100 bg-stone-50">
      <tr>
        <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Name</th>
        <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Expenses</th>
        <th class="text-left text-xs font-semibold text-stone-400 uppercase tracking-wider px-5 py-3">Total Spent</th>
        <th class="px-5 py-3"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
    <tr class="border-b border-stone-50 hover:bg-stone-50">
      <td class="px-5 py-3.5">
        <span class="inline-flex items-center gap-2 font-medium text-stone-700">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= e($c['category_color']) ?>"></span>
          <?= e($c['category_name']) ?>
        </span>
      </td>
      <td class="px-5 py-3.5 text-stone-400"><?= $c['cnt'] ?></td>
      <td class="px-5 py-3.5 font-mono text-stone-700"><?= tk($c['spent']) ?></td>
      <td class="px-5 py-3.5 text-right whitespace-nowrap">
        <a href="dashboard.php?p=categories&act=edit&id=<?= $c['category_id'] ?>" class="text-xs font-medium text-stone-400 hover:text-brand-mid px-2 py-1 rounded hover:bg-brand-light transition mr-1">Edit</a>
        <form method="POST" action="dashboard.php" class="inline" onsubmit="return confirm('Delete? Linked expenses will be uncategorized.')">
          <input type="hidden" name="act" value="del_cat">
          <input type="hidden" name="cat_id" value="<?= $c['category_id'] ?>">
          <button type="submit" class="text-xs font-medium text-stone-400 hover:text-red-500 px-2 py-1 rounded hover:bg-red-50 transition">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-center py-14 text-sm text-stone-400">No categories yet. <a href="dashboard.php?p=categories&act=add" class="text-brand-mid hover:underline ml-1">Create first →</a></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════
// PROFILE
// ════════════════════════════════════════════════════════════
elseif ($page==='profile'):
$st=$db->prepare("SELECT * FROM users WHERE user_id=?");$st->execute([$uid]);$u=$st->fetch();
$st=$db->prepare("SELECT monthly_limit FROM budgets WHERE user_id=? AND month=? AND year=?");$st->execute([$uid,$m,$y]);$curBudget=$st->fetchColumn()?:'';
?>

<div class="mb-7">
  <h1 class="text-xl font-semibold tracking-tight">Profile</h1>
  <p class="text-sm text-stone-400 mt-0.5">Manage your account</p>
</div>

<div class="flex flex-col gap-5 max-w-lg">

  <div class="bg-white rounded-xl border border-stone-200 p-6">
    <p class="text-sm font-semibold text-stone-700 pb-4 mb-5 border-b border-stone-100">Account Info</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_profile">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Full Name</label>
      <input type="text" name="user_name" value="<?= e($u['user_name']) ?>" required class="<?= $inp ?> mb-4">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Email</label>
      <input type="email" name="user_email" value="<?= e($u['user_email']) ?>" required class="<?= $inp ?> mb-4">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Phone</label>
      <input type="text" name="user_phone" value="<?= e($u['user_phone']??'') ?>" class="<?= $inp ?> mb-5">
      <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-brand-mid hover:bg-brand text-white transition">Save Changes</button>
    </form>
  </div>

  <div class="bg-white rounded-xl border border-stone-200 p-6">
    <p class="text-sm font-semibold text-stone-700 pb-4 mb-5 border-b border-stone-100">Monthly Budget</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_budget">
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div>
          <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Month</label>
          <select name="bmonth" class="<?= $inp ?>">
            <?php for ($i=1;$i<=12;$i++): ?><option value="<?= $i ?>" <?= $i==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$i,1)) ?></option><?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Year</label>
          <select name="byear" class="<?= $inp ?>">
            <?php for ($i=$y-1;$i<=$y+1;$i++): ?><option value="<?= $i ?>" <?= $i==$y?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
          </select>
        </div>
      </div>
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Monthly Limit (৳)</label>
      <input type="number" name="monthly_limit" step="0.01" min="0" value="<?= e($curBudget) ?>" placeholder="e.g. 15000" class="<?= $inp ?> mb-1">
      <p class="text-xs text-stone-400 mb-5">Set 0 to remove the limit.</p>
      <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-brand-mid hover:bg-brand text-white transition">Update Budget</button>
    </form>
  </div>

  <div class="bg-white rounded-xl border border-stone-200 p-6">
    <p class="text-sm font-semibold text-stone-700 pb-4 mb-5 border-b border-stone-100">Change Password</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_password">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Current Password</label>
      <input type="password" name="cur_pass" required class="<?= $inp ?> mb-4">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">New Password</label>
      <input type="password" name="new_pass" placeholder="Min 6 characters" required class="<?= $inp ?> mb-4">
      <label class="block text-xs font-semibold text-stone-400 uppercase tracking-wider mb-1.5">Confirm New Password</label>
      <input type="password" name="new_pass2" required class="<?= $inp ?> mb-5">
      <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium bg-brand-mid hover:bg-brand text-white transition">Change Password</button>
    </form>
  </div>

  <div class="pt-2 border-t border-stone-100">
    <a href="logout.php" class="inline-flex px-4 py-2.5 rounded-lg text-sm font-medium bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition">Sign Out</a>
  </div>

</div>

<?php endif; ?>
</main>
</div>

<script>
function pickColor(c) {
  const inp = document.getElementById('catColor');
  if (!inp) return;
  inp.value = c;
  document.querySelectorAll('#colorPicker div').forEach(d => {
    const s = d.dataset.c === c;
    d.classList.toggle('ring-2', s);
    d.classList.toggle('ring-offset-1', s);
    d.classList.toggle('ring-stone-700', s);
  });
}
</script>
</body>
</html>