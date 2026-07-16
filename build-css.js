const postcss = require('postcss');
const cssnano = require('cssnano');
const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, 'assets', 'css');
const backupDir = path.join(__dirname, 'assets', 'css', 'backup');

// Create backup directory if it doesn't exist
if (!fs.existsSync(backupDir)) {
  fs.mkdirSync(backupDir, { recursive: true });
}

// Get all CSS files
const cssFiles = fs.readdirSync(cssDir).filter(file => file.endsWith('.css'));

console.log('Starting CSS minification...');

cssFiles.forEach(async (file) => {
  const filePath = path.join(cssDir, file);
  const backupPath = path.join(backupDir, file);
  
  // Read original file
  const originalCss = fs.readFileSync(filePath, 'utf8');
  
  // Backup original file
  if (!fs.existsSync(backupPath)) {
    fs.writeFileSync(backupPath, originalCss);
    console.log(`Backed up: ${file}`);
  }
  
  try {
    // Minify CSS
    const result = await postcss([cssnano]).process(originalCss, {
      from: filePath,
      to: filePath
    });
    
    // Write minified CSS
    fs.writeFileSync(filePath, result.css);
    console.log(`Minified: ${file}`);
  } catch (error) {
    console.error(`Error minifying ${file}:`, error.message);
  }
});

console.log('CSS minification complete!');
console.log('Original files backed up to: assets/css/backup/');
