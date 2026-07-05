import React, { useEffect, useState } from 'react'
import apiClient from '../api/client'

interface Product {
  id: number
  name: string
  price: number
  description: string
  stock: number
  createdAt: string
}

const Products: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchProducts = async (query?: string) => {
    setLoading(true)
    setError('')
    try {
      const url = query ? `/products?search=${encodeURIComponent(query)}` : '/products'
      const res = await apiClient.get(url)
      setProducts(res.data)
    } catch {
      setError('Failed to load products. Make sure the backend services are running.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchProducts()
  }, [])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    fetchProducts(search)
  }

  const handleClear = () => {
    setSearch('')
    fetchProducts()
  }

  return (
    <div className="products-page">
      <div className="page-header">
        <h2>Products</h2>
        <form className="search-bar" onSubmit={handleSearch}>
          <input
            type="text"
            placeholder="Search products..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
          <button type="submit" className="btn btn-primary">Search</button>
          {search && (
            <button type="button" className="btn btn-secondary" onClick={handleClear}>Clear</button>
          )}
        </form>
      </div>

      {loading && (
        <div className="loading-grid">
          {[1, 2, 3].map(i => (
            <div className="skeleton-card" key={i}>
              <div className="skeleton-line w-60"></div>
              <div className="skeleton-line w-40"></div>
              <div className="skeleton-line w-80"></div>
              <div className="skeleton-line w-30"></div>
            </div>
          ))}
        </div>
      )}

      {error && <div className="error-banner">{error}</div>}

      {!loading && !error && products.length === 0 && (
        <div className="empty-state">
          <p>No products found{search ? ` for "${search}"` : ''}.</p>
        </div>
      )}

      {!loading && !error && products.length > 0 && (
        <div className="product-grid">
          {products.map(product => (
            <div className="product-card" key={product.id}>
              <div className="product-image-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6366f1" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
              </div>
              <div className="product-info">
                <h3>{product.name}</h3>
                <p className="product-description">{product.description}</p>
                <div className="product-meta">
                  <span className="product-price">${product.price.toFixed(2)}</span>
                  <span className={`product-stock ${product.stock > 0 ? 'in-stock' : 'out-of-stock'}`}>
                    {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'}
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default Products
