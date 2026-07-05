# Product Service — Code Walkthrough

The Product Service is a Node.js / Express application running on port **3002**.  
It owns the product catalog — listing, creating, updating, and deleting products.  
Read endpoints (GET) are public. Write endpoints (POST/PUT/DELETE) require a JWT,  
enforced by the API Gateway before the request ever reaches this service.

---

## Folder Structure

```
services/product-service/
├── src/
│   ├── index.js          # Express app setup and server startup
│   ├── models/
│   │   └── store.js      # In-memory product data store (the "database")
│   └── routes/
│       └── products.js   # All product CRUD routes
├── Dockerfile
└── package.json
```

---

## src/index.js — App Entry Point

```js
const express       = require('express');
const cors          = require('cors');
const productRoutes = require('./routes/products');

const app  = express();
const PORT = 3002; // this service always listens on 3002

// cors() allows cross-origin requests — mainly relevant for server-to-server
// calls from the gateway, but good practice to include.
app.use(cors());

// express.json() parses JSON request bodies into req.body.
// Required for POST and PUT routes that receive product data.
app.use(express.json());

// Request logger — prints every incoming request to the console with a timestamp.
// next() passes control to the next middleware or route handler.
app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] ${req.method} ${req.url}`);
  next();
});

// Mount all product routes under the /api prefix.
// e.g. router.get('/products') becomes GET /api/products
app.use('/api', productRoutes);

// Health check endpoint — used by Docker and monitoring to confirm the service is up.
app.get('/api/health', (req, res) => {
  res.json({
    status:    'ok',
    service:   'product-service',
    timestamp: new Date().toISOString(),
  });
});

// 404 fallback — fires when no route above matched the request.
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Global error handler — catches any unhandled errors thrown in route handlers.
// The 4-parameter signature tells Express this is an error handler.
app.use((err, req, res, next) => {
  console.error('Product Service Error:', err.message);
  res.status(500).json({ error: 'Internal server error' });
});

// Start the server and begin accepting connections.
app.listen(PORT, () => {
  console.log(`Product Service running on port ${PORT}`);
});
```

---

## src/models/store.js — In-Memory Product Store

```js
class ProductStore {
  constructor() {
    // Seed data — five products exist when the service starts.
    // All data is lost when the service restarts (in-memory only).
    this.products = [
      { id: 1, name: 'Wireless Headphones', price: 79.99,  description: 'Noise-canceling Bluetooth headphones with 30hr battery', stock: 45,  createdAt: '2024-01-01T00:00:00Z' },
      { id: 2, name: 'Smart Watch',         price: 199.99, description: 'Fitness tracker with heart rate monitor and GPS',          stock: 30,  createdAt: '2024-01-02T00:00:00Z' },
      { id: 3, name: 'Laptop Stand',        price: 49.99,  description: 'Adjustable aluminum stand for 13-17 inch laptops',        stock: 100, createdAt: '2024-01-03T00:00:00Z' },
      { id: 4, name: 'Mechanical Keyboard', price: 129.99, description: 'RGB backlit mechanical keyboard with Cherry MX switches',  stock: 25,  createdAt: '2024-01-04T00:00:00Z' },
      { id: 5, name: 'USB-C Hub',           price: 34.99,  description: '7-in-1 USB-C hub with HDMI, USB 3.0, SD card reader',    stock: 60,  createdAt: '2024-01-05T00:00:00Z' },
    ];

    // Next available ID — starts at 6 since seed data uses 1–5.
    this.nextId = 6;
  }

  // Returns all products, or a filtered subset if a search term is provided.
  // toLowerCase() on both sides makes the search case-insensitive.
  // includes() checks if the product name contains the search string anywhere.
  getProducts(search) {
    if (!search) return this.products; // no filter — return everything
    const q = search.toLowerCase();
    return this.products.filter((p) => p.name.toLowerCase().includes(q));
  }

  // Returns a single product by numeric ID, or null if not found.
  // Number(id) converts the string URL param to a number for strict equality.
  getProduct(id) {
    return this.products.find((p) => p.id === Number(id)) || null;
  }

  // Creates a new product and appends it to the array.
  // Number() coerces price and stock to numbers in case they arrive as strings.
  // || 0 ensures stock defaults to 0 if not provided or falsy.
  createProduct(data) {
    const product = {
      id:          this.nextId++,
      name:        data.name,
      price:       Number(data.price),
      description: data.description || '',
      stock:       Number(data.stock) || 0,
      createdAt:   new Date().toISOString(),
    };
    this.products.push(product);
    return product;
  }

  // Updates only the fields that were passed in data.
  // Uses an allowlist to prevent updating id or createdAt.
  // Mutates the product in-place (this.products[index][field] = ...).
  updateProduct(id, data) {
    const index = this.products.findIndex((p) => p.id === Number(id));
    if (index === -1) return null;

    const allowed = ['name', 'price', 'description', 'stock'];
    for (const field of allowed) {
      if (data[field] !== undefined) {
        this.products[index][field] = data[field]; // only update fields that were sent
      }
    }
    return this.products[index];
  }

  // Removes a product from the array by index.
  // splice(index, 1) removes exactly 1 element at that position.
  deleteProduct(id) {
    const index = this.products.findIndex((p) => p.id === Number(id));
    if (index === -1) return false;
    this.products.splice(index, 1);
    return true;
  }
}

// Export a singleton — all routes share the same store instance.
module.exports = new ProductStore();
```

---

## src/routes/products.js — Product CRUD Routes

```js
const express = require('express');
const store   = require('../models/store');
const router  = express.Router();

// GET /api/products?search=...
// Public — no authentication required (enforced at the gateway level).
// Reads the optional ?search= query parameter and passes it to the store.
router.get('/products', (req, res) => {
  const { search } = req.query; // req.query holds URL query string params
  const products = store.getProducts(search);
  res.json(products);
});

// GET /api/products/:id
// Public — returns a single product by its numeric ID.
router.get('/products/:id', (req, res) => {
  const product = store.getProduct(req.params.id);
  if (!product) {
    return res.status(404).json({ error: 'Product not found' });
  }
  res.json(product);
});

// POST /api/products
// Protected at the gateway — only authenticated users reach this route.
// Validates that name and price are present and valid before creating.
router.post('/products', (req, res) => {
  const { name, price } = req.body;

  // name is required — a product must have a name.
  if (!name) {
    return res.status(400).json({ error: 'Product name is required' });
  }

  // price must be present and greater than zero.
  // Number(price) <= 0 also catches negative prices.
  if (price === undefined || Number(price) <= 0) {
    return res.status(400).json({ error: 'Price must be a positive number' });
  }

  // stock is optional, but if provided it must be a non-negative whole number.
  // Number.isInteger() returns false for decimals like 1.5.
  if (req.body.stock !== undefined && (!Number.isInteger(Number(req.body.stock)) || Number(req.body.stock) < 0)) {
    return res.status(400).json({ error: 'Stock must be a non-negative integer' });
  }

  const product = store.createProduct(req.body);
  res.status(201).json(product); // 201 Created
});

// PUT /api/products/:id
// Protected at the gateway. Partial update — only send the fields you want to change.
// Uses an allowlist so clients can't accidentally overwrite id or createdAt.
router.put('/products/:id', (req, res) => {
  const allowedFields = ['name', 'price', 'description', 'stock'];
  const updates = {};

  // Build an object with only the fields that are both allowed and present in the body.
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
// Protected at the gateway. Returns 204 No Content on success.
router.delete('/products/:id', (req, res) => {
  const deleted = store.deleteProduct(req.params.id);
  if (!deleted) {
    return res.status(404).json({ error: 'Product not found' });
  }
  res.status(204).send(); // 204 = deleted successfully, no body
});

module.exports = router;
```
