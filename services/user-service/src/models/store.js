class UserStore {
  constructor() {
    this.users = [
      {
        id: 1,
        username: 'admin',
        password: 'password',
        name: 'Admin User',
        email: 'admin@example.com',
        role: 'admin',
        createdAt: '2024-01-01T00:00:00Z',
      },
      {
        id: 2,
        username: 'john',
        password: 'password',
        name: 'John Doe',
        email: 'john@example.com',
        role: 'user',
        createdAt: '2024-01-02T00:00:00Z',
      },
    ];
    this.nextId = 3;
  }

  getUsers() {
    return this.users.map(({ password, ...u }) => u);
  }

  getUser(id) {
    const user = this.users.find((u) => u.id === Number(id));
    if (!user) return null;
    const { password, ...userWithoutPassword } = user;
    return userWithoutPassword;
  }

  getUserByUsername(username) {
    return this.users.find((u) => u.username === username) || null;
  }

  createUser(data) {
    const user = {
      id: this.nextId++,
      username: data.username,
      password: data.password,
      name: data.name,
      email: data.email,
      role: data.role || 'user',
      createdAt: new Date().toISOString(),
    };
    this.users.push(user);
    const { password, ...created } = user;
    return created;
  }

  updateUser(id, data) {
    const index = this.users.findIndex((u) => u.id === Number(id));
    if (index === -1) return null;
    const updated = { ...this.users[index], ...data, id: this.users[index].id };
    this.users[index] = updated;
    const { password, ...result } = updated;
    return result;
  }

  deleteUser(id) {
    const index = this.users.findIndex((u) => u.id === Number(id));
    if (index === -1) return false;
    this.users.splice(index, 1);
    return true;
  }
}

module.exports = new UserStore();
