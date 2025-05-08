const express = require('express');
const router = express.Router();
const authMiddleware = require('../middlewares/auth.middleware');
const boardsController = require('../controllers/boards.controller');
const usersController = require('../controllers/users.controller');

// Users endpoints
router.post('/auth/register', usersController.register);

// Boards endpoints
router.get('/boards', authMiddleware.isAuthenticated, boardsController.getAllBoards);

module.exports = router;