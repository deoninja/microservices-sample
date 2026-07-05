# Frontend — Code Walkthrough

The frontend is a **React + TypeScript** application built with **Vite**, running on port **3000**.  
It communicates exclusively with the API Gateway at port 8000 — never directly with microservices.  
All API calls go through a single Axios client that automatically attaches the JWT token.

---

## Folder Structure

```
frontend/
├── src/
│   ├── api/
│   │   └── client.ts         # Axios instance with auth interceptors
│   ├── components/
│   │   └── Layout.tsx        # Navbar, footer, logout logic
│   ├── pages/
│   │   ├── Home.tsx          # Landing page with architecture diagram
│   │   ├── Login.tsx         # Login form
│   │   ├── Products.tsx      # Product listing with search
│   │   └── Orders.tsx        # Order listing and create form
│   ├── App.tsx               # Route definitions
│   ├── main.tsx              # React app entry point
│   └── App.css               # Global styles
├── vite.config.ts            # Vite dev server + proxy config
├── tsconfig.json             # TypeScript compiler options
└── index.html                # HTML shell
```

---

## vite.config.ts — Dev Server & Proxy

```ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()], // enables JSX/TSX transformation and React Fast Refresh

  server: {
    port: 3000, // the dev server listens on port 3000

    // host: '0.0.0.0' makes Vite bind to all network interfaces, not just localhost.
    // This is required when running inside Docker so the container port is reachable
    // from the host machine. Without this, the server only accepts connections
    // from within the container itself.
    host: '0.0.0.0',

    proxy: {
      // Any request from the browser that starts with /api is forwarded to the gateway.
      // This means the browser calls http://localhost:3000/api/products
      // and Vite silently forwards it to http://localhost:8000/api/products.
      //
      // Benefits:
      //   1. No CORS issues — the browser thinks everything is on the same origin.
      //   2. The gateway URL is configurable via VITE_API_URL for Docker.
      '/api': {
        // VITE_API_URL is set to http://api-gateway:8000 in docker-compose.yml
        // so the proxy resolves to the correct container name inside Docker.
        // Falls back to http://localhost:8000 for local development.
        target: process.env.VITE_API_URL || 'http://localhost:8000',
        changeOrigin: true, // rewrites the Host header to match the target
      },
    },
  },
})
```

---

## src/main.tsx — React Entry Point

```tsx
import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App'
import './App.css' // global styles loaded once here

// ReactDOM.createRoot() is the React 18 way to mount the app.
// document.getElementById('root')! — the ! tells TypeScript we're sure
// this element exists (it's in index.html). Without ! TypeScript would
// complain it might be null.
ReactDOM.createRoot(document.getElementById('root')!).render(
  // React.StrictMode runs extra checks in development only (not in production).
  // It intentionally renders components twice to help detect side effects.
  <React.StrictMode>
    // BrowserRouter enables client-side routing using the browser's History API.
    // It must wrap the entire app so all child components can use useNavigate()
    // and <Link> without errors.
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>,
)
```

---

## src/App.tsx — Route Definitions

```tsx
import React from 'react'
import { Routes, Route } from 'react-router-dom'
import Layout from './components/Layout'
import Home     from './pages/Home'
import Products from './pages/Products'
import Orders   from './pages/Orders'
import Login    from './pages/Login'

const App: React.FC = () => {
  return (
    // Layout wraps every page — it renders the navbar and footer around {children}.
    // Every route's page component is passed as children to Layout.
    <Layout>
      // Routes renders only the first <Route> that matches the current URL.
      // Without Routes, all matching routes would render simultaneously.
      <Routes>
        <Route path="/"         element={<Home />}     />
        <Route path="/products" element={<Products />} />
        <Route path="/orders"   element={<Orders />}   />
        <Route path="/login"    element={<Login />}    />
      </Routes>
    </Layout>
  )
}

export default App
```

---

## src/api/client.ts — Axios HTTP Client

This is the single place all API calls are made from. Every page imports this client.

```ts
import axios from 'axios'

// Create a pre-configured Axios instance.
// baseURL: '/api' means all requests are relative to /api.
// So apiClient.get('/products') sends GET /api/products.
// Vite's proxy then forwards /api/* to the gateway at port 8000.
const apiClient = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

// ── Request Interceptor ───────────────────────────────────────────────────────
//
// Runs automatically before EVERY request is sent.
// Its job: attach the JWT token to the Authorization header if one exists.
//
// After login, the token is stored in localStorage.
// This interceptor reads it and adds: Authorization: Bearer <token>
// The gateway's JwtMiddleware then reads this header to authenticate the request.
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('token') // null if not logged in
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config // must return config to continue the request
})

// ── Response Interceptor ──────────────────────────────────────────────────────
//
// Runs automatically after EVERY response is received.
// Has two callbacks: one for success (2xx), one for errors (4xx/5xx).
apiClient.interceptors.response.use(
  // Success handler — just pass the response through unchanged.
  (response) => response,

  // Error handler — runs when the server returns a 4xx or 5xx status.
  (error) => {
    // If the server returned 401 Unauthorized, the token is missing or expired.
    // Clear the stored credentials and redirect to the login page.
    // The check for pathname !== '/login' prevents an infinite redirect loop
    // if the login request itself returns a 401.
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    // Re-throw the error so the calling code (try/catch in pages) can handle it.
    return Promise.reject(error)
  }
)

export default apiClient
```

---

## src/components/Layout.tsx — Navbar & Shell

```tsx
import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'

interface LayoutProps {
  children: React.ReactNode // any valid React content passed between <Layout> tags
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
  // useNavigate() returns a function to programmatically change the URL.
  // Used here to redirect to /login after logout.
  const navigate = useNavigate()

  // menuOpen controls the mobile hamburger menu open/close state.
  const [menuOpen, setMenuOpen] = useState(false)

  // Read the stored user object from localStorage.
  // JSON.parse() converts the stored string back to an object.
  // If nothing is stored, user is null — the navbar shows "Login" instead of the username.
  const userStr = localStorage.getItem('user')
  const user    = userStr ? JSON.parse(userStr) : null

  // handleLogout clears auth data and sends the user to the login page.
  const handleLogout = () => {
    localStorage.removeItem('token') // remove JWT token
    localStorage.removeItem('user')  // remove cached user object
    navigate('/login')               // redirect to login page
  }

  return (
    <div className="app-layout">
      <nav className="navbar">
        <div className="nav-container">
          {/* Brand logo — clicking it goes to the home page */}
          <Link to="/" className="nav-brand">
            {/* Inline SVG house icon */}
            <svg ...>...</svg>
            MicroStore
          </Link>

          {/* Hamburger button — only visible on mobile via CSS */}
          <button className="menu-toggle" onClick={() => setMenuOpen(!menuOpen)}>
            <span></span><span></span><span></span>
          </button>

          {/* Nav links — the 'open' class is toggled by menuOpen for mobile */}
          <div className={`nav-links ${menuOpen ? 'open' : ''}`}>
            <Link to="/"         onClick={() => setMenuOpen(false)}>Home</Link>
            <Link to="/products" onClick={() => setMenuOpen(false)}>Products</Link>
            <Link to="/orders"   onClick={() => setMenuOpen(false)}>Orders</Link>

            {/* Conditionally render user info or login link based on auth state */}
            {user ? (
              <div className="nav-user">
                {/* Show name if available, fall back to username */}
                <span className="user-name">{user.name || user.username}</span>
                <button className="btn-logout" onClick={handleLogout}>Logout</button>
              </div>
            ) : (
              <Link to="/login" className="btn-login" onClick={() => setMenuOpen(false)}>Login</Link>
            )}
          </div>
        </div>
      </nav>

      {/* Page content — each route's component renders here */}
      <main className="main-content">{children}</main>

      <footer className="footer">
        <p>MicroStore &copy; 2024 — Demo Microservices Architecture</p>
      </footer>
    </div>
  )
}

export default Layout
```

---

## src/pages/Login.tsx — Login Page

```tsx
import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

const Login: React.FC = () => {
  const navigate = useNavigate()

  // Controlled input state — each input's value is stored in state
  // and updated on every keystroke via onChange.
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error,    setError]    = useState('') // error message shown in the form
  const [loading,  setLoading]  = useState(false) // disables the button while submitting

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault() // prevent the browser's default form submission (page reload)
    setError('')        // clear any previous error message
    setLoading(true)    // disable the submit button

    try {
      // POST /api/auth/login → gateway AuthController::login()
      // The gateway verifies credentials with the User Service and returns a JWT.
      const res = await apiClient.post('/auth/login', { username, password })

      // Store the token and user object in localStorage.
      // The Axios request interceptor in client.ts will attach the token
      // to every subsequent request automatically.
      localStorage.setItem('token', res.data.token)
      localStorage.setItem('user',  JSON.stringify(res.data.user))

      // Redirect to the orders page after successful login.
      navigate('/orders')
    } catch (err: any) {
      // err.response?.data?.error is the error message from the gateway.
      // The ?. (optional chaining) prevents crashes if the response structure
      // is unexpected (e.g. network error with no response).
      setError(err.response?.data?.error || 'Login failed. Please try again.')
    } finally {
      // Always re-enable the button, whether login succeeded or failed.
      setLoading(false)
    }
  }

  return (
    <div className="login-page">
      <div className="login-card">
        <h2>Sign In</h2>
        <p className="login-hint">Demo: admin / password</p>
        <form onSubmit={handleSubmit}>
          {/* Only render the error div when there is an error message */}
          {error && <div className="form-error">{error}</div>}

          <div className="form-group">
            <label htmlFor="username">Username</label>
            {/* value + onChange = controlled input — React owns the value */}
            <input
              id="username"
              type="text"
              value={username}
              onChange={e => setUsername(e.target.value)}
              placeholder="Enter your username"
              required
            />
          </div>

          <div className="form-group">
            <label htmlFor="password">Password</label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="Enter your password"
              required
            />
          </div>

          {/* disabled={loading} prevents double-submission while the request is in flight */}
          <button type="submit" className="btn btn-primary btn-block" disabled={loading}>
            {loading ? 'Signing in...' : 'Sign In'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default Login
```

---

## src/pages/Home.tsx — Home Page

```tsx
import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

// TypeScript interface — defines the shape of the health check response.
// This gives us type safety and autocomplete when accessing res.data fields.
interface HealthResponse {
  status:    string
  service:   string
  timestamp: string
}

const Home: React.FC = () => {
  // health stores the gateway's health check response, or null while loading.
  const [health,      setHealth]      = useState<HealthResponse | null>(null)
  // healthError is true if the health check request failed (gateway is down).
  const [healthError, setHealthError] = useState(false)

  // useEffect with an empty dependency array [] runs once after the component mounts.
  // It calls GET /api/health to check if the gateway is reachable.
  // The result drives the status indicator in the hero section.
  useEffect(() => {
    apiClient.get('/health')
      .then(res => setHealth(res.data))    // success — store the response
      .catch(() => setHealthError(true))   // failure — mark as offline
  }, []) // [] means "run once on mount, never re-run"

  return (
    <div className="home-page">
      <section className="hero">
        {/* Status indicator — shows Online/Offline/Connecting based on state */}
        <div className={`status-indicator ${health ? 'online' : healthError ? 'offline' : 'loading'}`}>
          <span className="status-dot"></span>
          <span>
            {health
              ? `API Gateway Online — ${new Date(health.timestamp).toLocaleTimeString()}`
              : healthError
                ? 'API Gateway Offline'
                : 'Connecting...'}
          </span>
        </div>
      </section>

      {/* Architecture diagram section — static HTML, no data fetching */}
      <section className="arch-section">
        <div className="arch-diagram">
          <div className="arch-item frontend">React Frontend <span>:3000</span></div>
          <div className="arch-arrow">↓ ↑ HTTP</div>
          <div className="arch-item gateway">Laravel API Gateway <span>:8000</span></div>
          <div className="arch-arrow">↓ ↑ HTTP</div>
          <div className="arch-layer layer-three">
            <div className="arch-item service">User Service    <span>:3001</span></div>
            <div className="arch-item service">Product Service <span>:3002</span></div>
            <div className="arch-item service">Order Service   <span>:3003</span></div>
          </div>
        </div>
      </section>
    </div>
  )
}

export default Home
```

---

## src/pages/Products.tsx — Products Page

```tsx
import React, { useEffect, useState } from 'react'
import apiClient from '../api/client'

// TypeScript interface for a product object.
// Matches the shape returned by the Product Service.
interface Product {
  id:          number
  name:        string
  price:       number
  description: string
  stock:       number
  createdAt:   string
}

const Products: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]) // list of products
  const [search,   setSearch]   = useState('')             // current search input value
  const [loading,  setLoading]  = useState(true)           // true while fetching
  const [error,    setError]    = useState('')             // error message if fetch fails

  // fetchProducts is defined outside useEffect so it can also be called
  // by the search form submit handler and the clear button.
  const fetchProducts = async (query?: string) => {
    setLoading(true)
    setError('')
    try {
      // If a search query is provided, append it as a URL parameter.
      // encodeURIComponent() escapes special characters (spaces, &, etc.)
      // so they don't break the URL.
      const url = query ? `/products?search=${encodeURIComponent(query)}` : '/products'
      const res = await apiClient.get(url)
      setProducts(res.data)
    } catch {
      setError('Failed to load products. Make sure the backend services are running.')
    } finally {
      setLoading(false) // always stop the loading state
    }
  }

  // Fetch all products once when the component first renders.
  useEffect(() => { fetchProducts() }, [])

  // Called when the search form is submitted.
  // e.preventDefault() stops the browser from reloading the page.
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    fetchProducts(search)
  }

  // Clears the search input and reloads all products.
  const handleClear = () => {
    setSearch('')
    fetchProducts()
  }

  return (
    <div className="products-page">
      {/* Search bar — controlled form */}
      <form className="search-bar" onSubmit={handleSearch}>
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)} // update state on every keystroke
          placeholder="Search products..."
        />
        <button type="submit">Search</button>
        {/* Only show Clear button when there is an active search term */}
        {search && <button type="button" onClick={handleClear}>Clear</button>}
      </form>

      {/* Loading skeleton — shown while the fetch is in progress */}
      {loading && (
        <div className="loading-grid">
          {[1, 2, 3].map(i => <div className="skeleton-card" key={i} />)}
        </div>
      )}

      {/* Error banner — shown if the fetch failed */}
      {error && <div className="error-banner">{error}</div>}

      {/* Empty state — shown when fetch succeeded but returned no results */}
      {!loading && !error && products.length === 0 && (
        <div className="empty-state">
          <p>No products found{search ? ` for "${search}"` : ''}.</p>
        </div>
      )}

      {/* Product grid — rendered when we have data */}
      {!loading && !error && products.length > 0 && (
        <div className="product-grid">
          {products.map(product => (
            <div className="product-card" key={product.id}>
              <h3>{product.name}</h3>
              <p>{product.description}</p>
              {/* toFixed(2) formats the price to always show 2 decimal places */}
              <span className="product-price">${product.price.toFixed(2)}</span>
              {/* Conditional CSS class changes the colour based on stock level */}
              <span className={`product-stock ${product.stock > 0 ? 'in-stock' : 'out-of-stock'}`}>
                {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default Products
```

---

## src/pages/Orders.tsx — Orders Page

```tsx
import React, { useEffect, useState } from 'react'
import apiClient from '../api/client'

// TypeScript interfaces — define the shape of order data from the Order Service.
interface OrderItem {
  productId: number
  name:      string
  quantity:  number
  price:     number
}

interface Order {
  id:           number
  userId:       number
  customerName: string
  items:        OrderItem[]
  total:        number
  status:       string
  createdAt:    string
}

// Maps status strings to CSS class names for colour-coded badges.
// Record<string, string> is a TypeScript type for an object with string keys and values.
const statusColors: Record<string, string> = {
  pending:    'badge-pending',
  processing: 'badge-processing',
  completed:  'badge-completed',
  cancelled:  'badge-cancelled',
}

const Orders: React.FC = () => {
  const [orders,   setOrders]   = useState<Order[]>([])
  const [loading,  setLoading]  = useState(true)
  const [error,    setError]    = useState('')
  const [showForm, setShowForm] = useState(false) // toggles the create order form

  // Read the token directly from localStorage to check if the user is logged in.
  // This is checked before fetching — unauthenticated users see a "please login" message.
  const token = localStorage.getItem('token')

  const fetchOrders = async () => {
    setLoading(true)
    setError('')
    try {
      // GET /api/orders — the Axios interceptor attaches the Bearer token automatically.
      // The gateway forwards this to the Order Service with X-User-Id/X-User-Role headers.
      const res = await apiClient.get('/orders')
      setOrders(res.data)
    } catch {
      setError('Failed to load orders.')
    } finally {
      setLoading(false)
    }
  }

  // Only fetch orders if the user is logged in.
  // If not logged in, skip the fetch and stop the loading spinner.
  // token is in the dependency array — if it changes (login/logout), this re-runs.
  useEffect(() => {
    if (token) fetchOrders()
    else setLoading(false)
  }, [token])

  // Show a locked state if the user is not authenticated.
  if (!token) {
    return (
      <div className="orders-page">
        <div className="empty-state">
          <h3>Login to view orders</h3>
          <p>Please sign in to see your order history.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="orders-page">
      <div className="page-header">
        <h2>Orders</h2>
        {/* Toggle the create form. Button label changes based on showForm state. */}
        <button onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancel' : '+ New Order'}
        </button>
      </div>

      {/* CreateOrderForm is only mounted when showForm is true.
          onCreated callback hides the form and refreshes the order list. */}
      {showForm && <CreateOrderForm onCreated={() => { setShowForm(false); fetchOrders() }} />}

      {loading && <div className="loading-text">Loading orders...</div>}
      {error   && <div className="error-banner">{error}</div>}

      {!loading && !error && orders.length === 0 && (
        <div className="empty-state"><p>No orders yet. Create your first order!</p></div>
      )}

      {!loading && orders.length > 0 && (
        <table className="orders-table">
          <thead>
            <tr>
              <th>Order ID</th><th>Customer</th><th>Items</th>
              <th>Total</th><th>Status</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            {orders.map(order => (
              <tr key={order.id}>
                <td>#{order.id}</td>
                <td>{order.customerName}</td>
                <td>{order.items.length} item(s)</td>
                <td>${order.total.toFixed(2)}</td>
                <td>
                  {/* statusColors[order.status] looks up the CSS class for this status.
                      || '' is a fallback in case an unknown status is returned. */}
                  <span className={`status-badge ${statusColors[order.status] || ''}`}>
                    {order.status}
                  </span>
                </td>
                {/* toLocaleDateString() formats the ISO timestamp to a readable date */}
                <td>{new Date(order.createdAt).toLocaleDateString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

// ── CreateOrderForm — sub-component ──────────────────────────────────────────
//
// Defined in the same file since it's only used by the Orders page.
// Receives onCreated as a prop — called after a successful order creation
// so the parent can hide the form and refresh the list.
const CreateOrderForm: React.FC<{ onCreated: () => void }> = ({ onCreated }) => {
  const [customerName, setCustomerName] = useState('')
  // items is an array of order line items. Starts with one empty item row.
  const [items,        setItems]        = useState([{ productId: 1, name: '', quantity: 1, price: 0 }])
  const [submitting,   setSubmitting]   = useState(false)
  const [error,        setError]        = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      // POST /api/orders — sends customerName and items array to the gateway.
      // The gateway forwards to the Order Service which calculates the total.
      await apiClient.post('/orders', { customerName, items })
      onCreated() // notify parent: form can close, list should refresh
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to create order.')
    } finally {
      setSubmitting(false)
    }
  }

  // Adds a new empty item row to the form.
  // Spread [...items, newItem] creates a new array (never mutate state directly).
  const addItem = () => {
    setItems([...items, { productId: items.length + 1, name: '', quantity: 1, price: 0 }])
  }

  // Updates a single field of a single item by index.
  // [...items] creates a shallow copy so we don't mutate the original state array.
  // (updated[index] as any)[field] uses 'any' to allow dynamic field access in TypeScript.
  const updateItem = (index: number, field: string, value: string | number) => {
    const updated = [...items]
    ;(updated[index] as any)[field] = value
    setItems(updated)
  }

  return (
    <form className="order-form" onSubmit={handleSubmit}>
      <h3>Create New Order</h3>
      {error && <div className="form-error">{error}</div>}

      <div className="form-group">
        <label>Customer Name</label>
        <input type="text" value={customerName} onChange={e => setCustomerName(e.target.value)} required />
      </div>

      <div className="form-items">
        <label>Items</label>
        {items.map((item, i) => (
          <div className="form-item-row" key={i}>
            {/* Each row has three inputs: product name, quantity, and unit price */}
            <input type="text"   placeholder="Product name" value={item.name}     onChange={e => updateItem(i, 'name',     e.target.value)}                    required />
            <input type="number" placeholder="Qty"          value={item.quantity} onChange={e => updateItem(i, 'quantity', parseInt(e.target.value)   || 1)} min={1}      required />
            <input type="number" placeholder="Price"        value={item.price}    onChange={e => updateItem(i, 'price',    parseFloat(e.target.value) || 0)} min={0} step="0.01" required />
          </div>
        ))}
        <button type="button" onClick={addItem}>+ Add Item</button>
      </div>

      <button type="submit" disabled={submitting}>
        {submitting ? 'Creating...' : 'Create Order'}
      </button>
    </form>
  )
}

export default Orders
```
