// Run the SQLite-backed GREENBASKET API server.
require('./server.js').startServer().catch(error => {
  console.error('Failed to start GREENBASKET server:', error);
  process.exit(1);
});
