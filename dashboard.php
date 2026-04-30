<?php
require_once 'db.php';
requireLogin();

$db=$db??getDB(); $db=getDB();
$uid=uid(); $page=$_GET['p']??'home';
$m=(int)date('n'); $y=(int)date('Y');
$user=$_SESSION['user'];

// ── SEED CATEGORIES ───────────────────────────────────────────
$chk=$db->prepare("SELECT COUNT(*) FROM categories WHERE user_id=?");
$chk->execute([$uid]);
if ((int)$chk->fetchColumn()===0) {
    $ins=$db->prepare("INSERT INTO categories (user_id,category_name,category_color) VALUES (?,?,?)");
    foreach ([['Food','#ef4444'],['Medical','#3b82f6'],['Travel','#06b6d4'],['Education','#8b5cf6'],
              ['Personal','#10b981'],['Family','#f97316'],['Entertainment','#ec4899'],['Living Cost','#f59e0b']] as [$n,$c])
        $ins->execute([$uid,$n,$c]);
}

// ── POST ACTIONS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['act']??'';

    if ($act==='add_expense'||$act==='edit_expense') {
        $title=trim($_POST['title']??''); $amount=(float)($_POST['amount']??0);
        $cat=($_POST['category_id']??'')?:null; $date=$_POST['expense_date']??date('Y-m-d');
        $eid=(int)($_POST['expense_id']??0);
        if (!$title||$amount<=0) { flash('Title and valid amount required.','danger'); }
        elseif ($eid) {
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

    if ($act==='del_expense') {
        $db->prepare("DELETE FROM expenses WHERE expense_id=? AND user_id=?")->execute([(int)$_POST['expense_id'],$uid]);
        flash('Expense deleted.');
        header("Location: dashboard.php?p=expenses"); exit;
    }

    if ($act==='add_cat'||$act==='edit_cat') {
        $name=trim($_POST['cat_name']??''); $color=$_POST['cat_color']??'#f59e0b'; $cid=(int)($_POST['cat_id']??0);
        if (!$name) { flash('Name required.','danger'); }
        elseif ($cid) {
            $db->prepare("UPDATE categories SET category_name=?,category_color=? WHERE category_id=? AND user_id=?")->execute([$name,$color,$cid,$uid]);
            flash('Category updated.');
        } else {
            $db->prepare("INSERT INTO categories (user_id,category_name,category_color) VALUES (?,?,?)")->execute([$uid,$name,$color]);
            flash('Category created.');
        }
        header("Location: dashboard.php?p=categories"); exit;
    }

    if ($act==='del_cat') {
        $cid=(int)$_POST['cat_id'];
        $db->prepare("UPDATE expenses SET category_id=NULL WHERE category_id=? AND user_id=?")->execute([$cid,$uid]);
        $db->prepare("DELETE FROM categories WHERE category_id=? AND user_id=?")->execute([$cid,$uid]);
        flash('Category deleted.');
        header("Location: dashboard.php?p=categories"); exit;
    }

    if ($act==='save_profile') {
        $name=trim($_POST['user_name']??''); $email=trim($_POST['user_email']??''); $phone=trim($_POST['user_phone']??'');
        $errs=[];
        if (!$name) $errs[]='Name required.';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errs[]='Valid email required.';
        if (!$errs) {
            $chk=$db->prepare("SELECT user_id FROM users WHERE user_email=? AND user_id!=?"); $chk->execute([$email,$uid]);
            if ($chk->fetch()) { flash('Email taken.','danger'); }
            else {
                $db->prepare("UPDATE users SET user_name=?,user_email=?,user_phone=? WHERE user_id=?")->execute([$name,$email,$phone,$uid]);
                $_SESSION['user']['user_name']=$name; $_SESSION['user']['user_email']=$email; $_SESSION['user']['user_phone']=$phone;
                $user=$_SESSION['user']; flash('Profile updated.');
            }
        } else { flash(implode(' ',$errs),'danger'); }
        header("Location: dashboard.php?p=profile"); exit;
    }

    if ($act==='save_password') {
        $cur=$_POST['cur_pass']??''; $new=$_POST['new_pass']??''; $new2=$_POST['new_pass2']??'';
        $st=$db->prepare("SELECT password FROM users WHERE user_id=?"); $st->execute([$uid]); $hash=$st->fetchColumn();
        if (!password_verify($cur,$hash))  flash('Current password incorrect.','danger');
        elseif (strlen($new)<6)            flash('New password needs 6+ chars.','danger');
        elseif ($new!==$new2)              flash('Passwords do not match.','danger');
        else { $db->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]); flash('Password changed.'); }
        header("Location: dashboard.php?p=profile"); exit;
    }

    if ($act==='save_budget') {
        $limit=(float)($_POST['monthly_limit']??0); $bm=(int)($_POST['bmonth']??$m); $by=(int)($_POST['byear']??$y);
        $db->prepare("INSERT INTO budgets (user_id,monthly_limit,month,year) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE monthly_limit=VALUES(monthly_limit)")
           ->execute([$uid,$limit,$bm,$by]);
        flash('Budget saved.');
        header("Location: dashboard.php?p=profile"); exit;
    }

    if ($act==='save_saving') {
        $type=$_POST['stype']??'deposit'; $amount=(float)($_POST['amount']??0); $note=trim($_POST['note']??'');
        if ($amount<=0) { flash('Enter a valid amount.','danger'); }
        else {
            if ($type==='withdraw') {
                $st=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END),0) FROM savings WHERE user_id=?");
                $st->execute([$uid]); $bal=(float)$st->fetchColumn();
                if ($amount>$bal) { flash('Insufficient savings balance.','danger'); header("Location: dashboard.php?p=savings"); exit; }
            }
            $db->prepare("INSERT INTO savings (user_id,type,amount,note) VALUES (?,?,?,?)")->execute([$uid,$type,$amount,$note]);
            flash($type==='deposit'?'Saved successfully!':'Withdrawn successfully.');
        }
        header("Location: dashboard.php?p=savings"); exit;
    }
}

// ── SHARED ────────────────────────────────────────────────────
$st=$db->prepare("SELECT monthly_limit FROM budgets WHERE user_id=? AND month=? AND year=?");
$st->execute([$uid,$m,$y]); $budget=(float)($st->fetchColumn()?:0);

$st=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$st->execute([$uid,$m,$y]); $monthTotal=(float)$st->fetchColumn();
$budgetPct=$budget>0?min(($monthTotal/$budget)*100,100):0;

$st=$db->prepare("SELECT * FROM categories WHERE user_id=? ORDER BY category_name");
$st->execute([$uid]); $allCats=$st->fetchAll();

$st=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END),0) FROM savings WHERE user_id=?");
$st->execute([$uid]); $savingsBalance=(float)$st->fetchColumn();

$flash=getFlash();
$COLORS=['#ef4444','#3b82f6','#06b6d4','#8b5cf6','#10b981','#f97316','#ec4899','#f59e0b','#14b8a6','#a855f7'];

$inp="w-full px-3.5 py-2.5 rounded-xl text-sm text-white placeholder-white/30 outline-none transition";
$inpS="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12)";
$inpF="border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)";
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SpendSmart</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{p:{DEFAULT:'#0f172a',2:'#1e293b',3:'#334155'},a:'#f59e0b',b:'#06b6d4',g:'#10b981',vio:'#8b5cf6'},fontFamily:{sans:['Inter','ui-sans-serif'],mono:['JetBrains Mono','ui-monospace']}}}}</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
body{background:linear-gradient(160deg,#0f172a 0%,#1e1b4b 40%,#0c1a2e 100%);min-height:100vh;color:#fff}
.sidebar{background:rgba(15,23,42,.9);backdrop-filter:blur(16px);border-right:1px solid rgba(255,255,255,.08)}
.card{background:rgba(255,255,255,.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.08);border-radius:1rem}
.card-bright{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:1rem}
.nav-active{background:rgba(245,158,11,.15);color:#f59e0b;border:1px solid rgba(245,158,11,.2)}
.nav-item{color:rgba(255,255,255,.5);border:1px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff}
.inp-field{width:100%;padding:.65rem .9rem;border-radius:.75rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#fff;font-size:.875rem;outline:none;transition:.2s;font-family:inherit}
.inp-field::placeholder{color:rgba(255,255,255,.3)}
.inp-field:focus{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.15)}
.inp-field option{background:#1e293b;color:#fff}
.btn-primary{background:#f59e0b;color:#0f172a;font-weight:700;border-radius:.75rem;padding:.65rem 1.25rem;font-size:.875rem;transition:.2s;border:none;cursor:pointer}
.btn-primary:hover{background:#fbbf24}
.btn-secondary{background:rgba(255,255,255,.08);color:#fff;font-weight:500;border-radius:.75rem;padding:.65rem 1.25rem;font-size:.875rem;transition:.2s;border:1px solid rgba(255,255,255,.12);cursor:pointer;text-decoration:none;display:inline-block}
.btn-secondary:hover{background:rgba(255,255,255,.14)}
.btn-danger{background:rgba(239,68,68,.2);color:#fca5a5;font-weight:500;border-radius:.75rem;padding:.65rem 1.25rem;font-size:.875rem;transition:.2s;border:1px solid rgba(239,68,68,.3);cursor:pointer}
.btn-danger:hover{background:rgba(239,68,68,.3)}
.glow-a{box-shadow:0 0 30px rgba(245,158,11,.2)}
.glow-g{box-shadow:0 0 30px rgba(16,185,129,.2)}
.stat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.25rem}
table{width:100%;border-collapse:collapse}
th{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.4);padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}
td{padding:.875rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);font-size:.8rem;color:rgba(255,255,255,.85)}
tr:hover td{background:rgba(255,255,255,.03)}
tr:last-child td{border-bottom:none}
::-webkit-scrollbar{width:4px} ::-webkit-scrollbar-track{background:transparent} ::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}
</style>
</head>
<body class="font-sans antialiased">
<div class="flex min-h-screen">

<!-- SIDEBAR -->
<aside class="sidebar w-60 fixed top-0 left-0 bottom-0 flex flex-col z-40">
  <div class="px-5 py-5 border-b border-white/8">
    <div class="font-bold text-lg tracking-tight"><span class="text-a">Spend</span>Smart</div>
    <div class="text-white/30 text-xs mt-0.5">Track Smart, Spend Better</div>
  </div>
  <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
    <?php
    $links=[
      ['home',      'Dashboard',  '🏠'],
      ['expenses',  'Expenses',   '💸'],
      ['categories','Categories', '🏷️'],
      ['savings',   'Savings',    '🏦'],
      ['profile',   'Profile',    '👤'],
    ];
    foreach ($links as [$key,$label,$ico]):
      $a=$page===$key;
    ?>
    <a href="dashboard.php?p=<?= $key ?>"
       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $a?'nav-active':'nav-item' ?>">
      <span class="text-base"><?= $ico ?></span><?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="px-4 py-4 border-t border-white/8">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-full bg-a/20 border border-a/30 text-a flex items-center justify-center text-xs font-bold flex-shrink-0">
        <?= strtoupper(substr($user['user_name'],0,1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-white truncate"><?= e($user['user_name']) ?></div>
        <a href="logout.php" class="text-xs text-white/30 hover:text-red-400 transition">Sign out</a>
      </div>
    </div>
  </div>
</aside>

<main class="ml-60 flex-1 p-8">

<?php if ($flash): ?>
<div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium
            <?= $flash['type']==='danger'?'bg-red-500/20 text-red-300 border border-red-500/30':'bg-g/20 text-g border border-g/30' ?>">
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════
// HOME
// ════════════════════════════════
if ($page==='home'):

$st=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=?");
$st->execute([$uid]); $allTotal=(float)$st->fetchColumn();
$st=$db->prepare("SELECT COUNT(*) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$st->execute([$uid,$m,$y]); $expCount=(int)$st->fetchColumn();
$st=$db->prepare("SELECT c.category_name,c.category_color,COALESCE(SUM(e.amount),0) AS total
    FROM categories c LEFT JOIN expenses e ON e.category_id=c.category_id
      AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=?
    WHERE c.user_id=? GROUP BY c.category_id HAVING total>0 ORDER BY total DESC");
$st->execute([$m,$y,$uid]); $catData=$st->fetchAll();
$barL=[]; $barA=[];
for ($i=5;$i>=0;$i--) {
    $dt=new DateTime("first day of -$i month"); $barL[]=$dt->format('M');
    $s2=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
    $s2->execute([$uid,(int)$dt->format('n'),(int)$dt->format('Y')]); $barA[]=(float)$s2->fetchColumn();
}
$st=$db->prepare("SELECT e.*,c.category_name,c.category_color FROM expenses e LEFT JOIN categories c ON c.category_id=e.category_id WHERE e.user_id=? ORDER BY e.expense_date DESC,e.created_at DESC LIMIT 5");
$st->execute([$uid]); $recent=$st->fetchAll();
$bf=$budgetPct>=100?'#ef4444':($budgetPct>=90?'#f59e0b':'#10b981');
?>

<div class="flex justify-between items-center mb-7">
  <div>
    <h1 class="text-2xl font-bold text-white">Dashboard</h1>
    <p class="text-white/40 text-sm mt-0.5"><?= date('l, F j, Y') ?></p>
  </div>
  <a href="dashboard.php?p=expenses&act=add" class="btn-primary flex items-center gap-2">
    <span class="text-base">+</span> Add Expense
  </a>
</div>

<?php if ($budget>0 && $budgetPct>=90): ?>
<div class="mb-6 px-4 py-3 rounded-xl text-sm font-semibold border bg-a/10 text-a border-a/30">
  ⚠ <?= $budgetPct>=100?'Budget exceeded!':'You\'ve used '.round($budgetPct).'% of your '.date('F').' budget.' ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $rem=$budget>0?max($budget-$monthTotal,0):null;
  $stats=[
    ['This Month', tk($monthTotal), '#ef4444', $budget>0&&$monthTotal>$budget],
    ['Remaining',  $rem!==null?tk($rem):'No limit', '#10b981', false],
    ['This Month', $expCount.' expenses', '#06b6d4', false],
    ['Savings', tk($savingsBalance), '#10b981', false],
  ];
  $labels=['💸 Spent','💰 Budget Left','📋 Count','🏦 Saved'];
  foreach ($stats as $i=>[$lbl,$val,$color,$warn]):
  ?>
  <div class="stat-card glow-<?= $i===3?'g':'a' ?>">
    <div class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-2"><?= $labels[$i] ?></div>
    <div class="text-2xl font-bold font-mono" style="color:<?= $warn?'#ef4444':$color ?>"><?= $val ?></div>
    <?php if ($i===0 && $budget>0): ?>
    <div class="mt-2.5 h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.1)">
      <div class="h-full rounded-full transition-all" style="width:<?= $budgetPct ?>%;background:<?= $bf ?>"></div>
    </div>
    <div class="flex justify-between text-xs text-white/30 mt-1"><span>৳0</span><span><?= tk($budget) ?></span></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <div class="card p-5">
    <div class="text-sm font-bold text-white mb-0.5">💹 Spending by Category</div>
    <div class="text-xs text-white/30 mb-4"><?= date('F Y') ?> · Total: <?= tk($monthTotal) ?></div>
    <?php if ($catData): ?>
    <div class="relative h-44 mb-4"><canvas id="pieChart"></canvas></div>
    <div class="space-y-2">
      <?php foreach ($catData as $cd):
        $pct=$monthTotal>0?round(($cd['total']/$monthTotal)*100,1):0;
      ?>
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= e($cd['category_color']) ?>"></span>
        <span class="text-xs text-white/70 flex-1 truncate"><?= e($cd['category_name']) ?></span>
        <div class="w-20 h-1.5 rounded-full overflow-hidden flex-shrink-0" style="background:rgba(255,255,255,.1)">
          <div class="h-full rounded-full" style="width:<?= $pct ?>%;background:<?= e($cd['category_color']) ?>"></div>
        </div>
        <span class="text-xs font-mono text-white/40 w-8 text-right"><?= $pct ?>%</span>
        <span class="text-xs font-mono font-bold text-white w-20 text-right"><?= tk($cd['total']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="h-44 flex items-center justify-center text-sm text-white/30">No expenses this month.</div>
    <?php endif; ?>
  </div>
  <div class="card p-5">
    <div class="text-sm font-bold text-white mb-0.5">📊 Last 6 Months</div>
    <div class="text-xs text-white/30 mb-4">Monthly spending trend</div>
    <div class="relative h-44"><canvas id="barChart"></canvas></div>
  </div>
</div>

<!-- Recent + Savings preview -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div class="card p-5 lg:col-span-2">
    <div class="flex justify-between items-center mb-4">
      <div class="text-sm font-bold text-white">🕐 Recent Expenses</div>
      <a href="dashboard.php?p=expenses" class="text-xs text-a hover:underline">View all →</a>
    </div>
    <?php if ($recent): ?>
    <table>
      <thead><tr>
        <th>Title</th><th>Category</th><th>Date</th><th style="text-align:right">Amount</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td class="font-medium text-white"><?= e($r['title']) ?></td>
        <td>
          <?php if ($r['category_name']): ?>
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium" style="background:<?= e($r['category_color']) ?>22;color:<?= e($r['category_color']) ?>;border:1px solid <?= e($r['category_color']) ?>44">
            <span class="w-1.5 h-1.5 rounded-full" style="background:<?= e($r['category_color']) ?>"></span>
            <?= e($r['category_name']) ?>
          </span>
          <?php else: ?><span class="text-white/20">—</span><?php endif; ?>
        </td>
        <td class="text-white/40 font-mono text-xs"><?= date('d M',strtotime($r['expense_date'])) ?></td>
        <td class="text-right font-mono font-bold text-a"><?= tk($r['amount']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="text-center py-8 text-sm text-white/30">No expenses yet. <a href="dashboard.php?p=expenses&act=add" class="text-a hover:underline">Add first →</a></div>
    <?php endif; ?>
  </div>

  <!-- Savings summary -->
  <div class="card p-5" style="background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(6,182,212,.08));border-color:rgba(16,185,129,.2)">
    <div class="text-sm font-bold text-white mb-1">🏦 Savings Vault</div>
    <div class="text-xs text-white/30 mb-5">Your saved money</div>
    <div class="text-4xl font-bold font-mono text-g mb-1"><?= tk($savingsBalance) ?></div>
    <div class="text-xs text-white/30 mb-6">Total balance</div>
    <a href="dashboard.php?p=savings" class="btn-primary w-full text-center block">Manage Savings</a>
    <?php
    $st=$db->prepare("SELECT type,amount,note,created_at FROM savings WHERE user_id=? ORDER BY created_at DESC LIMIT 3");
    $st->execute([$uid]); $recentSav=$st->fetchAll();
    if ($recentSav): ?>
    <div class="mt-4 pt-4 border-t border-white/8 space-y-2">
      <?php foreach ($recentSav as $s): ?>
      <div class="flex justify-between items-center">
        <div>
          <span class="text-xs <?= $s['type']==='deposit'?'text-g':'text-red-400' ?> font-semibold"><?= $s['type']==='deposit'?'↑ Deposit':'↓ Withdraw' ?></span>
          <?php if ($s['note']): ?><div class="text-xs text-white/30 truncate w-24"><?= e($s['note']) ?></div><?php endif; ?>
        </div>
        <span class="text-xs font-mono font-bold <?= $s['type']==='deposit'?'text-g':'text-red-400' ?>"><?= $s['type']==='deposit'?'+':'-' ?><?= tk($s['amount']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const co={maintainAspectRatio:false,plugins:{legend:{labels:{font:{family:'Inter',size:11},color:'rgba(255,255,255,.5)',boxWidth:9,padding:10}}}};
<?php if ($catData): ?>
new Chart(document.getElementById('pieChart'),{type:'doughnut',
  data:{labels:<?= json_encode(array_column($catData,'category_name')) ?>,
    datasets:[{data:<?= json_encode(array_column($catData,'total')) ?>,
      backgroundColor:<?= json_encode(array_column($catData,'category_color')) ?>,
      borderWidth:3,borderColor:'rgba(15,23,42,.8)',hoverOffset:8}]},
  options:{...co,cutout:'60%',plugins:{legend:{display:false},
    tooltip:{callbacks:{label:function(ctx){
      const tot=ctx.dataset.data.reduce((a,b)=>a+b,0);
      return ' ৳'+ctx.raw.toLocaleString()+' ('+((ctx.raw/tot)*100).toFixed(1)+'%)';
    }}}}}
});
<?php endif; ?>
new Chart(document.getElementById('barChart'),{type:'bar',
  data:{labels:<?= json_encode($barL) ?>,
    datasets:[{data:<?= json_encode($barA) ?>,
      backgroundColor:['rgba(239,68,68,.7)','rgba(59,130,246,.7)','rgba(6,182,212,.7)','rgba(139,92,246,.7)','rgba(16,185,129,.7)','rgba(245,158,11,.9)'],
      borderWidth:0,borderRadius:8}]},
  options:{...co,plugins:{legend:{display:false}},
    scales:{y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'rgba(255,255,255,.3)',font:{family:'JetBrains Mono',size:10}}},
      x:{grid:{display:false},ticks:{color:'rgba(255,255,255,.4)',font:{family:'Inter',size:11}}}}}
});
</script>

<?php
// ════════════════════════════════
// EXPENSES
// ════════════════════════════════
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
  <div><h1 class="text-2xl font-bold text-white">💸 Expenses</h1><p class="text-white/40 text-sm mt-0.5"><?= $tot ?> records · <?= tk($fSum) ?></p></div>
  <a href="dashboard.php?p=expenses&act=add" class="btn-primary">+ Add Expense</a>
</div>

<?php if ($sub==='add'||$sub==='edit'): ?>
<div class="card p-6 max-w-lg">
  <p class="text-sm font-bold text-white mb-5"><?= $editing?'Edit Expense':'New Expense' ?></p>
  <form method="POST" action="dashboard.php">
    <input type="hidden" name="act" value="<?= $editing?'edit_expense':'add_expense' ?>">
    <?php if ($editing): ?><input type="hidden" name="expense_id" value="<?= $editing['expense_id'] ?>"><?php endif; ?>
    <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Title</label>
    <input type="text" name="title" class="inp-field mb-4" value="<?= e($editing['title']??'') ?>" placeholder="e.g. Lunch, Rickshaw" required autofocus>
    <div class="grid grid-cols-2 gap-3 mb-4">
      <div>
        <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Amount (৳)</label>
        <input type="number" name="amount" step="0.01" min="0.01" class="inp-field" value="<?= $editing?$editing['amount']:'' ?>" placeholder="0.00" required>
      </div>
      <div>
        <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Date</label>
        <input type="date" name="expense_date" class="inp-field" value="<?= $editing?$editing['expense_date']:date('Y-m-d') ?>" required>
      </div>
    </div>
    <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Category <span class="normal-case font-normal text-white/20">optional</span></label>
    <select name="category_id" class="inp-field mb-5">
      <option value="">— No category —</option>
      <?php foreach ($allCats as $c): ?>
      <option value="<?= $c['category_id'] ?>" <?= ($editing&&$editing['category_id']==$c['category_id'])?'selected':'' ?>><?= e($c['category_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="flex gap-3">
      <button type="submit" class="btn-primary"><?= $editing?'Update':'Save Expense' ?></button>
      <a href="dashboard.php?p=expenses" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<form method="GET" action="dashboard.php" class="flex gap-2.5 mb-5 flex-wrap">
  <input type="hidden" name="p" value="expenses">
  <input type="month" name="month" value="<?= e($fm) ?>" class="inp-field" style="width:auto;padding:.5rem .75rem">
  <select name="cat" class="inp-field" style="width:auto;padding:.5rem .75rem">
    <option value="">All Categories</option>
    <?php foreach ($allCats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $fc==$c['category_id']?'selected':'' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="btn-secondary" style="padding:.5rem 1rem">Filter</button>
  <?php if ($fm||$fc): ?><a href="dashboard.php?p=expenses" class="btn-secondary" style="padding:.5rem 1rem">Clear</a><?php endif; ?>
</form>

<div class="card overflow-hidden">
  <?php if ($expenses): ?>
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Title</th><th>Category</th><th>Date</th><th style="text-align:right">Amount</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($expenses as $exp): ?>
      <tr>
        <td class="font-semibold text-white"><?= e($exp['title']) ?></td>
        <td><?php if ($exp['category_name']): ?>
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium" style="background:<?= e($exp['category_color']) ?>22;color:<?= e($exp['category_color']) ?>;border:1px solid <?= e($exp['category_color']) ?>44">
            <span class="w-1.5 h-1.5 rounded-full" style="background:<?= e($exp['category_color']) ?>"></span><?= e($exp['category_name']) ?>
          </span>
          <?php else: ?><span class="text-white/20">—</span><?php endif; ?></td>
        <td class="text-white/40 font-mono text-xs"><?= date('d M Y',strtotime($exp['expense_date'])) ?></td>
        <td class="text-right font-mono font-bold text-a"><?= tk($exp['amount']) ?></td>
        <td class="text-right whitespace-nowrap">
          <a href="dashboard.php?p=expenses&act=edit&id=<?= $exp['expense_id'] ?>" class="text-xs text-white/40 hover:text-a px-2 py-1 rounded transition mr-1">Edit</a>
          <form method="POST" action="dashboard.php" class="inline" onsubmit="return confirm('Delete?')">
            <input type="hidden" name="act" value="del_expense">
            <input type="hidden" name="expense_id" value="<?= $exp['expense_id'] ?>">
            <button type="submit" class="text-xs text-white/40 hover:text-red-400 px-2 py-1 rounded transition">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages>1): ?>
  <div class="flex justify-center gap-1 py-4" style="border-top:1px solid rgba(255,255,255,.08)">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="dashboard.php?p=expenses&pg=<?= $i ?>&month=<?= e($fm) ?>&cat=<?= e($fc) ?>"
       class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $i===$pg?'bg-a text-p font-bold':'text-white/50 hover:text-white' ?>"
       style="<?= $i===$pg?'':'background:rgba(255,255,255,.06)' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div class="text-center py-14 text-sm text-white/30">No expenses. <?php if (!$fm&&!$fc): ?><a href="dashboard.php?p=expenses&act=add" class="text-a hover:underline">Add first →</a><?php endif; ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════
// CATEGORIES
// ════════════════════════════════
elseif ($page==='categories'):
$sub=$_GET['act']??'list'; $editCat=null;
if ($sub==='edit'&&isset($_GET['id'])){
    $st=$db->prepare("SELECT * FROM categories WHERE category_id=? AND user_id=?");
    $st->execute([(int)$_GET['id'],$uid]); $editCat=$st->fetch();
}
$st=$db->prepare("SELECT c.*,COUNT(e.expense_id) AS cnt,COALESCE(SUM(e.amount),0) AS spent FROM categories c LEFT JOIN expenses e ON e.category_id=c.category_id WHERE c.user_id=? GROUP BY c.category_id ORDER BY c.category_name");
$st->execute([$uid]); $cats=$st->fetchAll();
?>

<div class="flex justify-between items-center mb-7">
  <div><h1 class="text-2xl font-bold text-white">🏷️ Categories</h1><p class="text-white/40 text-sm mt-0.5"><?= count($cats) ?> total</p></div>
  <a href="dashboard.php?p=categories&act=add" class="btn-primary">+ New Category</a>
</div>

<?php if ($sub==='add'||$sub==='edit'): ?>
<div class="card p-6 max-w-sm">
  <p class="text-sm font-bold text-white mb-5"><?= $editCat?'Edit':'New' ?> Category</p>
  <form method="POST" action="dashboard.php">
    <input type="hidden" name="act" value="<?= $editCat?'edit_cat':'add_cat' ?>">
    <?php if ($editCat): ?><input type="hidden" name="cat_id" value="<?= $editCat['category_id'] ?>"><?php endif; ?>
    <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Name</label>
    <input type="text" name="cat_name" class="inp-field mb-4" value="<?= e($editCat['category_name']??'') ?>" placeholder="e.g. Food, Travel" required autofocus>
    <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-2">Colour</label>
    <input type="hidden" name="cat_color" id="catColor" value="<?= e($editCat['category_color']??'#f59e0b') ?>">
    <div class="flex flex-wrap gap-2 mb-5" id="colorPicker">
      <?php foreach ($COLORS as $c):
        $sel=($editCat&&$editCat['category_color']===$c)||(!$editCat&&$c==='#f59e0b');
      ?>
      <div onclick="pickColor('<?= $c ?>')" data-c="<?= $c ?>"
           class="w-7 h-7 rounded-full cursor-pointer transition-transform hover:scale-110 <?= $sel?'ring-2 ring-offset-2 ring-white':'' ?>"
           style="background:<?= $c ?>;ring-offset-color:#0f172a"></div>
      <?php endforeach; ?>
    </div>
    <div class="flex gap-3">
      <button type="submit" class="btn-primary"><?= $editCat?'Update':'Create' ?></button>
      <a href="dashboard.php?p=categories" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php else: ?>
<div class="card overflow-hidden">
  <?php if ($cats): ?>
  <table>
    <thead><tr><th>Name</th><th>Expenses</th><th>Total Spent</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
    <tr>
      <td><span class="inline-flex items-center gap-2 font-semibold text-white"><span class="w-3 h-3 rounded-full" style="background:<?= e($c['category_color']) ?>"></span><?= e($c['category_name']) ?></span></td>
      <td class="text-white/40"><?= $c['cnt'] ?></td>
      <td class="font-mono font-bold text-a"><?= tk($c['spent']) ?></td>
      <td class="text-right whitespace-nowrap">
        <a href="dashboard.php?p=categories&act=edit&id=<?= $c['category_id'] ?>" class="text-xs text-white/40 hover:text-a px-2 py-1 rounded transition mr-1">Edit</a>
        <form method="POST" action="dashboard.php" class="inline" onsubmit="return confirm('Delete category?')">
          <input type="hidden" name="act" value="del_cat"><input type="hidden" name="cat_id" value="<?= $c['category_id'] ?>">
          <button type="submit" class="text-xs text-white/40 hover:text-red-400 px-2 py-1 rounded transition">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-center py-14 text-sm text-white/30">No categories. <a href="dashboard.php?p=categories&act=add" class="text-a hover:underline">Create first →</a></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════
// SAVINGS
// ════════════════════════════════
elseif ($page==='savings'):
$st=$db->prepare("SELECT * FROM savings WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$st->execute([$uid]); $savLogs=$st->fetchAll();
$st=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_dep, COALESCE(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_with FROM savings WHERE user_id=?");
$st->execute([$uid]); $savStats=$st->fetch();
?>

<div class="flex justify-between items-center mb-7">
  <div><h1 class="text-2xl font-bold text-white">🏦 Savings Vault</h1><p class="text-white/40 text-sm mt-0.5">Deposit & withdraw anytime</p></div>
</div>

<!-- Balance cards -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="card p-5" style="background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(6,182,212,.08));border-color:rgba(16,185,129,.25)">
    <div class="text-xs font-semibold text-g/70 uppercase tracking-wider mb-2">💰 Current Balance</div>
    <div class="text-3xl font-bold font-mono text-g"><?= tk($savingsBalance) ?></div>
  </div>
  <div class="card p-5">
    <div class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-2">↑ Total Deposited</div>
    <div class="text-2xl font-bold font-mono text-g"><?= tk($savStats['total_dep']) ?></div>
  </div>
  <div class="card p-5">
    <div class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-2">↓ Total Withdrawn</div>
    <div class="text-2xl font-bold font-mono text-red-400"><?= tk($savStats['total_with']) ?></div>
  </div>
</div>

<!-- Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <!-- Deposit -->
  <div class="card p-6" style="border-color:rgba(16,185,129,.2)">
    <div class="text-sm font-bold text-g mb-4">↑ Add to Savings</div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_saving">
      <input type="hidden" name="stype" value="deposit">
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Amount (৳)</label>
      <input type="number" name="amount" class="inp-field mb-3" step="0.01" min="0.01" placeholder="0.00" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Note <span class="normal-case font-normal text-white/20">optional</span></label>
      <input type="text" name="note" class="inp-field mb-4" placeholder="e.g. Monthly savings, Emergency fund">
      <button type="submit" class="btn-primary w-full" style="background:#10b981;color:#fff;text-align:center">↑ Deposit</button>
    </form>
  </div>
  <!-- Withdraw -->
  <div class="card p-6" style="border-color:rgba(239,68,68,.2)">
    <div class="text-sm font-bold text-red-400 mb-4">↓ Withdraw from Savings</div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_saving">
      <input type="hidden" name="stype" value="withdraw">
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Amount (৳)</label>
      <input type="number" name="amount" class="inp-field mb-3" step="0.01" min="0.01" placeholder="0.00" max="<?= $savingsBalance ?>" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Note <span class="normal-case font-normal text-white/20">optional</span></label>
      <input type="text" name="note" class="inp-field mb-4" placeholder="e.g. Medical emergency, Travel">
      <div class="text-xs text-white/30 mb-4">Available: <span class="font-bold text-g font-mono"><?= tk($savingsBalance) ?></span></div>
      <button type="submit" class="w-full text-center font-bold py-2.5 rounded-xl text-sm transition" style="background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)" onclick="return confirm('Withdraw from savings?')">↓ Withdraw</button>
    </form>
  </div>
</div>

<!-- Transaction history -->
<div class="card overflow-hidden">
  <div class="px-5 py-4" style="border-bottom:1px solid rgba(255,255,255,.08)">
    <div class="text-sm font-bold text-white">Transaction History</div>
  </div>
  <?php if ($savLogs): ?>
  <table>
    <thead><tr><th>Type</th><th>Note</th><th>Date & Time</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($savLogs as $s): ?>
    <tr>
      <td>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold
                     <?= $s['type']==='deposit'?'bg-g/20 text-g':'bg-red-500/20 text-red-400' ?>">
          <?= $s['type']==='deposit'?'↑ Deposit':'↓ Withdraw' ?>
        </span>
      </td>
      <td class="text-white/50"><?= $s['note'] ? e($s['note']) : '—' ?></td>
      <td class="text-white/30 font-mono text-xs"><?= date('d M Y, h:i A',strtotime($s['created_at'])) ?></td>
      <td class="text-right font-mono font-bold <?= $s['type']==='deposit'?'text-g':'text-red-400' ?>">
        <?= $s['type']==='deposit'?'+':'-' ?><?= tk($s['amount']) ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-center py-14 text-sm text-white/30">No transactions yet. Make your first deposit above.</div>
  <?php endif; ?>
</div>

<?php
// ════════════════════════════════
// PROFILE
// ════════════════════════════════
elseif ($page==='profile'):
$st=$db->prepare("SELECT * FROM users WHERE user_id=?");$st->execute([$uid]);$u=$st->fetch();
$st=$db->prepare("SELECT monthly_limit FROM budgets WHERE user_id=? AND month=? AND year=?");$st->execute([$uid,$m,$y]);$curBudget=$st->fetchColumn()?:'';
?>

<div class="mb-7"><h1 class="text-2xl font-bold text-white">👤 Profile</h1><p class="text-white/40 text-sm mt-0.5">Manage your account</p></div>

<div class="flex flex-col gap-5 max-w-lg">

  <div class="card p-6">
    <p class="text-sm font-bold text-white pb-4 mb-5" style="border-bottom:1px solid rgba(255,255,255,.08)">Account Info</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_profile">
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Full Name</label>
      <input type="text" name="user_name" class="inp-field mb-4" value="<?= e($u['user_name']) ?>" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Email</label>
      <input type="email" name="user_email" class="inp-field mb-4" value="<?= e($u['user_email']) ?>" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Phone</label>
      <input type="text" name="user_phone" class="inp-field mb-5" value="<?= e($u['user_phone']??'') ?>">
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>

  <div class="card p-6">
    <p class="text-sm font-bold text-white pb-4 mb-5" style="border-bottom:1px solid rgba(255,255,255,.08)">Monthly Budget</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_budget">
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div>
          <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Month</label>
          <select name="bmonth" class="inp-field">
            <?php for ($i=1;$i<=12;$i++): ?><option value="<?= $i ?>" <?= $i==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$i,1)) ?></option><?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Year</label>
          <select name="byear" class="inp-field">
            <?php for ($i=$y-1;$i<=$y+1;$i++): ?><option value="<?= $i ?>" <?= $i==$y?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
          </select>
        </div>
      </div>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Monthly Limit (৳)</label>
      <input type="number" name="monthly_limit" class="inp-field mb-1" step="0.01" min="0" value="<?= e($curBudget) ?>" placeholder="e.g. 15000">
      <p class="text-xs text-white/20 mb-5">Set 0 to remove the limit.</p>
      <button type="submit" class="btn-primary">Update Budget</button>
    </form>
  </div>

  <div class="card p-6">
    <p class="text-sm font-bold text-white pb-4 mb-5" style="border-bottom:1px solid rgba(255,255,255,.08)">Change Password</p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="act" value="save_password">
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Current Password</label>
      <input type="password" name="cur_pass" class="inp-field mb-4" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">New Password</label>
      <input type="password" name="new_pass" class="inp-field mb-4" placeholder="Min 6 characters" required>
      <label class="block text-xs font-semibold text-white/40 uppercase tracking-wider mb-1.5">Confirm New Password</label>
      <input type="password" name="new_pass2" class="inp-field mb-5" required>
      <button type="submit" class="btn-primary">Change Password</button>
    </form>
  </div>

  <div style="padding-top:.75rem;border-top:1px solid rgba(255,255,255,.08)">
    <a href="logout.php" class="btn-danger">Sign Out</a>
  </div>

</div>

<?php endif; ?>
</main>
</div>

<script>
function pickColor(c){
  const inp=document.getElementById('catColor');
  if(!inp)return; inp.value=c;
  document.querySelectorAll('#colorPicker div').forEach(d=>{
    const s=d.dataset.c===c;
    d.classList.toggle('ring-2',s);
    d.classList.toggle('ring-offset-2',s);
    d.classList.toggle('ring-white',s);
  });
}
</script>
</body>
</html>
