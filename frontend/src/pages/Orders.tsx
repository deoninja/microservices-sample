import React, { useEffect, useState } from 'react'
import apiClient from '../api/client'

interface OrderItem {
  productId: number
  name: string
  quantity: number
  price: number
}

interface Order {
  id: number
  userId: number
  customerName: string
  items: OrderItem[]
  total: number
  status: string
  createdAt: string
}

const statusColors: Record<string, string> = {
  pending: 'badge-pending',
  processing: 'badge-processing',
  completed: 'badge-completed',
  cancelled: 'badge-cancelled',
}

const Orders: React.FC = () => {
  const [orders, setOrders] = useState<Order[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showForm, setShowForm] = useState(false)
  const token = localStorage.getItem('token')

  const fetchOrders = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await apiClient.get('/orders')
      setOrders(res.data)
    } catch {
      setError('Failed to load orders.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (token) fetchOrders()
    else setLoading(false)
  }, [token])

  if (!token) {
    return (
      <div className="orders-page">
        <div className="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
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
        <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancel' : '+ New Order'}
        </button>
      </div>

      {showForm && <CreateOrderForm onCreated={() => { setShowForm(false); fetchOrders() }} />}

      {loading && <div className="loading-text">Loading orders...</div>}
      {error && <div className="error-banner">{error}</div>}

      {!loading && !error && orders.length === 0 && (
        <div className="empty-state">
          <p>No orders yet. Create your first order!</p>
        </div>
      )}

      {!loading && orders.length > 0 && (
        <div className="table-wrapper">
          <table className="orders-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
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
                    <span className={`status-badge ${statusColors[order.status] || ''}`}>
                      {order.status}
                    </span>
                  </td>
                  <td>{new Date(order.createdAt).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

const CreateOrderForm: React.FC<{ onCreated: () => void }> = ({ onCreated }) => {
  const [customerName, setCustomerName] = useState('')
  const [items, setItems] = useState([{ productId: 1, name: '', quantity: 1, price: 0 }])
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      await apiClient.post('/orders', { customerName, items })
      onCreated()
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to create order.')
    } finally {
      setSubmitting(false)
    }
  }

  const addItem = () => {
    setItems([...items, { productId: items.length + 1, name: '', quantity: 1, price: 0 }])
  }

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
            <input type="text" placeholder="Product name" value={item.name} onChange={e => updateItem(i, 'name', e.target.value)} required />
            <input type="number" placeholder="Qty" value={item.quantity} min={1} onChange={e => updateItem(i, 'quantity', parseInt(e.target.value) || 1)} required />
            <input type="number" placeholder="Price" value={item.price} min={0} step="0.01" onChange={e => updateItem(i, 'price', parseFloat(e.target.value) || 0)} required />
          </div>
        ))}
        <button type="button" className="btn btn-secondary btn-sm" onClick={addItem}>+ Add Item</button>
      </div>
      <button type="submit" className="btn btn-primary" disabled={submitting}>
        {submitting ? 'Creating...' : 'Create Order'}
      </button>
    </form>
  )
}

export default Orders
