class OrderStore {
  constructor() {
    this.orders = [
      {
        id: 1,
        userId: 1,
        customerName: 'Admin User',
        items: [
          { productId: 1, name: 'Wireless Headphones', quantity: 1, price: 79.99 },
        ],
        total: 79.99,
        status: 'completed',
        createdAt: '2024-01-15T10:30:00Z',
      },
      {
        id: 2,
        userId: 2,
        customerName: 'John Doe',
        items: [
          { productId: 2, name: 'Smart Watch', quantity: 1, price: 199.99 },
          { productId: 4, name: 'Mechanical Keyboard', quantity: 1, price: 129.99 },
        ],
        total: 329.98,
        status: 'processing',
        createdAt: '2024-01-20T14:00:00Z',
      },
      {
        id: 3,
        userId: 2,
        customerName: 'John Doe',
        items: [
          { productId: 5, name: 'USB-C Hub', quantity: 2, price: 34.99 },
        ],
        total: 69.98,
        status: 'pending',
        createdAt: '2024-01-25T09:15:00Z',
      },
    ];
    this.nextId = 4;
    this.validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
  }

  getOrders(userId, userRole) {
    if (userRole === 'admin') return this.orders;
    return this.orders.filter((o) => o.userId === Number(userId));
  }

  getOrder(id) {
    return this.orders.find((o) => o.id === Number(id)) || null;
  }

  createOrder(data) {
    const total = data.items.reduce((sum, item) => sum + item.quantity * item.price, 0);
    const order = {
      id: this.nextId++,
      userId: Number(data.userId) || 1,
      customerName: data.customerName || 'Customer',
      items: data.items || [],
      total: Math.round(total * 100) / 100,
      status: 'pending',
      createdAt: new Date().toISOString(),
    };
    this.orders.push(order);
    return order;
  }

  updateOrderStatus(id, status) {
    const order = this.orders.find((o) => o.id === Number(id));
    if (!order) return null;
    if (!this.validStatuses.includes(status)) return { error: true, message: `Invalid status. Must be one of: ${this.validStatuses.join(', ')}` };
    order.status = status;
    return order;
  }
}

module.exports = new OrderStore();
