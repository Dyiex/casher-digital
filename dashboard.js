(() => {
  const el = document.getElementById('salesChart');
  if (!el || typeof Chart === 'undefined') return;
  const labels = JSON.parse(el.dataset.labels);
  const values = JSON.parse(el.dataset.values);
  const cur = el.dataset.currency;
  const css = getComputedStyle(document.documentElement);

  new Chart(el, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: values.map((_, i) => i === values.length - 1
          ? css.getPropertyValue('--pine').trim() : css.getPropertyValue('--pine-soft').trim()),
        borderRadius: 6,
        maxBarThickness: 44,
      }],
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: c => ' ' + cur + Number(c.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2 }) } },
      },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: '#E6EAE7' }, ticks: { callback: v => cur + v } },
      },
    },
  });
})();
