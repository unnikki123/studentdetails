const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

console.log('========================================');
console.log('Starting build process...');
console.log('========================================\n');

// Check if node_modules exists
if (!fs.existsSync(path.join(__dirname, 'node_modules'))) {
  console.log('Installing dependencies...');
  try {
    execSync('npm install', { stdio: 'inherit' });
    console.log('Dependencies installed!\n');
  } catch (error) {
    console.error('Error installing dependencies:', error.message);
    process.exit(1);
  }
}

// Build JavaScript
console.log('Building JavaScript...');
try {
  execSync('node build-js.js', { stdio: 'inherit' });
  console.log('JavaScript build complete!\n');
} catch (error) {
  console.error('Error building JavaScript:', error.message);
}

// Build CSS
console.log('Building CSS...');
try {
  execSync('node build-css.js', { stdio: 'inherit' });
  console.log('CSS build complete!\n');
} catch (error) {
  console.error('Error building CSS:', error.message);
}

console.log('========================================');
console.log('Build process complete!');
console.log('========================================');
console.log('\nBackup locations:');
console.log('- JavaScript: assets/js/backup/');
console.log('- CSS: assets/css/backup/');
console.log('\nTo restore original files, copy from backup folders.');
