// PHP-only backend entry point for QuickBite Grocery
// Run: node backend.js
const { spawn } = require('node:child_process');
const path = require('node:path');

const port = process.env.PORT || '3000';
const projectRoot = __dirname;
const phpProcess = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', '.', 'php-server.php'], {
  cwd: projectRoot,
  stdio: 'inherit',
  env: process.env,
});

phpProcess.on('exit', (code, signal) => {
  if (signal) {
    console.error(`PHP backend exited from signal ${signal}`);
  } else {
    console.error(`PHP backend exited with code ${code}`);
  }
  process.exit(code ?? 1);
});

process.on('SIGINT', () => phpProcess.kill('SIGINT'));
process.on('SIGTERM', () => phpProcess.kill('SIGTERM'));
