#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Determine parent directory using npm lifecycle environment variables
// npm sets INIT_CWD to the directory where npm was invoked
const parentDir = process.env.INIT_CWD;

if (!parentDir) {
    // Not running via npm - skip
    process.exit(0);
}

// Target directory for grid-sync files
const publicVendorDir = path.join(parentDir, 'public', 'vendor');
const targetDir = path.join(publicVendorDir, 'grid-sync');
const sourceDir = path.join(__dirname, 'grid-sync-js', 'src');

// Check if public directory exists (Laravel project indicator)
const publicDir = path.join(parentDir, 'public');
if (!fs.existsSync(publicDir)) {
    // Silent exit - not a Laravel project
    process.exit(0);
}

try {
    console.log('Setting up grid-sync for browser access...');

    // Create public/vendor directory if it doesn't exist
    if (!fs.existsSync(publicVendorDir)) {
        fs.mkdirSync(publicVendorDir, { recursive: true });
        console.log('Created public/vendor directory');
    }

    // Remove old grid-sync directory if exists
    if (fs.existsSync(targetDir)) {
        console.log('Removing old grid-sync files...');
        fs.rmSync(targetDir, { recursive: true, force: true });
    }

    // Copy grid-sync files to public/vendor
    console.log('Copying grid-sync files to public/vendor/grid-sync...');

    // Use platform-appropriate copy command
    const isWindows = process.platform === 'win32';
    if (isWindows) {
        execSync(`xcopy "${sourceDir}" "${targetDir}" /E /I /Y`, { stdio: 'inherit' });
    } else {
        execSync(`cp -r "${sourceDir}" "${targetDir}"`, { stdio: 'inherit' });
    }

    console.log('');
    console.log('Grid-sync installed successfully to public/vendor/grid-sync/');
    console.log('');
    console.log('IMPORTANT: Add to .gitignore: /public/vendor/grid-sync');

} catch (error) {
    console.error('Error during grid-sync setup:', error.message);
    console.error('You may need to manually run: npm run sync-grid');
    process.exit(1);
}
