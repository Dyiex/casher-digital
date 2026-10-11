<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
verify_csrf();

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['items']) || !is_array($in['items'])) {
    json_out(['ok' => false, 'error' => 'The cart is empty.'], 422);
}

/* Merge duplicate lines, validate quantities */
$qtys = [];
foreach ($in['items'] as $line) {
    $id  = (int) ($line['id'] ?? 0);
    $qty = (int) ($line['qty'] ?? 0);
    if ($id <= 0 || $qty <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid item in cart.'], 422);
    }
    $qtys[$id] = ($qtys[$id] ?? 0) + $qty;
}

$method = $in['payment_method'] ?? 'cash';
if (!in_array($method, ['cash', 'card', 'ewallet'], true)) {
    json_out(['ok' => false, 'error' => 'Choose a valid payment method.'], 422);
}

$pdo = db();
$userId = current_user()['id'];

try {
    $pdo->beginTransaction();

    $ids = array_keys($qtys);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products
                           WHERE id IN ($marks) AND active = 1 FOR UPDATE");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $p) {
        $products[(int) $p['id']] = $p;
    }

    $subtotal = 0.0;
    $lines = [];
    foreach ($qtys as $id => $qty) {
        if (!isset($products[$id])) {
            throw new RuntimeException('A product in the cart is no longer available. Refresh the page.');
        }
        $p = $products[$id];
        if ((int) $p['stock'] < $qty) {
            throw new RuntimeException("Not enough stock for {$p['name']}. Only {$p['stock']} left.");
        }
        $lineTotal = round((float) $p['price'] * $qty, 2);
        $subtotal += $lineTotal;
        $lines[] = ['id' => $id, 'name' => $p['name'], 'price' => (float) $p['price'], 'qty' => $qty, 'total' => $lineTotal];
    }
    $subtotal = round($subtotal, 2);

    $discount = round(max(0, (float) ($in['discount'] ?? 0)), 2);
    $discount = min($discount, $subtotal);
    $tax      = round(($subtotal - $discount) * TAX_RATE, 2);
    $total    = round($subtotal - $discount + $tax, 2);

    $paid = $method === 'cash' ? round((float) ($in['amount_paid'] ?? 0), 2) : $total;
    if ($paid < $total) {
        throw new RuntimeException('The amount received is less than the total.');
    }
    $change = round($paid - $total, 2);

    $receiptNo = receipt_number();
    $pdo->prepare("INSERT INTO sales (receipt_no, user_id, subtotal, discount, tax, total, amount_paid, change_due, payment_method)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$receiptNo, $userId, $subtotal, $discount, $tax, $total, $paid, $change, $method]);
    $saleId = (int) $pdo->lastInsertId();

    $insItem  = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?,?,?,?,?,?)");
    $updStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $insMove  = $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity_change, note) VALUES (?,?, 'sale', ?, ?)");

    foreach ($lines as $l) {
        $insItem->execute([$saleId, $l['id'], $l['name'], $l['price'], $l['qty'], $l['total']]);
        $updStock->execute([$l['qty'], $l['id']]);
        $insMove->execute([$l['id'], $userId, -$l['qty'], 'Sale ' . $receiptNo]);
    }

    $pdo->commit();

    json_out([
        'ok'         => true,
        'sale_id'    => $saleId,
        'receipt_no' => $receiptNo,
        'total'      => $total,
        'change'     => $change,
        'receipt_url' => url('/receipt.php?id=' . $saleId),
    ]);
} catch (RuntimeException $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Checkout failed: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Something went wrong while saving the sale. Nothing was charged.'], 500);
}
