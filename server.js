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
        try {
          execSync('mariadb -u root -e "CREATE DATABASE IF NOT EXISTS smm_panel; CREATE USER IF NOT EXISTS \'root\'@\'127.0.0.1\' IDENTIFIED BY \'\'; CREATE USER IF NOT EXISTS \'root\'@\'localhost\' IDENTIFIED BY \'\'; ALTER USER \'root\'@\'127.0.0.1\' IDENTIFIED BY \'\'; ALTER USER \'root\'@\'localhost\' IDENTIFIED BY \'\'; GRANT ALL PRIVILEGES ON *.* TO \'root\'@\'127.0.0.1\' WITH GRANT OPTION; GRANT ALL PRIVILEGES ON *.* TO \'root\'@\'localhost\' WITH GRANT OPTION; FLUSH PRIVILEGES;"', { stdio: 'ignore' });
          const hasTables = execSync('mariadb -u root smm_panel -e "SHOW TABLES;"', { encoding: 'utf8' });
          if (!hasTables || hasTables.trim().length === 0) {
            console.log('[RoseSMM] Initializing smm_panel database from database.sql...');
            execSync('mariadb -u root smm_panel < database.sql', { stdio: 'ignore' });
          }
        } catch (dbInitErr) {
          console.warn('[RoseSMM] Database permission setup notice:', dbInitErr.message);
        }
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
  env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4' }
});

phpServer.on('error', (err) => {
  console.error('[RoseSMM] PHP Server error:', err);
});

phpServer.on('exit', (code, signal) => {
  console.log(`[RoseSMM] PHP server exited with code ${code} and signal ${signal}`);
});

// 3. Periodic Background Cron Job Runner (Every 60s)
setInterval(() => {
  try {
    execSync('php cron.php', { stdio: 'ignore', timeout: 15000 });
  } catch (cronErr) {
    // Ignore transient cron errors
  }
}, 60000);

process.on('SIGINT', () => {
  phpServer.kill('SIGINT');
  process.exit(0);
});

process.on('SIGTERM', () => {
  phpServer.kill('SIGTERM');
  process.exit(0);
});
