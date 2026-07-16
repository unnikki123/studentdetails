# Build Instructions - Code Obfuscation

This project includes a build process to obfuscate JavaScript and minify CSS for production deployment.

## Setup

1. **Install Node.js** (if not already installed)
   - Download from: https://nodejs.org/
   - Verify installation: `node --version`

2. **Install Dependencies**
   ```bash
   npm install
   ```

## Build Commands

### Full Build (Recommended for Production)
```bash
npm run build
```
This will:
- Obfuscate all JavaScript files in `assets/js/`
- Minify all CSS files in `assets/css/`
- Create backups of original files

### Individual Builds

**JavaScript only:**
```bash
npm run build:js
```

**CSS only:**
```bash
npm run build:css
```

## What Happens During Build

### JavaScript Obfuscation
- All `.js` files in `assets/js/` are obfuscated
- Original files are backed up to `assets/js/backup/`
- Obfuscation includes:
  - Variable name mangling
  - Control flow flattening
  - Dead code injection
  - String array encoding
  - Console output disabled

### CSS Minification
- All `.css` files in `assets/css/` are minified
- Original files are backed up to `assets/css/backup/`
- Minification includes:
  - Whitespace removal
  - Comment removal
  - Property optimization
  - Selector merging

## Restoring Original Files

If you need to restore the original files:

**JavaScript:**
```bash
# Copy from backup to original
copy assets\js\backup\*.js assets\js\
```

**CSS:**
```bash
# Copy from backup to original
copy assets\css\backup\*.css assets\css\
```

## Important Notes

- **Always test after building** - Obfuscated code should work identically to original
- **Keep backups** - Original files are automatically backed up
- **Version control** - Commit original files to git before building
- **Debugging** - Use original files for development, build only for production
- **Not security** - This makes code harder to read, not impossible to reverse-engineer

## Development Workflow

1. **Development:** Work with original files in `assets/js/` and `assets/css/`
2. **Testing:** Test your changes with original files
3. **Building:** Run `npm run build` when ready for production
4. **Deployment:** Deploy the obfuscated/minified files
5. **Updates:** Make changes to original files, then rebuild

## Troubleshooting

**Build fails:**
- Ensure Node.js is installed
- Run `npm install` to install dependencies
- Check file permissions

**Obfuscated code doesn't work:**
- Restore from backup
- Check for syntax errors in original code
- Some advanced features may not be compatible with obfuscation

**Need to make changes:**
- Edit original files (not obfuscated ones)
- Run build again after changes
