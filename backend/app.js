const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const routes = require('./routes/api.routes');
const sequelize = require('./config/db');

const app = express();
const PORT = process.env.PORT || 8000;

// Middlewares
app.use(cors());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());

// Routes
app.use('/api', routes);

// Start the server
sequelize.sync().then(() => {
  console.log('Database connected successfully!');
}).catch((err) => {
  console.error('Error connecting to database:', err.message);
});

app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});