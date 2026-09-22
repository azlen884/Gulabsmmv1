// Plain JavaScript Server Runner for RoseSMM Panel
import { spawn, execSync } from 'child_process';
import http from 'http';

console.log('[RoseSMM] Initializing system services...');

// 1. Ensure MariaDB Daemon is running
function ensureDatabase() {
  try {
    execSync('mariadb -u root -e "SELECT 1;"', { stdio: 'ignore', timeout: 2000 });
    console.log('[RoseSMM] MariaDB is active.');
  } catch (err) {
    console.log('[RoseSMM] Starting MariaDB service...');
    try {
      execSync('mkdir -p /run/mysqld && chmod 777 /run/mysqld');
      const dbProcess = spawn('/usr/sbin/mariadbd', ['--user=root'], {
        detached: true,
        stdio: 'ignore'
      });
      dbProcess.unref();

      // Wait for socket to become available
      let connected = false;
      for (let i = 0; i < 15; i++) {
        try {
          execSync('mariadb -u root -e "SELECT 1;"', { stdio: 'ignore', timeout: 1000 });
          connected = true;
          break;
        } catch (e) {
          execSync('sleep 0.5');
        }
      }
      if (connected) {
        console.log('[RoseSMM] MariaDB connected successfully.');
      } else {
        console.warn('[RoseSMM] MariaDB start check timed out, continuing...');
      }
    } catch (startErr) {
      console.error('[RoseSMM] Failed to start MariaDB:', startErr.message);
    }
  }
}

ensureDatabase();

// 2. Spawn PHP Built-in Server on 0.0.0.0:3000
console.log('[RoseSMM] Launching PHP server on 0.0.0.0:3000 with router.php...');
const phpServer = spawn('php', ['-S', '0.0.0.0:3000', 'router.php'], {
  stdio: 'inherit',
  env: process.env
});

phpServer.on('error', (err) => {
  console.error('[RoseSMM] PHP Server error:', err);
});

phpServer.on('exit', (code, signal) => {
  console.log(`[RoseSMM] PHP server exited with code ${code} and signal ${signal}`);
});

process.on('SIGINT', () => {
  phpServer.kill('SIGINT');
  process.exit(0);
});

process.on('SIGTERM', () => {
  phpServer.kill('SIGTERM');
  process.exit(0);
});
