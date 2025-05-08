const Sequelize = require('sequelize');
const sequelize = require('../config/db');

const Task = sequelize.define('Task', {
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
  priority: {
    type: Sequelize.ENUM('LOW', 'MEDIUM', 'HIGH'),
    defaultValue: 'MEDIUM'
  },
  deadline: {
    type: Sequelize.DATE
  },
  status: {
    type: Sequelize.ENUM('WAITING', 'IN_PROGRESS', 'DONE'),
    defaultValue: 'WAITING'
  }
});

module.exports = Task;