<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            flash('success', 'Category deleted. Its products are now uncategorized.');
        } elseif ($action === 'save') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                flash('danger', 'Enter a category name.');
            } elseif ($id) {
                $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, $id]);
                flash('success', 'Category renamed.');
            } else {
                $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
                flash('success', 'Category added.');
            }
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'A category with that name already exists.' : 'The category could not be saved.');
    }
    redirect('/categories.php');
}

$cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) AS products
                     FROM categories c LEFT JOIN products p ON p.category_id = c.id
                     GROUP BY c.id ORDER BY c.name")->fetchAll();

$pageTitle = 'Categories';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Categories</h1><p class="text-muted mb-0">Group products so cashiers can find them fast.</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" data-title="Add category"><i class="bi bi-plus-lg me-1"></i> Add category</button>
</div>

<div class="panel">
<?php if (!$cats): ?>
    <p class="empty">No categories yet. Add one to organize your products.</p>
<?php else: ?>
    <table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th class="text-end">Products</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cats as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td class="text-end"><?= (int) $c['products'] ?></td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#catModal" data-title="Rename category" data-record="<?= e(json_encode($c)) ?>">Rename</button>
                    <form method="post" class="d-inline" data-confirm="Delete this category? Its products will become uncategorized.">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" class="modal-content" data-fillable>
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-title>Add category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save category</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
