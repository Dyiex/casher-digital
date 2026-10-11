<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id   = (int) ($_POST['product_id'] ?? 0);
    $mode = $_POST['mode'] ?? 'in';
    $qty  = (int) ($_POST['quantity'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    try {
        if (!in_array($mode, ['in', 'out', 'set'], true) || $qty < 0 || ($mode !== 'set' && $qty === 0)) {
            throw new RuntimeException('Enter a quantity greater than zero.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) throw new RuntimeException('Product not found.');

        $change = match ($mode) {
            'in'  => $qty,
            'out' => -$qty,
            'set' => $qty - (int) $p['stock'],
        };
        if ((int) $p['stock'] + $change < 0) {
            throw new RuntimeException('You cannot remove more than the ' . $p['stock'] . ' in stock.');
        }
        if ($change !== 0) {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$change, $id]);
            $type = $mode === 'in' ? 'purchase' : 'adjustment';
            $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity_change, note) VALUES (?,?,?,?,?)")
                ->execute([$id, current_user()['id'], $type, $change, $note !== '' ? $note : null]);
        }
        $pdo->commit();
        flash('success', 'Stock updated for ' . $p['name'] . '.');
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', $ex->getMessage());
    }
    redirect('/inventory.php' . (!empty($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
}

$filter = ($_GET['filter'] ?? '') === 'low' ? 'low' : 'all';
$where  = 'p.active = 1' . ($filter === 'low' ? ' AND p.stock <= p.reorder_level' : '');
$products = $pdo->query("SELECT p.*, c.name AS category FROM products p
                         LEFT JOIN categories c ON c.id = p.category_id
                         WHERE $where ORDER BY p.stock ASC, p.name")->fetchAll();

$stockValue = (float) $pdo->query("SELECT COALESCE(SUM(stock * cost),0) FROM products WHERE active = 1")->fetchColumn();
$lowCount   = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE active = 1 AND stock <= reorder_level")->fetchColumn();

$moves = $pdo->query("SELECT m.*, p.name AS product, u.full_name
                      FROM stock_movements m
                      JOIN products p ON p.id = m.product_id
                      LEFT JOIN users u ON u.id = m.user_id
                      ORDER BY m.id DESC LIMIT 15")->fetchAll();

$pageTitle = 'Inventory';
$active = 'inventory';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Inventory</h1><p class="text-muted mb-0">Stock on hand is worth <?= money($stockValue) ?> at cost.</p></div>
    <div class="btn-group" role="group">
        <a class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/inventory.php')) ?>">All items</a>
        <a class="btn <?= $filter === 'low' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/inventory.php?filter=low')) ?>">Low stock (<?= $lowCount ?>)</a>
    </div>
</div>

<div class="panel mb-3">
<?php if (!$products): ?>
    <p class="empty">Nothing needs restocking right now.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Product</th><th>Category</th><th class="text-end">Reorder at</th><th class="text-end">On hand</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong><br><small class="text-muted"><?= e($p['sku']) ?></small></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td class="text-end"><?= (int) $p['reorder_level'] ?></td>
                    <td class="text-end"><span class="chip <?= stock_class((int) $p['stock'], (int) $p['reorder_level']) ?>"><?= (int) $p['stock'] ?></span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#stockModal"
                                data-title="Update stock · <?= e($p['name']) ?>"
                                data-record="<?= e(json_encode(['product_id' => $p['id']])) ?>">Update stock</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<div class="panel">
    <div class="panel-head"><h2>Recent stock changes</h2></div>
    <?php if (!$moves): ?>
        <p class="empty">No stock changes yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>When</th><th>Product</th><th>Type</th><th class="text-end">Change</th><th>Note</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($moves as $m): ?>
                <tr>
                    <td><?= e(date('M j, g:i A', strtotime($m['created_at']))) ?></td>
                    <td><?= e($m['product']) ?></td>
                    <td><?= e(ucfirst($m['type'])) ?></td>
                    <td class="text-end num <?= $m['quantity_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['quantity_change'] > 0 ? '+' : '' ?><?= (int) $m['quantity_change'] ?></td>
                    <td><?= e($m['note'] ?? '') ?></td>
                    <td><?= e($m['full_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content" data-fillable>
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-title>Update stock</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label">What happened?</label>
                    <select class="form-select" name="mode">
                        <option value="in">Received new stock (add)</option>
                        <option value="out">Damaged, expired or lost (remove)</option>
                        <option value="set">Recount shelf (set exact number)</option>
                    </select></div>
                <div class="col-12"><label class="form-label">Quantity</label><input class="form-control" type="number" name="quantity" min="0" required></div>
                <div class="col-12"><label class="form-label">Note (optional)</label><input class="form-control" name="note" maxlength="255" placeholder="Supplier, invoice number, reason…"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save stock</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
