/**
 * Copy Next.js Static Export Build to WordPress Plugin
 * 
 * This script copies the static export output from Next.js (out/ directory)
 * to the WordPress plugin assets directory for deployment.
 */

const fs = require('fs');
const path = require('path');

/**
 * Recursively copy directory contents
 */
function copyDirRecursive(src, dest, stats = { files: 0, dirs: 0 }) {
  if (!fs.existsSync(dest)) {
    fs.mkdirSync(dest, { recursive: true });
    stats.dirs++;
  }

  const items = fs.readdirSync(src, { withFileTypes: true });

  items.forEach((item) => {
    const srcPath = path.join(src, item.name);
    const destPath = path.join(dest, item.name);

    if (item.isDirectory()) {
      copyDirRecursive(srcPath, destPath, stats);
    } else if (item.isFile()) {
      fs.copyFileSync(srcPath, destPath);
      stats.files++;
    }
  });

  return stats;
}

/**
 * Rewrite asset URLs in CSS and JS files
 * Changes /fonts/ to correct relative paths based on file depth
 */
function rewriteAssetUrls(baseDir, currentDir = null, rewrittenFiles = 0) {
  if (currentDir === null) currentDir = baseDir;
  if (!fs.existsSync(currentDir)) return rewrittenFiles;

  const items = fs.readdirSync(currentDir, { withFileTypes: true });

  items.forEach((item) => {
    const fullPath = path.join(currentDir, item.name);

    if (item.isDirectory()) {
      rewrittenFiles = rewriteAssetUrls(baseDir, fullPath, rewrittenFiles);
    } else if (item.isFile() && (item.name.endsWith('.css') || item.name.endsWith('.js'))) {
      try {
        let content = fs.readFileSync(fullPath, 'utf8');
        const originalContent = content;

        // Calculate relative depth from baseDir to current file
        const relativePath = path.relative(baseDir, currentDir);
        const depth = relativePath ? relativePath.split(path.sep).length : 0;
        const upPath = '../'.repeat(depth);
        
        // Replace /fonts/ with correct relative path based on file depth
        // E.g., for file at _next/static/css/, depth=3, so upPath='../../../'
        content = content.replace(/\/fonts\//g, upPath + 'fonts/');
        
        // Replace /_next/ with correct relative path (for dynamic imports)
        content = content.replace(/\/_next\//g, upPath + '_next/');

        if (content !== originalContent) {
          fs.writeFileSync(fullPath, content, 'utf8');
          rewrittenFiles++;
        }
      } catch (err) {
        console.error(`⚠️  Error rewriting ${fullPath}:`, err.message);
      }
    }
  });

  return rewrittenFiles;
}

/**
 * Remove directory with Windows file locking support
 */
function removeDir(dir) {
  if (!fs.existsSync(dir)) return;

  try {
    fs.rmSync(dir, { recursive: true, force: true });
  } catch (err) {
    if (err.code === 'EBUSY' || err.code === 'EACCES') {
      console.log('⚠️  Warning: Some files in use, will overwrite...');
      return;
    }
    throw err;
  }
}

// Main deployment logic
const scriptDir = __dirname;
const sourceDir = path.join(scriptDir, '../out');
const targetDir = path.join(scriptDir, '../../assets/admin-app');

console.log('\n' + '='.repeat(60));
console.log('🚀 Deploying Next.js Admin Build to WordPress Plugin');
console.log('='.repeat(60) + '\n');

if (!fs.existsSync(sourceDir)) {
  console.error('❌ ERROR: Build output directory not found!');
  console.error(`Expected: ${sourceDir}`);
  console.error('\nPlease run: npm run build\n');
  process.exit(1);
}

console.log(`📦 Source: ${sourceDir}`);
console.log(`📂 Target: ${targetDir}\n`);

if (fs.existsSync(targetDir)) {
  console.log('🗑️  Cleaning old deployment...');
  removeDir(targetDir);
  console.log('✅ Old deployment removed\n');
}

console.log('📋 Copying files...');
const stats = copyDirRecursive(sourceDir, targetDir);

console.log('🔧 Rewriting asset URLs in CSS/JS files...');
const rewrittenFiles = rewriteAssetUrls(targetDir);
console.log(`✅ Rewrote ${rewrittenFiles} files\n`);

console.log(`\n✨ Deployment complete!`);
console.log(`   Directories created: ${stats.dirs}`);
console.log(`   Files copied: ${stats.files}`);
console.log(`   Asset URLs rewritten: ${rewrittenFiles}`);
console.log('\n' + '='.repeat(60) + '\n');
