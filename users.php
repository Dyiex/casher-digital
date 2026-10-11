<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = db();
$me  = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'toggle') {
            if ($id === $me) throw new RuntimeException('You cannot deactivate your own account.');
            $pdo->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
            flash('success', 'User updated.');
        } elseif ($action === 'save') {
            $name = trim($_POST['full_name'] ?? '');
            $user = trim($_POST['username'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'cashier';
            $pass = $_POST['password'] ?? '';

            if ($name === '' || $user === '') throw new RuntimeException('Enter a full name and a username.');
            if ($id === 0 && strlen($pass) < 6) throw new RuntimeException('Passwords need at least 6 characters.');
            if ($pass !== '' && strlen($pass) < 6) throw new RuntimeException('Passwords need at least 6 characters.');
            if ($id === $me && $role !== 'admin') throw new RuntimeException('You cannot remove your own admin role.');

            if ($id) {
                $pdo->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE id=?")->execute([$name, $user, $role, $id]);
                if ($pass !== '') {
                    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
                if ($id === $me) {
                    $_SESSION['user']['full_name'] = $name;
                    $_SESSION['user']['username']  = $user;
                }
                flash('success', 'Saved changes to ' . $name . '.');
            } else {
                $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)")
                    ->execute([$name, $user, password_hash($pass, PASSWORD_DEFAULT), $role]);
                flash('success', 'Added ' . $name . '.');
            }
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That username is already taken.' : 'The user could not be saved.');
    }
    redirect('/users.php');
}

$users = $pdo->query("SELECT id, full_name, username, role, active, created_at FROM users ORDER BY active DESC, full_name")->fetchAll();

$pageTitle = 'Users';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Users</h1><p class="text-muted mb-0">Admins manage everything. Cashiers can only ring up sales.</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-title="Add user"><i class="bi bi-plus-lg me-1"></i> Add user</button>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['active'] ? '' : 'row-void' ?>">
                    <td><strong><?= e($u['full_name']) ?></strong><?= (int) $u['id'] === $me ? ' <small class="text-muted">(you)</small>' : '' ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><span class="chip <?= $u['active'] ? 'ok' : 'out' ?>"><?= $u['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#userModal" data-title="Edit user"
                                data-record="<?= e(json_encode(['id' => $u['id'], 'full_name' => $u['full_name'], 'username' => $u['username'], 'role' => $u['role']])) ?>">Edit</button>
                        <?php if ((int) $u['id'] !== $me): ?>
                        <form method="post" class="d-inline" data-confirm="<?= $u['active'] ? 'Deactivate this user? They will not be able to sign in.' : 'Reactivate this user?' ?>">
                            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= $u['active'] ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content" data-fillable autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-title>Add user</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label">Full name</label><input class="form-control" name="full_name" required></div>
                <div class="col-6"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
                <div class="col-6"><label class="form-label">Role</label>
                    <select class="form-select" name="role"><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
                <div class="col-12"><label class="form-label">Password</label>
                    <input class="form-control" name="password" type="password" minlength="6" autocomplete="new-password">
                    <div class="form-text" data-edit-only>Leave blank to keep the current password.</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save user</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
