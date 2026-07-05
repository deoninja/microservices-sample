const express = require('express');
const store = require('../models/store');
const router = express.Router();

// POST /api/users/login
router.post('/users/login', (req, res) => {
  const { username, password } = req.body;

  if (!username || !password) {
    return res.status(400).json({ error: 'Username and password are required' });
  }

  const user = store.getUserByUsername(username);
  if (!user || user.password !== password) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }

  const { password: _, ...userData } = user;
  res.json(userData);
});

// POST /api/users/register
router.post('/users/register', (req, res) => {
  const { username, password, name, email } = req.body;

  if (!username || !password || !name || !email) {
    return res.status(400).json({ error: 'All fields are required: username, password, name, email' });
  }

  if (username.length < 3) {
    return res.status(400).json({ error: 'Username must be at least 3 characters' });
  }

  if (password.length < 6) {
    return res.status(400).json({ error: 'Password must be at least 6 characters' });
  }

  const existing = store.getUserByUsername(username);
  if (existing) {
    return res.status(409).json({ error: 'Username already exists' });
  }

  const user = store.createUser({ username, password, name, email });
  res.status(201).json(user);
});

module.exports = router;
