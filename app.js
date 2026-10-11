/* Shared UI behaviour: modal prefill + confirm prompts */

// Fill a modal form from the clicked button's data-record JSON.
document.addEventListener('show.bs.modal', (ev) => {
  const modal = ev.target;
  const form = modal.querySelector('form[data-fillable]');
  if (!form) return;

  const trigger = ev.relatedTarget;
  const raw = trigger && trigger.getAttribute('data-record');
  const rec = raw ? JSON.parse(raw) : null;

  form.reset();
  if (rec) {
    form.querySelectorAll('[name]').forEach((el) => {
      if (el.name in rec && rec[el.name] !== null) el.value = rec[el.name];
    });
  }

  const title = modal.querySelector('[data-title]');
  if (title && trigger && trigger.dataset.title) title.textContent = trigger.dataset.title;

  modal.querySelectorAll('[data-edit-only]').forEach((n) => { n.hidden = !rec; });
  modal.querySelectorAll('[data-create-only]').forEach((n) => { n.hidden = !!rec; });

  // Password is only required when creating a user.
  const pw = form.querySelector('input[name="password"]');
  if (pw) pw.required = !rec;
});

// Focus the first field when a modal opens.
document.addEventListener('shown.bs.modal', (ev) => {
  const first = ev.target.querySelector('input:not([type=hidden]):not([hidden])');
  if (first) first.focus();
});

// Confirm before submitting forms that carry data-confirm.
document.addEventListener('submit', (ev) => {
  const msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
