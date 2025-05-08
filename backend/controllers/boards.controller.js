const Board = require('../models/board.model');

exports.getAllBoards = async (req, res) => {
  try {
    const boards = await Board.findAll();
    return res.send(boards);
  } catch (err) {
    return res.status(500).send(err.message);
  }
};
