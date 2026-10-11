<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = db();
$products = $pdo->query("SELECT p.id, p.sku, p.barcode, p.name, p.price, p.stock, p.category_id,
                                COALESCE(c.name,'Other') AS category
                         FROM products p LEFT JOIN categories c ON c.id = p.category_id
                         WHERE p.active = 1 ORDER BY p.name")->fetchAll();
foreach ($products as &$p) {
    $p['id'] = (int) $p['id'];
    $p['price'] = (float) $p['price'];
    $p['stock'] = (int) $p['stock'];
    $p['category_id'] = (int) ($p['category_id'] ?? 0);
}
unset($p);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'New sale';
$active = 'pos';
$bodyClass = 'pos-page';
$pageScripts = [url('/assets/js/pos.js')];
require __DIR__ . '/../includes/header.php';
?>
<script>
window.POS = <?= json_encode([
    'products'   => $products,
    'currency'   => CURRENCY,
    'taxRate'    => TAX_RATE,
    'csrf'       => csrf_token(),
    'checkoutUrl'=> url('/api/checkout.php'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<div class="pos-layout">
    <section class="pos-catalog">
        <div class="pos-tools">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input id="search" class="form-control" type="search" autocomplete="off"
                       placeholder="Search by name, SKU or scan a barcode" autofocus>
            </div>
            <div class="cat-pills" id="catPills">
                <button class="pill active" data-cat="0">All</button>
                <?php foreach ($categories as $c): ?>
                    <button class="pill" data-cat="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="product-grid" id="productGrid"></div>
    </section>

    <aside class="pos-cart">
        <div class="tape">
            <div class="tape-head">
                <div>
                    <strong><?= e(STORE_NAME) ?></strong>
                    <small>Cashier: <?= e(current_user()['full_name']) ?></small>
                </div>
                <button class="btn btn-sm btn-link text-danger" id="clearCart" type="button">Clear</button>
            </div>

            <div class="tape-lines" id="cartLines">
                <p class="empty">Tap a product to add it to the sale.</p>
            </div>

            <div class="tape-totals">
                <div class="t-row"><span>Subtotal</span><span id="tSubtotal">0.00</span></div>
                <div class="t-row t-discount">
                    <label for="discount">Discount</label>
                    <input id="discount" type="number" min="0" step="0.01" value="0" inputmode="decimal">
                </div>
                <?php if (TAX_RATE > 0): ?>
                <div class="t-row"><span>Tax (<?= rtrim(rtrim(number_format(TAX_RATE * 100, 2), '0'), '.') ?>%)</span><span id="tTax">0.00</span></div>
                <?php endif; ?>
                <div class="t-rule"></div>
                <div class="t-row t-total"><span>Total</span><span id="tTotal">0.00</span></div>
            </div>
        </div>

        <div class="pay-box">
            <div class="btn-group w-100 mb-2" role="group" aria-label="Payment method" id="payMethods">
                <input type="radio" class="btn-check" name="method" id="mCash" value="cash" checked>
                <label class="btn btn-outline-primary" for="mCash"><i class="bi bi-cash-coin"></i> Cash</label>
                <input type="radio" class="btn-check" name="method" id="mCard" value="card">
                <label class="btn btn-outline-primary" for="mCard"><i class="bi bi-credit-card"></i> Card</label>
                <input type="radio" class="btn-check" name="method" id="mEwallet" value="ewallet">
                <label class="btn btn-outline-primary" for="mEwallet"><i class="bi bi-phone"></i> E-wallet</label>
            </div>

            <div id="cashBox">
                <div class="input-group mb-2">
                    <span class="input-group-text">Received</span>
                    <input id="received" class="form-control num" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
                <div class="quick-cash mb-2" id="quickCash">
                    <button type="button" class="btn btn-sm btn-light" data-amt="exact">Exact</button>
                    <button type="button" class="btn btn-sm btn-light" data-amt="100">100</button>
                    <button type="button" class="btn btn-sm btn-light" data-amt="200">200</button>
                    <button type="button" class="btn btn-sm btn-light" data-amt="500">500</button>
                    <button type="button" class="btn btn-sm btn-light" data-amt="1000">1000</button>
                </div>
                <div class="change-row"><span>Change</span><strong id="tChange">0.00</strong></div>
            </div>

            <div id="payError" class="text-danger small mb-2" role="alert"></div>
            <button id="chargeBtn" class="btn btn-primary btn-lg w-100" type="button" disabled>Charge</button>
        </div>
    </aside>
</div>

<!-- Success modal -->
<div class="modal fade" id="doneModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-3">
            <div class="done-icon"><i class="bi bi-check-lg"></i></div>
            <h2 class="h5 mt-2 mb-1">Sale complete</h2>
            <p class="text-muted mb-1" id="doneReceipt"></p>
            <p class="mb-3">Change due: <strong id="doneChange" class="num"></strong></p>
            <a id="doneReceiptLink" class="btn btn-outline-primary mb-2" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i> Print receipt</a>
            <button type="button" class="btn btn-primary" id="newSaleBtn">Start next sale</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
