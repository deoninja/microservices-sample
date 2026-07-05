import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

interface HealthResponse {
  status: string
  service: string
  timestamp: string
}

const features = [
  {
    title: 'User Management',
    description: 'Secure user authentication and profile management powered by a dedicated Node.js microservice.',
    icon: (
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    ),
  },
  {
    title: 'Product Catalog',
    description: 'Full CRUD product catalog with search and filtering, served by its own microservice.',
    icon: (
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
      </svg>
    ),
  },
  {
    title: 'Order Processing',
    description: 'End-to-end order lifecycle management with status tracking across services.',
    icon: (
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
      </svg>
    ),
  },
]

const Home: React.FC = () => {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [healthError, setHealthError] = useState(false)

  useEffect(() => {
    apiClient.get('/health')
      .then(res => setHealth(res.data))
      .catch(() => setHealthError(true))
  }, [])

  return (
    <div className="home-page">
      <section className="hero">
        <div className="hero-content">
          <h1>Microservices <span className="highlight">Demo</span></h1>
          <p className="hero-subtitle">
            A sample application showcasing React + Laravel (API Gateway) + Node.js (Microservices) architecture.
          </p>
          <div className="hero-actions">
            <Link to="/products" className="btn btn-primary">Browse Products</Link>
            <Link to="/orders" className="btn btn-secondary">View Orders</Link>
          </div>
        </div>
        <div className="hero-status">
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
        </div>
      </section>

      <section className="features">
        <h2>Architecture Components</h2>
        <div className="feature-grid">
          {features.map((f, i) => (
            <div className="feature-card" key={i}>
              <div className="feature-icon">{f.icon}</div>
              <h3>{f.title}</h3>
              <p>{f.description}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="arch-section">
        <h2>System Architecture</h2>
        <div className="arch-diagram">
          <div className="arch-layer">
            <div className="arch-item frontend">React Frontend <span>:3000</span></div>
          </div>
          <div className="arch-arrow">↓ ↑ HTTP</div>
          <div className="arch-layer">
            <div className="arch-item gateway">Laravel API Gateway <span>:8000</span></div>
          </div>
          <div className="arch-arrow">↓ ↑ HTTP</div>
          <div className="arch-layer layer-three">
            <div className="arch-item service">User Service <span>:3001</span></div>
            <div className="arch-item service">Product Service <span>:3002</span></div>
            <div className="arch-item service">Order Service <span>:3003</span></div>
          </div>
        </div>
      </section>
    </div>
  )
}

export default Home
