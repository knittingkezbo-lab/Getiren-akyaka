import assert from 'node:assert/strict';
import { estimateOrder, reserveOrder, settleOrder } from './core.js';

const order = estimateOrder({ text: '1 kutu süt, 2 ağrı kesici', zoneId: 'outer' });
assert.equal(order.itemsTotal, 350);
assert.equal(order.serviceFee, 350);
assert.equal(order.safetyBuffer, 53);
assert.equal(order.reservedAmount, 753);

const user = { walletBalance: 1000, reservedBalance: 0 };
reserveOrder(user, order);
assert.deepEqual(user, { walletBalance: 247, reservedBalance: 753 });

const settled = settleOrder(user, order, 320);
assert.equal(settled.status, 'delivered');
assert.equal(settled.capturedAmount, 670);
assert.equal(settled.refundAmount, 83);
assert.deepEqual(user, { walletBalance: 330, reservedBalance: 0 });

const low = { walletBalance: 10, reservedBalance: 0 };
assert.throws(() => reserveOrder(low, order), /Yetersiz bakiye/);

const extraOrder = estimateOrder({ text: '1 kutu süt, 2 ağrı kesici', zoneId: 'outer' });
const extraUser = { walletBalance: 1000, reservedBalance: 0 };
reserveOrder(extraUser, extraOrder);
const extra = settleOrder(extraUser, extraOrder, 900);
assert.equal(extra.status, 'requires_extra_payment');
assert.equal(extra.extraRequiredAmount, 497);
assert.deepEqual(extraUser, { walletBalance: 247, reservedBalance: 753 });

// çift settle idempotent olmalı: kapatılmış sipariş ikinci kez iade üretmemeli
const twice = estimateOrder({ text: '1 kutu süt, 2 ağrı kesici', zoneId: 'outer' });
const twiceUser = { walletBalance: 1000, reservedBalance: 0 };
reserveOrder(twiceUser, twice);
settleOrder(twiceUser, twice, 320);
const afterFirstSettle = { ...twiceUser };
settleOrder(twiceUser, twice, 320);
assert.deepEqual(twiceUser, afterFirstSettle);
assert.equal(twice.status, 'delivered');

console.log('core ok');
