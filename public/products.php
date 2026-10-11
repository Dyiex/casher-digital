<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE products SET active = 1 - active WHERE id = ?")->execute([(int) $_POST['id']]);
        flash('success', 'Product updated.');
        redirect('/products.php');
    }

    if ($action === 'save') {
        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $sku      = trim($_POST['sku'] ?? '');
        $barcode  = trim($_POST['barcode'] ?? '') ?: null;
        $cat      = (int) ($_POST['category_id'] ?? 0) ?: null;
        $price    = round((float) ($_POST['price'] ?? 0), 2);
        $cost     = round((float) ($_POST['cost'] ?? 0), 2);
        $reorder  = max(0, (int) ($_POST['reorder_level'] ?? 0));
        $stock    = max(0, (int) ($_POST['stock'] ?? 0));

        if ($name === '' || $sku === '' || $price < 0) {
            flash('danger', 'Enter a name, a SKU and a price of 0 or more.');
            redirect('/products.php');
        }
        try {
            if ($id) {
                $pdo->prepare("UPDATE products SET name=?, sku=?, barcode=?, category_id=?, price=?, cost=?, reorder_level=? WHERE id=?")
                    ->execute([$name, $sku, $barcode, $cat, $price, $cost, $reorder, $id]);
                flash('success', 'Saved changes to ' . $name . '.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO products (name, sku, barcode, category_id, price, cost, stock, reorder_level) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$name, $sku, $barcode, $cat, $price, $cost, $stock, $reorder]);
                if ($stock > 0) {
                    $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity_change, note) VALUES (?,?, 'purchase', ?, 'Opening stock')")
                        ->execute([(int) $pdo->lastInsertId(), current_user()['id'], $stock]);
                }
                $pdo->commit();
                flash('success', 'Added ' . $name . '.');
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', $ex->getCode() === '23000'
                ? 'The SKU "' . $sku . '" is already used by another product.'
                : 'The product could not be saved.');
        }
        redirect('/products.php');
    }
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id";
$args = [];
if ($q !== '') {
    $sql .= " WHERE p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?";
    $args = ["%$q%", "%$q%", "%$q%"];
}
$sql .= " ORDER BY p.active DESC, p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'Products';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Products</h1><p class="text-muted mb-0"><?= count($products) ?> items</p></div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" class="search-box">
            <i class="bi bi-search"></i>
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search products">
        </form>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" data-title="Add product">
            <i class="bi bi-plus-lg me-1"></i> Add product
        </button>
    </div>
</div>

<div class="panel">
<?php if (!$products): ?>
    <p class="empty">No products found. Add your first product to start selling.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Cost</th><th class="text-end">Stock</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr class="<?= $p['active'] ? '' : 'row-void' ?>">
                    <td><strong><?= e($p['name']) ?></strong>
                        <?php if (!$p['active']): ?><span class="chip out">Archived</span><?php endif; ?>
                        <?php if ($p['barcode']): ?><br><small class="text-muted"><?= e($p['barcode']) ?></small><?php endif; ?></td>
                    <td><?= e($p['sku']) ?></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td class="text-end num"><?= money($p['price']) ?></td>
                    <td class="text-end num"><?= money($p['cost']) ?></td>
                    <td class="text-end"><span class="chip <?= stock_class((int) $p['stock'], (int) $p['reorder_level']) ?>"><?= (int) $p['stock'] ?></span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#productModal"
                                data-title="Edit product" data-record="<?= e(json_encode($p)) ?>">Edit</button>
                        <form method="post" class="d-inline" data-confirm="<?= $p['active'] ? 'Archive this product? It will disappear from the register.' : 'Restore this product to the register?' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= $p['active'] ? 'Archive' : 'Restore' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content" data-fillable>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="">
            <div class="modal-header">
                <h2 class="modal-title h5" data-title>Add product</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                <div class="col-6"><label class="form-label">SKU</label><input class="form-control" name="sku" required></div>
                <div class="col-6"><label class="form-label">Barcode</label><input class="form-control" name="barcode"></div>
                <div class="col-12"><label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">No category</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-6"><label class="form-label">Selling price</label><input class="form-control" name="price" type="number" step="0.01" min="0" required></div>
                <div class="col-6"><label class="form-label">Cost</label><input class="form-control" name="cost" type="number" step="0.01" min="0" value="0"></div>
                <div class="col-6" data-create-only><label class="form-label">Opening stock</label><input class="form-control" name="stock" type="number" min="0" value="0"></div>
                <div class="col-6"><label class="form-label">Reorder level</label><input class="form-control" name="reorder_level" type="number" min="0" value="5"></div>
                <div class="col-12 text-muted small" data-edit-only>To change stock, use Inventory so the change is logged.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save product</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
