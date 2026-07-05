import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'

interface LayoutProps {
  children: React.ReactNode
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)
  const userStr = localStorage.getItem('user')
  const user = userStr ? JSON.parse(userStr) : null

  const handleLogout = () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    navigate('/login')
  }

  return (
    <div className="app-layout">
      <nav className="navbar">
        <div className="nav-container">
          <Link to="/" className="nav-brand">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            MicroStore
          </Link>

          <button className="menu-toggle" onClick={() => setMenuOpen(!menuOpen)}>
            <span></span><span></span><span></span>
          </button>

          <div className={`nav-links ${menuOpen ? 'open' : ''}`}>
            <Link to="/" onClick={() => setMenuOpen(false)}>Home</Link>
            <Link to="/products" onClick={() => setMenuOpen(false)}>Products</Link>
            <Link to="/orders" onClick={() => setMenuOpen(false)}>Orders</Link>
            {user ? (
              <div className="nav-user">
                <span className="user-name">{user.name || user.username}</span>
                <button className="btn-logout" onClick={handleLogout}>Logout</button>
              </div>
            ) : (
              <Link to="/login" className="btn-login" onClick={() => setMenuOpen(false)}>Login</Link>
            )}
          </div>
        </div>
      </nav>
      <main className="main-content">{children}</main>
      <footer className="footer">
        <p>MicroStore &copy; 2024 — Demo Microservices Architecture</p>
      </footer>
    </div>
  )
}

export default Layout
