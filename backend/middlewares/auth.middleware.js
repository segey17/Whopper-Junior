const jwt = require('jsonwebtoken');
const config = require('../config/auth');

exports.isAuthenticated = (req, res, next) => {
  const token = req.headers['authorization'];
  if (!token) return res.status(401).send('No token provided.');

  jwt.verify(token, config.secret, (err, decoded) => {
    if (err) return res.status(401).send('Failed to authenticate token.');
    req.userId = decoded.id;
    next();
  });
};