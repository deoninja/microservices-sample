class ProductStore {
  constructor() {
    this.products = [
      {
        id: 1,
        name: 'Wireless Headphones',
        price: 79.99,
        description: 'Noise-canceling Bluetooth headphones with 30hr battery',
        stock: 45,
        createdAt: '2024-01-01T00:00:00Z',
      },
      {
        id: 2,
        name: 'Smart Watch',
        price: 199.99,
        description: 'Fitness tracker with heart rate monitor and GPS',
        stock: 30,
        createdAt: '2024-01-02T00:00:00Z',
      },
      {
        id: 3,
        name: 'Laptop Stand',
        price: 49.99,
        description: 'Adjustable aluminum stand for 13-17 inch laptops',
        stock: 100,
        createdAt: '2024-01-03T00:00:00Z',
      },
      {
        id: 4,
        name: 'Mechanical Keyboard',
        price: 129.99,
        description: 'RGB backlit mechanical keyboard with Cherry MX switches',
        stock: 25,
        createdAt: '2024-01-04T00:00:00Z',
      },
      {
        id: 5,
        name: 'USB-C Hub',
        price: 34.99,
        description: '7-in-1 USB-C hub with HDMI, USB 3.0, SD card reader',
        stock: 60,
        createdAt: '2024-01-05T00:00:00Z',
      },
    ];
    this.nextId = 6;
  }

  getProducts(search) {
    if (!search) return this.products;
    const q = search.toLowerCase();
    return this.products.filter((p) => p.name.toLowerCase().includes(q));
  }

  getProduct(id) {
    return this.products.find((p) => p.id === Number(id)) || null;
  }

  createProduct(data) {
    const product = {
      id: this.nextId++,
      name: data.name,
      price: Number(data.price),
      description: data.description || '',
      stock: Number(data.stock) || 0,
      createdAt: new Date().toISOString(),
    };
    this.products.push(product);
    return product;
  }

  updateProduct(id, data) {
    const index = this.products.findIndex((p) => p.id === Number(id));
    if (index === -1) return null;
    const allowed = ['name', 'price', 'description', 'stock'];
    for (const field of allowed) {
      if (data[field] !== undefined) {
        this.products[index][field] = data[field];
      }
    }
    return this.products[index];
  }

  deleteProduct(id) {
    const index = this.products.findIndex((p) => p.id === Number(id));
    if (index === -1) return false;
    this.products.splice(index, 1);
    return true;
  }
}

module.exports = new ProductStore();
