const Sequelize = require('sequelize');
const config = require('../config/auth');

const sequelize = new Sequelize(config.dbName, config.dbUser, config.dbPassword, {
  host: config.dbHost,
  dialect: 'postgres',
  logging: false
});

module.exports = sequelize;