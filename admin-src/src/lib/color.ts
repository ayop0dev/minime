/**
 * Color utility functions for WCAG contrast and luminance calculations
 */

/**
 * Convert hex color to RGB values
 */
export function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  const clean = hex.replace('#', '');
  const result = /^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(clean);
  if (!result) return null;
  return {
    r: parseInt(result[1], 16),
    g: parseInt(result[2], 16),
    b: parseInt(result[3], 16),
  };
}

/**
 * Calculate relative luminance using WCAG formula
 * Accounts for human eye sensitivity to green > red > blue
 */
export function getRelativeLuminance(r: number, g: number, b: number): number {
  const [rs, gs, bs] = [r, g, b].map((val) => val / 255);

  // Apply gamma correction
  const [rLinear, gLinear, bLinear] = [rs, gs, bs].map((val) =>
    val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4)
  );

  // WCAG coefficients
  const luminance = 0.2126 * rLinear + 0.7152 * gLinear + 0.0722 * bLinear;
  return luminance;
}

/**
 * Determine if hex color is dark or light
 * Returns true if luminance < 0.5 (dark), false if >= 0.5 (light)
 */
export function isHexColorDark(hex: string): boolean {
  const rgb = hexToRgb(hex);
  if (!rgb) return false; // Default to light on parse error

  const luminance = getRelativeLuminance(rgb.r, rgb.g, rgb.b);
  return luminance < 0.5;
}

/**
 * Get contrasting text color (white or black)
 */
export function getContrastColor(hex: string): string {
  return isHexColorDark(hex) ? '#ffffff' : '#000000';
}
