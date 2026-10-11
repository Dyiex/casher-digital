<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT s.*, u.full_name FROM sales s JOIN users u ON u.id = s.user_id WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale || (!is_admin() && (int) $sale['user_id'] !== current_user()['id'])) {
    flash('danger', 'That receipt was not found.');
    redirect('/sales.php');
}

$items = db()->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt <?= e($sale['receipt_no']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="receipt-page">
<div class="receipt-actions no-print">
    <a class="btn btn-light" href="<?= e(url('/sales.php')) ?>">Back to sales</a>
    <button class="btn btn-primary" onclick="window.print()">Print</button>
</div>

<article class="receipt">
    <header class="text-center">
        <h1><?= e(STORE_NAME) ?></h1>
        <div><?= e(STORE_ADDRESS) ?></div>
        <div><?= e(STORE_PHONE) ?></div>
    </header>
    <div class="r-rule"></div>
    <div class="r-row"><span>Receipt</span><span><?= e($sale['receipt_no']) ?></span></div>
    <div class="r-row"><span>Date</span><span><?= e(date('M j, Y g:i A', strtotime($sale['created_at']))) ?></span></div>
    <div class="r-row"><span>Cashier</span><span><?= e($sale['full_name']) ?></span></div>
    <?php if ($sale['status'] === 'voided'): ?>
        <div class="r-void">VOIDED</div>
    <?php endif; ?>
    <div class="r-rule"></div>

    <?php foreach ($items as $it): ?>
        <div class="r-item"><?= e($it['product_name']) ?></div>
        <div class="r-row r-sub"><span><?= (int) $it['quantity'] ?> × <?= number_format((float) $it['unit_price'], 2) ?></span><span><?= number_format((float) $it['line_total'], 2) ?></span></div>
    <?php endforeach; ?>

    <div class="r-rule"></div>
    <div class="r-row"><span>Subtotal</span><span><?= number_format((float) $sale['subtotal'], 2) ?></span></div>
    <?php if ((float) $sale['discount'] > 0): ?>
        <div class="r-row"><span>Discount</span><span>-<?= number_format((float) $sale['discount'], 2) ?></span></div>
    <?php endif; ?>
    <?php if ((float) $sale['tax'] > 0): ?>
        <div class="r-row"><span>Tax</span><span><?= number_format((float) $sale['tax'], 2) ?></span></div>
    <?php endif; ?>
    <div class="r-row r-total"><span>Total</span><span><?= money($sale['total']) ?></span></div>
    <div class="r-row"><span>Paid (<?= e(ucfirst($sale['payment_method'])) ?>)</span><span><?= number_format((float) $sale['amount_paid'], 2) ?></span></div>
    <div class="r-row"><span>Change</span><span><?= number_format((float) $sale['change_due'], 2) ?></span></div>
    <div class="r-rule"></div>
    <p class="text-center mb-0"><?= e(RECEIPT_FOOTER) ?></p>
</article>
</body>
</html>
