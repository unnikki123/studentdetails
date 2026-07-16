const JavaScriptObfuscator = require('javascript-obfuscator');
const fs = require('fs');
const path = require('path');

const jsDir = path.join(__dirname, 'assets', 'js');
const backupDir = path.join(__dirname, 'assets', 'js', 'backup');

// Create backup directory if it doesn't exist
if (!fs.existsSync(backupDir)) {
  fs.mkdirSync(backupDir, { recursive: true });
}

// Get all JS files
const jsFiles = fs.readdirSync(jsDir).filter(file => file.endsWith('.js'));

console.log('Starting JavaScript obfuscation...');

jsFiles.forEach(file => {
  const filePath = path.join(jsDir, file);
  const backupPath = path.join(backupDir, file);
  
  // Read original file
  const originalCode = fs.readFileSync(filePath, 'utf8');
  
  // Backup original file
  if (!fs.existsSync(backupPath)) {
    fs.writeFileSync(backupPath, originalCode);
    console.log(`Backed up: ${file}`);
  }
  
  // Obfuscate
  const obfuscationResult = JavaScriptObfuscator.obfuscate(originalCode, {
    compact: true,
    controlFlowFlattening: true,
    controlFlowFlatteningThreshold: 0.75,
    deadCodeInjection: true,
    deadCodeInjectionThreshold: 0.4,
    debugProtection: false,
    debugProtectionInterval: 0,
    disableConsoleOutput: true,
    identifierNamesGenerator: 'hexadecimal',
    log: false,
    numbersToExpressions: true,
    renameGlobals: false,
    selfDefending: false,
    simplify: true,
    splitStrings: true,
    splitStringsChunkLength: 10,
    stringArray: true,
    stringArrayEncoding: ['rc4'],
    stringArrayIndexShift: true,
    stringArrayWrappersCount: 2,
    stringArrayWrappersChainedCalls: true,
    stringArrayWrappersParametersMaxCount: 2,
    stringArrayWrappersType: 'function',
    stringArrayThreshold: 0.75,
    transformObjectKeys: true,
    unicodeEscapeSequence: false
  });
  
  // Write obfuscated code
  fs.writeFileSync(filePath, obfuscationResult.getObfuscatedCode());
  console.log(`Obfuscated: ${file}`);
});

console.log('JavaScript obfuscation complete!');
console.log('Original files backed up to: assets/js/backup/');
