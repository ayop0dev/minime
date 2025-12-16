/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',  // Static export - generates out/ directory
  
  // Disable image optimization (requires server)
  images: {
    unoptimized: true,  // No server-side image optimization
  },
  
  // Strict mode for React
  reactStrictMode: true,
};

module.exports = nextConfig;
