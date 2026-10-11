<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo    = db();
$admin  = is_admin();
$uw     = $admin ? '' : ' AND s.user_id = :uid';
$params = $admin ? [] : [':uid' => current_user()['id']];

/* Today's totals */
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total),0) AS revenue
                       FROM sales s WHERE s.status='completed' AND DATE(s.created_at)=CURDATE() $uw");
$stmt->execute($params);
$today = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si
                       JOIN sales s ON s.id = si.sale_id
                       WHERE s.status='completed' AND DATE(s.created_at)=CURDATE() $uw");
$stmt->execute($params);
$itemsToday = (int) $stmt->fetchColumn();

$avgTicket = $today['cnt'] > 0 ? $today['revenue'] / $today['cnt'] : 0;

/* Last 7 days */
$stmt = $pdo->prepare("SELECT DATE(s.created_at) AS d, SUM(s.total) AS t
                       FROM sales s WHERE s.status='completed'
                       AND s.created_at >= CURDATE() - INTERVAL 6 DAY $uw
                       GROUP BY DATE(s.created_at)");
$stmt->execute($params);
$byDay = array_column($stmt->fetchAll(), 't', 'd');
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($d));
    $chartValues[] = round((float) ($byDay[$d] ?? 0), 2);
}

/* Best sellers (7 days) */
$stmt = $pdo->prepare("SELECT si.product_name, SUM(si.quantity) AS qty, SUM(si.line_total) AS rev
                       FROM sale_items si JOIN sales s ON s.id = si.sale_id
                       WHERE s.status='completed' AND s.created_at >= CURDATE() - INTERVAL 6 DAY $uw
                       GROUP BY si.product_name ORDER BY qty DESC LIMIT 5");
$stmt->execute($params);
$top = $stmt->fetchAll();

/* Recent sales */
$stmt = $pdo->prepare("SELECT s.id, s.receipt_no, s.total, s.status, s.created_at, u.full_name
                       FROM sales s JOIN users u ON u.id = s.user_id
                       WHERE 1=1 $uw ORDER BY s.id DESC LIMIT 6");
$stmt->execute($params);
$recent = $stmt->fetchAll();

/* Low stock (admin) */
$low = [];
if ($admin) {
    $low = $pdo->query("SELECT id, name, stock, reorder_level FROM products
                        WHERE active=1 AND stock <= reorder_level
                        ORDER BY stock ASC, name ASC LIMIT 8")->fetchAll();
}

$pageTitle = 'Dashboard';
$active = 'dashboard';
$pageScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', url('/assets/js/dashboard.js')];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hello, <?= e(explode(' ', current_user()['full_name'])[0]) ?></h1>
        <p class="text-muted mb-0"><?= date('l, F j') ?><?= $admin ? '' : ' · showing your own sales' ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/pos.php')) ?>"><i class="bi bi-bag-check me-1"></i> New sale</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat"><span class="stat-label">Sales today</span><span class="stat-value"><?= money($today['revenue']) ?></span></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><span class="stat-label">Transactions</span><span class="stat-value"><?= (int) $today['cnt'] ?></span></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><span class="stat-label">Items sold</span><span class="stat-value"><?= $itemsToday ?></span></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><span class="stat-label">Average sale</span><span class="stat-value"><?= money($avgTicket) ?></span></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="panel h-100">
            <div class="panel-head"><h2>Sales, last 7 days</h2></div>
            <div class="chart-box"><canvas id="salesChart"
                data-labels='<?= e(json_encode($chartLabels)) ?>'
                data-values='<?= e(json_encode($chartValues)) ?>'
                data-currency="<?= e(CURRENCY) ?>"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel h-100">
            <div class="panel-head"><h2>Best sellers</h2><span class="text-muted small">7 days</span></div>
            <?php if (!$top): ?>
                <p class="empty">No sales yet. Ring up your first sale to see best sellers here.</p>
            <?php else: ?>
                <ul class="rank-list">
                    <?php foreach ($top as $t): ?>
                        <li><span><?= e($t['product_name']) ?></span><strong><?= (int) $t['qty'] ?> sold</strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-<?= $admin ? '7' : '12' ?>">
        <div class="panel h-100">
            <div class="panel-head"><h2>Recent sales</h2><a href="<?= e(url('/sales.php')) ?>" class="small">View all</a></div>
            <?php if (!$recent): ?>
                <p class="empty">No sales recorded yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Receipt</th><th>Time</th><th>Cashier</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a href="<?= e(url('/receipt.php?id=' . $r['id'])) ?>"><?= e($r['receipt_no']) ?></a>
                                <?php if ($r['status'] === 'voided'): ?><span class="chip out">Voided</span><?php endif; ?></td>
                            <td><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></td>
                            <td><?= e($r['full_name']) ?></td>
                            <td class="text-end num"><?= money($r['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($admin): ?>
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-head"><h2>Needs restocking</h2><a href="<?= e(url('/inventory.php?filter=low')) ?>" class="small">Open inventory</a></div>
            <?php if (!$low): ?>
                <p class="empty">Every product is above its reorder level.</p>
            <?php else: ?>
                <ul class="rank-list">
                    <?php foreach ($low as $p): ?>
                        <li><span><?= e($p['name']) ?></span>
                            <span class="chip <?= stock_class((int)$p['stock'], (int)$p['reorder_level']) ?>"><?= (int) $p['stock'] ?> left</span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
