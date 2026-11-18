// Run the SQLite-backed QuickBite API server.
require('./server.js').startServer().catch(error => {
  console.error('Failed to start QuickBite server:', error);
  process.exit(1);
});
