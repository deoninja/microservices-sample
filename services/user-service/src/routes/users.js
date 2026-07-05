const express = require('express');
const store = require('../models/store');
const router = express.Router();

// GET /api/users
router.get('/users', (req, res) => {
  const users = store.getUsers();
  res.json(users);
});

// GET /api/users/:id
router.get('/users/:id', (req, res) => {
  const user = store.getUser(req.params.id);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.json(user);
});

// POST /api/users
router.post('/users', (req, res) => {
  const { username, password, name, email } = req.body;

  if (!username || !password || !name || !email) {
    return res.status(400).json({ error: 'All fields are required: username, password, name, email' });
  }

  const existing = store.getUserByUsername(username);
  if (existing) {
    return res.status(409).json({ error: 'Username already exists' });
  }

  const user = store.createUser(req.body);
  res.status(201).json(user);
});

// PUT /api/users/:id
router.put('/users/:id', (req, res) => {
  const allowedFields = ['name', 'email', 'password'];
  const updates = {};
  for (const field of allowedFields) {
    if (req.body[field] !== undefined) {
      updates[field] = req.body[field];
    }
  }

  const user = store.updateUser(req.params.id, updates);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.json(user);
});

// DELETE /api/users/:id
router.delete('/users/:id', (req, res) => {
  const deleted = store.deleteUser(req.params.id);
  if (!deleted) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.status(204).send();
});

module.exports = router;
