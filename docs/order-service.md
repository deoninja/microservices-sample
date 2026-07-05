# Order Service — Code Walkthrough

The Order Service is a Node.js / Express application running on port **3003**.  
It manages the full order lifecycle — creating orders, listing them, and updating their status.  
All routes require authentication, enforced by the API Gateway before requests arrive here.  
The service uses `X-User-Id` and `X-User-Role` headers (set by the gateway) to decide  
which orders a user is allowed to see.

---

## Folder Structure

```
services/order-service/
├── src/
│   ├── index.js        # Express app setup and server startup
│   ├── models/
│   │   └── store.js    # In-memory order data store (the "database")
│   └── routes/
│       └── orders.js   # All order routes
├── Dockerfile
└── package.json
```

---

## src/index.js — App Entry Point

```js
const express     = require('express');
const cors        = require('cors');
const orderRoutes = require('./routes/orders');

const app  = express();
const PORT = 3003; // this service always listens on 3003

// cors() adds CORS headers — needed for server-to-server calls from the gateway.
app.use(cors());

// express.json() parses incoming JSON bodies into req.body.
// Required for POST (create order) and PUT (update status) routes.
app.use(express.json());

// Request logger — logs every request with a timestamp to the console.
// next() must be called to continue to the next middleware or route.
app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] ${req.method} ${req.url}`);
  next();
});

// Mount all order routes under the /api prefix.
// e.g. router.get('/orders') becomes GET /api/orders
app.use('/api', orderRoutes);

// Health check — confirms the service is running.
// Used by Docker health checks and the Home page status indicator.
app.get('/api/health', (req, res) => {
  res.json({
    status:    'ok',
    service:   'order-service',
    timestamp: new Date().toISOString(),
  });
});

// 404 fallback — fires when no route matched.
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Global error handler — catches unhandled errors from route handlers.
app.use((err, req, res, next) => {
  console.error('Order Service Error:', err.message);
  res.status(500).json({ error: 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`Order Service running on port ${PORT}`);
});
```

---

## src/models/store.js — In-Memory Order Store

```js
class OrderStore {
  constructor() {
    // Seed data — three orders exist when the service starts.
    // Each order belongs to a user (userId) and contains an array of items.
    this.orders = [
      {
        id:           1,
        userId:       1,                  // belongs to admin (id: 1)
        customerName: 'Admin User',
        items: [
          { productId: 1, name: 'Wireless Headphones', quantity: 1, price: 79.99 },
        ],
        total:     79.99,
        status:    'completed',           // one of: pending, processing, completed, cancelled
        createdAt: '2024-01-15T10:30:00Z',
      },
      {
        id:           2,
        userId:       2,                  // belongs to john (id: 2)
        customerName: 'John Doe',
        items: [
          { productId: 2, name: 'Smart Watch',         quantity: 1, price: 199.99 },
          { productId: 4, name: 'Mechanical Keyboard', quantity: 1, price: 129.99 },
        ],
        total:     329.98,
        status:    'processing',
        createdAt: '2024-01-20T14:00:00Z',
      },
      {
        id:           3,
        userId:       2,
        customerName: 'John Doe',
        items: [
          { productId: 5, name: 'USB-C Hub', quantity: 2, price: 34.99 },
        ],
        total:     69.98,
        status:    'pending',
        createdAt: '2024-01-25T09:15:00Z',
      },
    ];

    this.nextId = 4; // next order will get ID 4

    // The only valid status values. Any other value is rejected in updateOrderStatus().
    this.validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
  }

  // Returns orders filtered by user role.
  // Admins see ALL orders. Regular users only see their own.
  // Number(userId) converts the string header value to a number for comparison.
  getOrders(userId, userRole) {
    if (userRole === 'admin') return this.orders;
    return this.orders.filter((o) => o.userId === Number(userId));
  }

  // Returns a single order by ID, or null if not found.
  getOrder(id) {
    return this.orders.find((o) => o.id === Number(id)) || null;
  }

  // Creates a new order and calculates the total automatically.
  // reduce() iterates over items and accumulates the running sum.
  // Math.round(total * 100) / 100 rounds to 2 decimal places to avoid
  // floating-point issues like 69.980000000001.
  createOrder(data) {
    const total = data.items.reduce(
      (sum, item) => sum + item.quantity * item.price,
      0  // initial value of sum
    );

    const order = {
      id:           this.nextId++,
      userId:       Number(data.userId) || 1, // fallback to 1 if userId is missing
      customerName: data.customerName || 'Customer',
      items:        data.items || [],
      total:        Math.round(total * 100) / 100,
      status:       'pending',               // all new orders start as 'pending'
      createdAt:    new Date().toISOString(),
    };

    this.orders.push(order);
    return order;
  }

  // Updates an order's status. Returns the updated order, null if not found,
  // or an error object if the status value is not in validStatuses.
  // The route handler checks result.error to decide the HTTP response code.
  updateOrderStatus(id, status) {
    const order = this.orders.find((o) => o.id === Number(id));
    if (!order) return null;

    if (!this.validStatuses.includes(status)) {
      // Return an error object instead of throwing — lets the route send a 400.
      return { error: true, message: `Invalid status. Must be one of: ${this.validStatuses.join(', ')}` };
    }

    order.status = status; // mutate in place
    return order;
  }
}

// Singleton export — all routes share the same store instance.
module.exports = new OrderStore();
```

---

## src/routes/orders.js — Order Routes

```js
const express = require('express');
const store   = require('../models/store');
const router  = express.Router();

// GET /api/orders
// Returns orders for the current user (or all orders for admins).
// The gateway sets X-User-Id and X-User-Role headers from the JWT token.
// This service trusts those headers — it never decodes a JWT itself.
router.get('/orders', (req, res) => {
  const userId   = req.headers['x-user-id'];          // e.g. "2"
  const userRole = req.headers['x-user-role'] || 'user'; // e.g. "admin" or "user"

  const orders = store.getOrders(userId, userRole);
  res.json(orders);
});

// GET /api/orders/:id
// Returns a single order. Enforces ownership — a regular user cannot view
// another user's order. Admins can view any order.
router.get('/orders/:id', (req, res) => {
  const order = store.getOrder(req.params.id);
  if (!order) {
    return res.status(404).json({ error: 'Order not found' });
  }

  // Ownership check — compare the order's userId with the requesting user's ID.
  // Number() converts the string header to a number for strict equality.
  const userId   = Number(req.headers['x-user-id']);
  const userRole = req.headers['x-user-role'] || 'user';

  // If the user is not an admin AND the order doesn't belong to them, deny access.
  // 403 Forbidden = authenticated but not allowed (different from 401 Unauthorized).
  if (userRole !== 'admin' && order.userId !== userId) {
    return res.status(403).json({ error: 'Access denied' });
  }

  res.json(order);
});

// POST /api/orders
// Creates a new order. The userId is taken from the X-User-Id header
// (set by the gateway from the JWT) so users can't create orders for other users.
router.post('/orders', (req, res) => {
  const { customerName, items } = req.body;

  // Read the user ID from the gateway-set header.
  // Fallback to 1 only as a safety net — in practice the gateway always sets this.
  const userId = req.headers['x-user-id'] || 1;

  // items must be a non-empty array — an order with no items makes no sense.
  if (!items || !Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: 'Order must contain at least one item' });
  }

  // Validate each item in the array.
  for (const item of items) {
    // Each item needs a name, quantity, and price to calculate the total.
    if (!item.name || !item.quantity || !item.price) {
      return res.status(400).json({ error: 'Each item requires name, quantity, and price' });
    }
    // Quantity must be at least 1 — you can't order 0 of something.
    if (item.quantity < 1) {
      return res.status(400).json({ error: 'Item quantity must be at least 1' });
    }
  }

  const order = store.createOrder({ userId, customerName, items });
  res.status(201).json(order); // 201 Created
});

// PUT /api/orders/:id/status
// Updates an order's status (e.g. pending → processing → completed).
// Only the status field is updated — other fields cannot be changed this way.
router.put('/orders/:id/status', (req, res) => {
  const { status } = req.body;

  // status field is required in the request body.
  if (!status) {
    return res.status(400).json({ error: 'Status is required' });
  }

  const result = store.updateOrderStatus(req.params.id, status);

  // null means the order ID doesn't exist.
  if (!result) {
    return res.status(404).json({ error: 'Order not found' });
  }

  // The store returns { error: true, message: '...' } for invalid status values.
  if (result.error) {
    return res.status(400).json({ error: result.message });
  }

  res.json(result); // return the updated order
});

module.exports = router;
```
