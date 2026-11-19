const { requestHandler } = require('../server.js');

module.exports = async (req, res) => {
  try {
    if (req.url && !req.url.startsWith('/api/') && req.url !== '/api') {
      req.url = '/api' + (req.url.startsWith('/') ? '' : '/') + req.url;
    }
    await requestHandler(req, res);
  } catch (error) {
    console.error('Vercel API error (/api/[...route]):', error);
    if (!res.headersSent) {
      res.statusCode = 500;
      res.setHeader('Content-Type', 'application/json; charset=utf-8');
      res.end(JSON.stringify({ error: 'Internal Server Error', message: error.message }));
    }
  }
};
