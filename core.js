export const zones = {
  center: { id: 'center', name: 'Akyaka İçi', serviceFee: 250 },
  outer: { id: 'outer', name: 'Akçapınar / Gökova / Ataköy', serviceFee: 350 },
};

const priceHints = [
  ['ağrı', 150],
  ['ilaç', 150],
  ['süt', 50],
  ['ekmek', 15],
  ['su', 35],
  ['kahve', 120],
];

export function estimateOrder({ text, zoneId }) {
  const zone = zones[zoneId] ?? zones.center;
  const cleanText = String(text || '').trim();
  if (!cleanText) throw new Error('Sipariş metni boş olamaz');

  const lower = cleanText.toLocaleLowerCase('tr');
  const itemsTotal = priceHints.reduce((sum, [word, price]) => {
    if (!lower.includes(word)) return sum;
    const qty = Number(lower.match(new RegExp(`(\\d+)\\s+[^,.]*${word}`))?.[1] || 1);
    return sum + qty * price;
  }, 0) || 200;
  const safetyBuffer = Math.ceil(itemsTotal * 0.15);

  return {
    id: cryptoRandomId(),
    text: cleanText,
    zoneId: zone.id,
    zoneName: zone.name,
    itemsTotal,
    serviceFee: zone.serviceFee,
    safetyBuffer,
    reservedAmount: itemsTotal + safetyBuffer + zone.serviceFee,
    status: 'estimated',
  };
}

export function reserveOrder(user, order) {
  if (user.walletBalance < order.reservedAmount) throw new Error('Yetersiz bakiye');
  user.walletBalance -= order.reservedAmount;
  user.reservedBalance += order.reservedAmount;
  order.status = 'reserved';
  return order;
}

export function settleOrder(user, order, receiptAmount) {
  // only a reserved order can be settled; re-settling would double the refund
  if (order.status !== 'reserved') return order;
  const actual = Math.max(0, Math.round(Number(receiptAmount) || 0));
  const capturedAmount = actual + order.serviceFee;

  if (capturedAmount > order.reservedAmount) {
    order.status = 'requires_extra_payment';
    order.actualReceiptAmount = actual;
    order.extraRequiredAmount = capturedAmount - order.reservedAmount;
    return order;
  }

  const refundAmount = order.reservedAmount - capturedAmount;
  user.reservedBalance -= order.reservedAmount;
  user.walletBalance += refundAmount;

  Object.assign(order, {
    status: 'delivered',
    actualReceiptAmount: actual,
    capturedAmount,
    refundAmount,
  });
  return order;
}

function cryptoRandomId() {
  // ponytail: demo id; Firestore doc id when backend exists.
  return Math.random().toString(36).slice(2, 10);
}
