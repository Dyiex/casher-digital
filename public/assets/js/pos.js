(() => {
  const cfg = window.POS;
  const products = cfg.products;
  const cart = new Map();            // product id -> qty
  let category = 0;

  const $ = (id) => document.getElementById(id);
  const grid = $('productGrid'), lines = $('cartLines'), search = $('search');
  const discountEl = $('discount'), receivedEl = $('received'), chargeBtn = $('chargeBtn');
  const payError = $('payError');

  const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
  const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const money = (n) => cfg.currency + fmt(n);
  const method = () => document.querySelector('input[name="method"]:checked').value;
  const byId = (id) => products.find((p) => p.id === id);

  /* ---------- Catalog ---------- */
  function stockClass(p) { return p.stock <= 0 ? 'out' : (p.stock <= 5 ? 'low' : 'ok'); }

  function renderGrid() {
    const q = search.value.trim().toLowerCase();
    const list = products.filter((p) =>
      (category === 0 || p.category_id === category) &&
      (!q || p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || (p.barcode || '').includes(q)));

    if (!list.length) {
      grid.innerHTML = '<p class="empty w-100">No products match. Try another name or category.</p>';
      return;
    }
    grid.innerHTML = list.map((p) => {
      const inCart = cart.get(p.id) || 0;
      const left = p.stock - inCart;
      const hue = (p.category_id * 47) % 360;
      return `<button type="button" class="product-tile ${left <= 0 ? 'is-out' : ''}" data-id="${p.id}" ${left <= 0 ? 'disabled' : ''} style="--hue:${hue}">
        <span class="tile-badge">${escapeHtml(p.name.trim().charAt(0).toUpperCase())}</span>
        <span class="tile-name">${escapeHtml(p.name)}</span>
        <span class="tile-meta"><strong class="num">${money(p.price)}</strong>
          <span class="chip ${left <= 0 ? 'out' : (left <= 5 ? 'low' : 'ok')}">${left <= 0 ? 'Out' : left + ' left'}</span></span>
      </button>`;
    }).join('');
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* ---------- Cart ---------- */
  function add(id) {
    const p = byId(id);
    if (!p) return;
    const qty = cart.get(id) || 0;
    if (qty + 1 > p.stock) return;
    cart.set(id, qty + 1);
    refresh();
  }

  function setQty(id, qty) {
    const p = byId(id);
    if (qty <= 0) cart.delete(id);
    else cart.set(id, Math.min(qty, p.stock));
    refresh();
  }

  function totals() {
    let subtotal = 0;
    cart.forEach((qty, id) => { subtotal += byId(id).price * qty; });
    subtotal = round2(subtotal);
    const discount = Math.min(Math.max(parseFloat(discountEl.value) || 0, 0), subtotal);
    const tax = round2((subtotal - discount) * cfg.taxRate);
    const total = round2(subtotal - discount + tax);
    return { subtotal, discount: round2(discount), tax, total };
  }

  function renderCart() {
    if (!cart.size) {
      lines.innerHTML = '<p class="empty">Tap a product to add it to the sale.</p>';
      return;
    }
    lines.innerHTML = [...cart.entries()].map(([id, qty]) => {
      const p = byId(id);
      return `<div class="t-line" data-id="${id}">
        <div class="t-line-name">${escapeHtml(p.name)}<small>${money(p.price)} each</small></div>
        <div class="qty">
          <button type="button" data-act="dec" aria-label="Remove one">−</button>
          <span>${qty}</span>
          <button type="button" data-act="inc" aria-label="Add one">+</button>
        </div>
        <div class="t-line-total num">${fmt(p.price * qty)}</div>
        <button type="button" class="t-remove" data-act="del" aria-label="Remove ${escapeHtml(p.name)}"><i class="bi bi-x-lg"></i></button>
      </div>`;
    }).join('');
  }

  function refresh() {
    const t = totals();
    renderCart();
    renderGrid();
    $('tSubtotal').textContent = fmt(t.subtotal);
    $('tTotal').textContent = fmt(t.total);
    const taxEl = $('tTax'); if (taxEl) taxEl.textContent = fmt(t.tax);

    const isCash = method() === 'cash';
    $('cashBox').style.display = isCash ? '' : 'none';
    const received = parseFloat(receivedEl.value) || 0;
    const change = Math.max(round2(received - t.total), 0);
    $('tChange').textContent = fmt(change);

    const ready = cart.size > 0 && (!isCash || received >= t.total);
    chargeBtn.disabled = !ready;
    chargeBtn.textContent = cart.size ? 'Charge ' + money(t.total) : 'Charge';
    payError.textContent = (isCash && cart.size && received > 0 && received < t.total)
      ? 'Amount received is ' + money(t.total - received) + ' short.' : '';
  }

  /* ---------- Events ---------- */
  grid.addEventListener('click', (e) => {
    const tile = e.target.closest('.product-tile');
    if (tile) add(parseInt(tile.dataset.id, 10));
  });

  lines.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const id = parseInt(btn.closest('.t-line').dataset.id, 10);
    const qty = cart.get(id) || 0;
    if (btn.dataset.act === 'inc') setQty(id, qty + 1);
    if (btn.dataset.act === 'dec') setQty(id, qty - 1);
    if (btn.dataset.act === 'del') setQty(id, 0);
  });

  $('catPills').addEventListener('click', (e) => {
    const pill = e.target.closest('.pill');
    if (!pill) return;
    document.querySelectorAll('#catPills .pill').forEach((p) => p.classList.remove('active'));
    pill.classList.add('active');
    category = parseInt(pill.dataset.cat, 10);
    renderGrid();
  });

  search.addEventListener('input', renderGrid);
  search.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = search.value.trim().toLowerCase();
    if (!q) return;
    const exact = products.find((p) => (p.barcode && p.barcode === q) || p.sku.toLowerCase() === q);
    const hit = exact || (() => {
      const matches = products.filter((p) => p.name.toLowerCase().includes(q));
      return matches.length === 1 ? matches[0] : null;
    })();
    if (hit) { add(hit.id); search.value = ''; renderGrid(); }
  });

  discountEl.addEventListener('input', refresh);
  receivedEl.addEventListener('input', refresh);
  document.querySelectorAll('input[name="method"]').forEach((r) => r.addEventListener('change', refresh));

  $('quickCash').addEventListener('click', (e) => {
    const b = e.target.closest('[data-amt]');
    if (!b) return;
    receivedEl.value = b.dataset.amt === 'exact' ? totals().total.toFixed(2) : b.dataset.amt;
    refresh();
  });

  $('clearCart').addEventListener('click', () => {
    if (!cart.size || confirm('Clear all items from this sale?')) {
      cart.clear(); discountEl.value = 0; receivedEl.value = ''; refresh();
    }
  });

  /* ---------- Checkout ---------- */
  const doneModal = new bootstrap.Modal($('doneModal'));

  chargeBtn.addEventListener('click', async () => {
    const t = totals();
    chargeBtn.disabled = true;
    chargeBtn.textContent = 'Saving…';
    payError.textContent = '';
    try {
      const res = await fetch(cfg.checkoutUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
        body: JSON.stringify({
          items: [...cart.entries()].map(([id, qty]) => ({ id, qty })),
          discount: t.discount,
          payment_method: method(),
          amount_paid: parseFloat(receivedEl.value) || 0,
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'The sale could not be saved.');

      cart.forEach((qty, id) => { byId(id).stock -= qty; });
      $('doneReceipt').textContent = 'Receipt ' + data.receipt_no;
      $('doneChange').textContent = money(data.change);
      $('doneReceiptLink').href = data.receipt_url;
      doneModal.show();
    } catch (err) {
      payError.textContent = err.message;
      refresh();
    }
  });

  $('newSaleBtn').addEventListener('click', () => {
    doneModal.hide();
    cart.clear(); discountEl.value = 0; receivedEl.value = '';
    refresh();
    search.focus();
  });

  refresh();
})();
