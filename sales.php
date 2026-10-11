<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = db();

/* Void a sale (admin) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf();
    $saleId = (int) ($_POST['sale_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, receipt_no, status FROM sales WHERE id = ? FOR UPDATE");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale || $sale['status'] !== 'completed') {
            throw new RuntimeException('That sale cannot be voided.');
        }
        $pdo->prepare("UPDATE sales SET status='voided' WHERE id = ?")->execute([$saleId]);
        $items = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ? AND product_id IS NOT NULL");
        $items->execute([$saleId]);
        $upd = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity_change, note) VALUES (?,?, 'void', ?, ?)");
        foreach ($items->fetchAll() as $it) {
            $upd->execute([$it['quantity'], $it['product_id']]);
            $mov->execute([$it['product_id'], current_user()['id'], $it['quantity'], 'Void ' . $sale['receipt_no']]);
        }
        $pdo->commit();
        flash('success', 'Voided ' . $sale['receipt_no'] . '. Stock was returned to inventory.');
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', $ex->getMessage());
    }
    redirect('/sales.php');
}

$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? $d : null;
$from = $valid($_GET['from'] ?? null) ?? date('Y-m-d', strtotime('-6 days'));
$to   = $valid($_GET['to'] ?? null) ?? date('Y-m-d');

$where  = 'DATE(s.created_at) BETWEEN :from AND :to';
$params = [':from' => $from, ':to' => $to];
if (!is_admin()) {
    $where .= ' AND s.user_id = :uid';
    $params[':uid'] = current_user()['id'];
}

$stmt = $pdo->prepare("SELECT s.*, u.full_name FROM sales s JOIN users u ON u.id = s.user_id
                       WHERE $where ORDER BY s.id DESC LIMIT 300");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sum = 0.0; $count = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'completed') { $sum += (float) $r['total']; $count++; }
}

$pageTitle = 'Sales';
$active = 'sales';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Sales</h1><p class="text-muted mb-0"><?= $count ?> completed · <?= money($sum) ?></p></div>
    <form class="filter-form" method="get">
        <input type="date" class="form-control" name="from" value="<?= e($from) ?>" aria-label="From date">
        <input type="date" class="form-control" name="to" value="<?= e($to) ?>" aria-label="To date">
        <button class="btn btn-primary">Filter</button>
    </form>
</div>

<div class="panel">
<?php if (!$rows): ?>
    <p class="empty">No sales between these dates.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Receipt</th><th>Date</th><th>Cashier</th><th>Payment</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['status'] === 'voided' ? 'row-void' : '' ?>">
                    <td><a href="<?= e(url('/receipt.php?id=' . $r['id'])) ?>"><?= e($r['receipt_no']) ?></a></td>
                    <td><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e(ucfirst($r['payment_method'])) ?></td>
                    <td class="text-end num"><?= money($r['total']) ?></td>
                    <td><span class="chip <?= $r['status'] === 'completed' ? 'ok' : 'out' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= e(url('/receipt.php?id=' . $r['id'])) ?>">Receipt</a>
                        <?php if (is_admin() && $r['status'] === 'completed'): ?>
                            <form method="post" class="d-inline" data-confirm="Void <?= e($r['receipt_no']) ?>? Stock will be returned to inventory.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sale_id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Void</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
