# User Service — Code Walkthrough

The User Service is a Node.js / Express application running on port **3001**.  
It owns all user data and handles credential verification.  
It is **never called directly by the browser** — only the API Gateway talks to it.

---

## Folder Structure

```
services/user-service/
├── src/
│   ├── index.js          # Express app setup and server startup
│   ├── models/
│   │   └── store.js      # In-memory user data store (the "database")
│   └── routes/
│       ├── auth.js       # POST /login and POST /register
│       └── users.js      # CRUD routes for user management
├── Dockerfile
└── package.json
```

---

## src/index.js — App Entry Point

```js
const express = require('express');
const cors    = require('cors');

// Import the two route files.
// authRoutes  → handles /api/users/login and /api/users/register
// userRoutes  → handles CRUD operations on /api/users
const authRoutes = require('./routes/auth');
const userRoutes = require('./routes/users');

const app  = express();
const PORT = 3001; // this service always listens on 3001

// ── Middleware ────────────────────────────────────────────────────────────────

// cors() adds Access-Control-Allow-Origin headers so other services
// (and the gateway) can call this service without CORS errors.
// Since this service is only called server-to-server (gateway → service),
// CORS is mostly a safety net here.
app.use(cors());

// express.json() parses incoming request bodies that have Content-Type: application/json
// and makes the parsed data available as req.body.
// Without this, req.body would be undefined.
app.use(express.json());

// Request logger — runs on every request before the route handlers.
// Prints a timestamped line to the console so you can see traffic in the terminal.
// next() must be called to pass the request to the next middleware/route.
app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] ${req.method} ${req.url}`);
  next(); // hand off to the next middleware in the chain
});

// ── Routes ────────────────────────────────────────────────────────────────────

// Mount both route files under the /api prefix.
// So a route defined as router.post('/users/login') becomes POST /api/users/login.
app.use('/api', authRoutes);
app.use('/api', userRoutes);

// ── Health Check ──────────────────────────────────────────────────────────────

// Simple endpoint used by Docker and monitoring tools to confirm the service is alive.
// Returns a JSON object with status "ok" and the current timestamp.
app.get('/api/health', (req, res) => {
  res.json({
    status:    'ok',
    service:   'user-service',
    timestamp: new Date().toISOString(),
  });
});

// ── Fallback Handlers ─────────────────────────────────────────────────────────

// 404 handler — catches any request that didn't match a route above.
// Must be placed AFTER all routes so it only fires when nothing else matched.
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Global error handler — Express calls this when a route throws an error.
// The 4-parameter signature (err, req, res, next) is how Express identifies
// this as an error handler rather than a regular middleware.
app.use((err, req, res, next) => {
  console.error('User Service Error:', err.message);
  res.status(500).json({ error: 'Internal server error' });
});

// ── Start Server ──────────────────────────────────────────────────────────────

// Start listening for incoming HTTP connections on PORT 3001.
// The callback runs once the server is ready.
app.listen(PORT, () => {
  console.log(`User Service running on port ${PORT}`);
});
```

---

## src/models/store.js — In-Memory Data Store

This file acts as the "database". All data lives in a plain JavaScript array in memory.  
**Data resets every time the service restarts** — this is intentional for a demo.

```js
class UserStore {
  constructor() {
    // Seed data — two users exist when the service starts.
    // Passwords are stored in plain text here for demo simplicity.
    // In production you would hash passwords with bcrypt.
    this.users = [
      {
        id: 1,
        username: 'admin',
        password: 'password',
        name:     'Admin User',
        email:    'admin@example.com',
        role:     'admin',           // 'admin' role gives access to all orders
        createdAt: '2024-01-01T00:00:00Z',
      },
      {
        id: 2,
        username: 'john',
        password: 'password',
        name:     'John Doe',
        email:    'john@example.com',
        role:     'user',            // 'user' role can only see their own orders
        createdAt: '2024-01-02T00:00:00Z',
      },
    ];

    // Auto-incrementing ID counter.
    // Starts at 3 because the two seed users already have IDs 1 and 2.
    this.nextId = 3;
  }

  // Returns all users with the password field removed.
  // map() creates a new array. Destructuring { password, ...u } splits the
  // password out and collects everything else into u, which is returned.
  getUsers() {
    return this.users.map(({ password, ...u }) => u);
  }

  // Returns a single user by ID, without the password.
  // Number(id) converts the string URL param (e.g. "1") to a number for comparison.
  // Returns null if no user is found — the route handler checks for this.
  getUser(id) {
    const user = this.users.find((u) => u.id === Number(id));
    if (!user) return null;
    const { password, ...userWithoutPassword } = user;
    return userWithoutPassword;
  }

  // Returns the full user object INCLUDING the password.
  // This is intentional — auth.js needs the password to verify credentials.
  // The password is stripped before sending any response to the gateway.
  getUserByUsername(username) {
    return this.users.find((u) => u.username === username) || null;
  }

  // Creates a new user, adds it to the array, and returns it without the password.
  // this.nextId++ uses the current value then increments it (post-increment).
  createUser(data) {
    const user = {
      id:        this.nextId++,
      username:  data.username,
      password:  data.password,
      name:      data.name,
      email:     data.email,
      role:      data.role || 'user', // default role is 'user' if not specified
      createdAt: new Date().toISOString(),
    };
    this.users.push(user);

    // Destructure to remove password before returning.
    const { password, ...created } = user;
    return created;
  }

  // Updates a user's fields and returns the updated user without the password.
  // Spread syntax: { ...this.users[index], ...data } merges the existing user
  // with the new data — new values overwrite old ones for matching keys.
  // We force id back to the original to prevent ID tampering.
  updateUser(id, data) {
    const index = this.users.findIndex((u) => u.id === Number(id));
    if (index === -1) return null; // user not found

    const updated = { ...this.users[index], ...data, id: this.users[index].id };
    this.users[index] = updated;

    const { password, ...result } = updated;
    return result;
  }

  // Removes a user from the array by index.
  // splice(index, 1) removes exactly 1 element at the given index.
  // Returns false if the user wasn't found so the route can send a 404.
  deleteUser(id) {
    const index = this.users.findIndex((u) => u.id === Number(id));
    if (index === -1) return false;
    this.users.splice(index, 1);
    return true;
  }
}

// Export a single shared instance (singleton).
// Every file that requires('./store') gets the SAME object,
// so all routes share the same in-memory data.
module.exports = new UserStore();
```

---

## src/routes/auth.js — Login & Register Routes

These routes are called by the API Gateway's `AuthController` — not by the browser directly.

```js
const express = require('express');
const store   = require('../models/store');
const router  = express.Router(); // creates a mini Express app for grouping routes

// POST /api/users/login
// Called by: API Gateway AuthController::login()
// Purpose: verify credentials and return the user object (without password)
router.post('/users/login', (req, res) => {
  // Destructure username and password from the parsed JSON body.
  const { username, password } = req.body;

  // Basic presence check — return 400 Bad Request if either field is missing.
  if (!username || !password) {
    return res.status(400).json({ error: 'Username and password are required' });
  }

  // Look up the user by username. Returns null if not found.
  const user = store.getUserByUsername(username);

  // If user doesn't exist OR password doesn't match, return 401 Unauthorized.
  // We intentionally give the same error for both cases to avoid revealing
  // whether a username exists (security best practice).
  if (!user || user.password !== password) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }

  // Credentials are correct. Strip the password before sending the response.
  // The gateway will use this user object to build the JWT payload.
  // password: _ renames the password field to _ (a throwaway variable) while
  // ...userData collects all other fields.
  const { password: _, ...userData } = user;
  res.json(userData);
});

// POST /api/users/register
// Called by: API Gateway AuthController::register()
// Purpose: create a new user account
router.post('/users/register', (req, res) => {
  const { username, password, name, email } = req.body;

  // All four fields are required.
  if (!username || !password || !name || !email) {
    return res.status(400).json({ error: 'All fields are required: username, password, name, email' });
  }

  // Enforce minimum lengths (mirrors the gateway's validation rules).
  if (username.length < 3) {
    return res.status(400).json({ error: 'Username must be at least 3 characters' });
  }
  if (password.length < 6) {
    return res.status(400).json({ error: 'Password must be at least 6 characters' });
  }

  // Check for duplicate username — usernames must be unique.
  // 409 Conflict is the correct HTTP status for "resource already exists".
  const existing = store.getUserByUsername(username);
  if (existing) {
    return res.status(409).json({ error: 'Username already exists' });
  }

  // Create the user and return it with 201 Created.
  // The password is stripped inside store.createUser() before returning.
  const user = store.createUser({ username, password, name, email });
  res.status(201).json(user);
});

module.exports = router;
```

---

## src/routes/users.js — User CRUD Routes

These routes are called by the API Gateway's `GatewayController` for user management.  
The gateway has already verified the JWT before these routes are reached.

```js
const express = require('express');
const store   = require('../models/store');
const router  = express.Router();

// GET /api/users
// Returns all users (passwords excluded by the store).
router.get('/users', (req, res) => {
  const users = store.getUsers();
  res.json(users);
});

// GET /api/users/:id
// :id is a URL parameter — Express puts it in req.params.id as a string.
router.get('/users/:id', (req, res) => {
  const user = store.getUser(req.params.id);
  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.json(user);
});

// POST /api/users
// Creates a new user directly (admin use, bypasses register flow).
router.post('/users', (req, res) => {
  const { username, password, name, email } = req.body;

  if (!username || !password || !name || !email) {
    return res.status(400).json({ error: 'All fields are required: username, password, name, email' });
  }

  const existing = store.getUserByUsername(username);
  if (existing) {
    return res.status(409).json({ error: 'Username already exists' });
  }

  // req.body is passed directly — store.createUser() picks the fields it needs.
  const user = store.createUser(req.body);
  res.status(201).json(user);
});

// PUT /api/users/:id
// Partial update — only the fields present in the request body are changed.
// We use an allowlist (allowedFields) to prevent clients from changing
// fields like 'id', 'role', or 'createdAt' that should not be user-editable.
router.put('/users/:id', (req, res) => {
  const allowedFields = ['name', 'email', 'password'];
  const updates = {};

  // Build an updates object containing only the allowed fields that were sent.
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
// Removes the user. Returns 204 No Content on success (no body needed).
router.delete('/users/:id', (req, res) => {
  const deleted = store.deleteUser(req.params.id);
  if (!deleted) {
    return res.status(404).json({ error: 'User not found' });
  }
  res.status(204).send(); // 204 = success with no response body
});

module.exports = router;
```
