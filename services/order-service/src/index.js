const express = require('express');
const cors = require('cors');
const orderRoutes = require('./routes/orders');

const app = express();
const PORT = 3003;

app.use(cors());
app.use(express.json());

app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] ${req.method} ${req.url}`);
  next();
});

app.use('/api', orderRoutes);

app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    service: 'order-service',
    timestamp: new Date().toISOString(),
  });
});

app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

app.use((err, req, res, next) => {
  console.error('Order Service Error:', err.message);
  res.status(500).json({ error: 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`Order Service running on port ${PORT}`);
});
