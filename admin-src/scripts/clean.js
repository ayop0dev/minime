/**
 * Clean build artifacts
 * 
 * Removes:
 * - .next/     (Next.js build cache)
 * - out/       (Static export output)
 * - ../assets/admin-app/  (Plugin assets)
 * 
 * Safe for Windows - uses Node.js APIs exclusively.
 */

const fs = require('fs');
const path = require('path');

const dirs = [
  path.resolve(__dirname, '..', '.next'),
  path.resolve(__dirname, '..', 'out'),
  path.resolve(__dirname, '..', '..', 'assets', 'admin-app'),
];

function removeDir(dir) {
  if (fs.existsSync(dir)) {
    fs.rmSync(dir, { recursive: true, force: true });
    return true;
  }
  return false;
}

console.log('\n🧹 Cleaning build artifacts...\n');

let removed = 0;
for (const dir of dirs) {
  if (removeDir(dir)) {
    console.log(`✓ Removed: ${dir}`);
    removed++;
  } else {
    console.log(`- Skipped: ${dir} (not found)`);
  }
}

console.log(`\n✅ Cleaned ${removed} director${removed === 1 ? 'y' : 'ies'}\n`);
