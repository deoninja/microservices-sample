const express = require('express');
const store = require('../models/store');
const router = express.Router();

// GET /api/products
router.get('/products', (req, res) => {
  const { search } = req.query;
  const products = store.getProducts(search);
  res.json(products);
});

// GET /api/products/:id
router.get('/products/:id', (req, res) => {
  const product = store.getProduct(req.params.id);
  if (!product) {
    return res.status(404).json({ error: 'Product not found' });
  }
  res.json(product);
});

// POST /api/products
router.post('/products', (req, res) => {
  const { name, price } = req.body;

  if (!name) {
    return res.status(400).json({ error: 'Product name is required' });
  }
  if (price === undefined || Number(price) <= 0) {
    return res.status(400).json({ error: 'Price must be a positive number' });
  }
  if (req.body.stock !== undefined && (!Number.isInteger(Number(req.body.stock)) || Number(req.body.stock) < 0)) {
    return res.status(400).json({ error: 'Stock must be a non-negative integer' });
  }

  const product = store.createProduct(req.body);
  res.status(201).json(product);
});

// PUT /api/products/:id
router.put('/products/:id', (req, res) => {
  const allowedFields = ['name', 'price', 'description', 'stock'];
  const updates = {};
  for (const field of allowedFields) {
    if (req.body[field] !== undefined) {
      updates[field] = req.body[field];
    }
  }

  const product = store.updateProduct(req.params.id, updates);
  if (!product) {
    return res.status(404).json({ error: 'Product not found' });
  }
  res.json(product);
});

// DELETE /api/products/:id
router.delete('/products/:id', (req, res) => {
  const deleted = store.deleteProduct(req.params.id);
  if (!deleted) {
    return res.status(404).json({ error: 'Product not found' });
  }
  res.status(204).send();
});

module.exports = router;
