const Sequelize = require('sequelize');
const sequelize = require('../config/db');

const Board = sequelize.define('Board', {
  id: {
    type: Sequelize.INTEGER,
    primaryKey: true,
    autoIncrement: true
  },
  title: {
    type: Sequelize.STRING,
    allowNull: false
  },
  description: {
    type: Sequelize.TEXT
  },
  isPublic: {
    type: Sequelize.BOOLEAN,
    defaultValue: false
  }
});

module.exports = Board;