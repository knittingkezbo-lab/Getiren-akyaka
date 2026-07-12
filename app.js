import { estimateOrder, reserveOrder, settleOrder } from './core.js';

const state = JSON.parse(localStorage.getItem('getiren-demo') || 'null') || {
  user: { walletBalance: 1000, reservedBalance: 0 },
  estimate: null,
  orders: [],
  view: 'customer',
};
state.view ||= 'customer';

const $ = (id) => document.getElementById(id);
const money = (n) => Math.round(n).toLocaleString('tr-TR');
const save = () => localStorage.setItem('getiren-demo', JSON.stringify(state));
const escapeHtml = (value) => String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function render() {
  const latest = state.orders[0];
  const needsExtra = latest?.status === 'requires_extra_payment';

  $('wallet').textContent = money(state.user.walletBalance);
  $('estimateBox').innerHTML = state.estimate ? estimateHtml(state.estimate) : '<div class="estimate-empty">Henüz tahmin yok.</div>';
  $('reserve').disabled = !state.estimate || state.user.walletBalance < state.estimate.reservedAmount;
  $('orders').innerHTML = state.orders.length ? state.orders.map(orderHtml).join('') : '<p class="muted dark">Sipariş yok.</p>';
  $('settle').disabled = latest?.status !== 'reserved';
  $('extra').disabled = !needsExtra;
  $('extraHint').textContent = needsExtra
    ? `${money(latest.extraRequiredAmount)} TL eksik. Müşteriye bildirim gönderilebilir.`
    : 'Eksik bakiye oluşunca otomatik açılır.';

  for (const view of ['customer', 'admin']) {
    $(`${view}View`).classList.toggle('active', state.view === view);
    $(`${view}Tab`).classList.toggle('active', state.view === view);
  }
  save();
}

function estimateHtml(o) {
  return `<div class="estimate-cards">
    ${metric('Ürün tahmini', o.itemsTotal)}
    ${metric('%15 güvenlik payı', o.safetyBuffer)}
    ${metric('Teslimat', o.serviceFee)}
    ${metric('Bloke edilecek', o.reservedAmount, true)}
  </div>
  <p class="estimate-note">${escapeHtml(o.zoneName)} · Gerçek fiş düşük çıkarsa fark cüzdana iade edilir.</p>`;
}

function metric(label, value, strong = false) {
  return `<div class="metric ${strong ? 'strong' : ''}"><span>${label}</span><b>${money(value)} TL</b></div>`;
}

function orderHtml(o) {
  return `<div class="order">
    <div><b>${escapeHtml(o.text)}</b></div>
    <div class="status">${o.status}</div>
    <div>Bloke: ${money(o.reservedAmount)} TL · Fiş: ${money(o.actualReceiptAmount || 0)} TL</div>
    <div>${o.extraRequiredAmount ? `Eksik bakiye: ${money(o.extraRequiredAmount)} TL` : 'Fazla blokaj otomatik iade edilir.'}</div>
  </div>`;
}

for (const tab of [$('customerTab'), $('adminTab')]) {
  tab.onclick = () => { state.view = tab.dataset.view; render(); };
}

$('topup').onclick = () => { state.user.walletBalance += 500; render(); };
$('estimate').onclick = () => {
  try { state.estimate = estimateOrder({ text: $('text').value, zoneId: $('zone').value }); }
  catch (error) { alert(error.message); }
  render();
};
$('reserve').onclick = () => {
  try {
    reserveOrder(state.user, state.estimate);
    state.orders.unshift(state.estimate);
    state.estimate = null;
    state.view = 'admin';
  } catch (error) { alert(error.message); }
  render();
};
$('settle').onclick = () => {
  const order = state.orders[0];
  if (!order) return alert('Sipariş yok');
  try { settleOrder(state.user, order, $('receipt').value); }
  catch (error) { alert(error.message); }
  render();
};
$('extra').onclick = () => {
  const order = state.orders[0];
  if (order?.status !== 'requires_extra_payment') return;
  alert(`${money(order.extraRequiredAmount)} TL ek bakiye bildirimi müşteriye gönderildi.`);
};

render();
