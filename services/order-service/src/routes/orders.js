const express = require('express');
const store = require('../models/store');
const router = express.Router();

// GET /api/orders
router.get('/orders', (req, res) => {
  const userId = req.headers['x-user-id'];
  const userRole = req.headers['x-user-role'] || 'user';
  const orders = store.getOrders(userId, userRole);
  res.json(orders);
});

// GET /api/orders/:id
router.get('/orders/:id', (req, res) => {
  const order = store.getOrder(req.params.id);
  if (!order) {
    return res.status(404).json({ error: 'Order not found' });
  }

  const userId = Number(req.headers['x-user-id']);
  const userRole = req.headers['x-user-role'] || 'user';
  if (userRole !== 'admin' && order.userId !== userId) {
    return res.status(403).json({ error: 'Access denied' });
  }

  res.json(order);
});

// POST /api/orders
router.post('/orders', (req, res) => {
  const { customerName, items } = req.body;
  const userId = req.headers['x-user-id'] || 1;

  if (!items || !Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: 'Order must contain at least one item' });
  }

  for (const item of items) {
    if (!item.name || !item.quantity || !item.price) {
      return res.status(400).json({ error: 'Each item requires name, quantity, and price' });
    }
    if (item.quantity < 1) {
      return res.status(400).json({ error: 'Item quantity must be at least 1' });
    }
  }

  const order = store.createOrder({ userId, customerName, items });
  res.status(201).json(order);
});

// PUT /api/orders/:id/status
router.put('/orders/:id/status', (req, res) => {
  const { status } = req.body;
  if (!status) {
    return res.status(400).json({ error: 'Status is required' });
  }

  const result = store.updateOrderStatus(req.params.id, status);
  if (!result) {
    return res.status(404).json({ error: 'Order not found' });
  }
  if (result.error) {
    return res.status(400).json({ error: result.message });
  }

  res.json(result);
});

module.exports = router;
