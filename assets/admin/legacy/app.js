// =====================
// Config
// =====================
const MINIME = window.MINIME_CONFIG || {};
const BASE_URL = MINIME.baseUrl || '/';
const FRONT_SLUG = MINIME.frontSlug || 'minime';
const REST_ROOT = MINIME.restRoot || (BASE_URL + 'wp-json/minime/v1');
const API_BASE = REST_ROOT;
const API_URL = REST_ROOT + '/data';

// =====================
// Helpers
// =====================

/* Removed unused Font Awesome helper `getIconHtml` to reduce bundle size */

// Remove only background layers created by this script.
// This will remove #custom-bg, #lib-custom-bg and any elements with
// .lb-bg-layer or .lb-bg-custom that are outside `#app`.
function cleanupBgLayers() {
  // Remove specific known IDs first
  ["custom-bg", "lib-custom-bg"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.remove();
  });

  // Remove any leftover layer elements that are outside the app container
  document.querySelectorAll(".lb-bg-layer, .lb-bg-custom").forEach((el) => {
    if (!el.closest('#app')) {
      el.remove();
    }
  });
}

// cleanupBgLayers: Remove only dynamic background containers created by
// this script. It intentionally avoids touching any elements that are
// children of `#app` so it won't remove app content. Safe to call
// multiple times.

// Decode Base64-encoded custom code
function decodeCustomCode(encoded) {
  try {
    if (!encoded) {
      console.log('[minime] decodeCustomCode: empty input');
      return "";
    }
    const decoded = decodeURIComponent(escape(atob(encoded)));
    console.log('[minime] decodeCustomCode success, length:', decoded.length);
    return decoded;
  } catch (e) {
    console.error("[minime] Failed to decode custom code:", e);
    console.error("[minime] Input was:", encoded);
    return "";
  }
}

// Normalize a button value into a usable href.
function normalizeButtonHref(raw) {
  if (!raw) return "#";

  const v = String(raw).trim();
  const lower = v.toLowerCase();

  if (
    lower.startsWith("http://") ||
    lower.startsWith("https://") ||
    lower.startsWith("mailto:") ||
    lower.startsWith("tel:") ||
    lower.startsWith("whatsapp:") ||
    lower.startsWith("sms:")
  ) {
    return v;
  }

  if (v.includes("@") && !v.includes(" ")) {
    return "mailto:" + v;
  }

  const phoneCandidate = v.replace(/[\s()+\-\.]/g, "");
  if (/^\+?\d{6,}$/.test(phoneCandidate)) {
    return "tel:" + phoneCandidate;
  }

  if (v.includes(".") || v.startsWith("www.")) {
    return /^\/\//.test(v) ? v : "https://" + v.replace(/^\/+/, "");
  }

  return v;
}

// =====================
// Theme Detection
// =====================
// Calculate relative luminance of a hex color using the WCAG formula.
// Returns a value between 0 (black) and 1 (white).
function getRelativeLuminance(hexColor) {
  if (!hexColor || typeof hexColor !== 'string') {
    return 0;
  }

  // Remove # if present
  let hex = hexColor.trim();
  if (hex.startsWith('#')) {
    hex = hex.substring(1);
  }

  // Parse RGB values
  if (hex.length === 3) {
    // Shorthand notation (#fff → #ffffff)
    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
  }

  if (hex.length !== 6) {
    return 0;
  }

  const r = parseInt(hex.substring(0, 2), 16) / 255;
  const g = parseInt(hex.substring(2, 4), 16) / 255;
  const b = parseInt(hex.substring(4, 6), 16) / 255;

  // Convert to linear RGB
  const toLinear = (c) => {
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  };

  const rLin = toLinear(r);
  const gLin = toLinear(g);
  const bLin = toLinear(b);

  // Calculate relative luminance
  return 0.2126 * rLin + 0.7152 * gLin + 0.0722 * bLin;
}

// Determine if the card should use dark or light theme based on background.
// Returns 'dark' for dark backgrounds (light text) or 'light' for light backgrounds (dark text).
function getCardThemeFromBackground(bg) {
  if (!bg || !bg.type) {
    return 'dark'; // Default fallback
  }

  let colorToCheck = null;

  if (bg.type === 'solid' && bg.color) {
    colorToCheck = bg.color;
  } else if (bg.type === 'gradient' && bg.gradient && Array.isArray(bg.gradient.colors)) {
    const colors = bg.gradient.colors.filter(Boolean);
    if (colors.length > 0) {
      colorToCheck = colors[0]; // Use first color in gradient
    }
  }

  if (!colorToCheck) {
    return 'dark'; // Default fallback
  }

  const luminance = getRelativeLuminance(colorToCheck);
  
  // Threshold: luminance < 0.5 = dark background → use light text
  // luminance >= 0.5 = light background → use dark text
  return luminance < 0.5 ? 'dark' : 'light';
}

// =====================
// Profile / Meta mapping
// =====================
// Apply site title, meta description and favicon from API data.
function applyProfileData(data) {
  if (!data) return;

  // Favicon: prefer site_icon_url, then site_icon
  const iconUrl = (data && data.site_icon_url) || (data && data.site_icon) || "";
  if (iconUrl) {
    let link = document.querySelector('link[rel="icon"]');
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      document.head.appendChild(link);
    }
    try {
      link.href = iconUrl;
    } catch (e) {
      console.warn('Failed to set favicon', e);
    }
  }

  // Document title & meta description
  const title = (data && (data.site_title || data.name)) || document.title;
  if (title) document.title = title;

  const desc = (data && (data.site_tagline || data.subtitle || data.bio)) || "";
  let meta = document.querySelector('meta[name="description"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.name = 'description';
    document.head.appendChild(meta);
  }
  meta.content = desc;
}

// =====================
// Background (new API shape)
// =====================
// Legacy background logic removed; `renderBackground` consumes the new API object.
// renderBackground: Apply the provided `background` object to the page.
// Supported shapes (checked by `bg.type`):
//  - "image"    -> uses `bg.image_url` and applies it to `body` as
//                   a fixed, cover background.
//  - "color"    -> uses `bg.color` and applies to `body.backgroundColor`.
//  - "gradient" -> expects `bg.gradient.colors` (array) and
//                   `bg.gradient.angle` (deg). Requires >=2 colors.
//  - "custom"   -> injects sanitized HTML/CSS into `#lib-custom-bg`.
// Safety notes: cleans previous layers first, returns early on invalid
// input, and does defensive checks before using properties.
function renderBackground(bg) {
  // Primary targets:
  // - For image/color/gradient we apply to document.body so the page wrapper stays above it.
  // - For custom we inject into a dynamically created background container.

  // Clean any old bg layers (custom wrappers)
  cleanupBgLayers();

  // Reset body background styles (do not remove other layout styles)
  const body = document.body;
  body.style.backgroundImage = "";
  body.style.backgroundSize = "";
  body.style.backgroundRepeat = "";
  body.style.backgroundAttachment = "";
  body.style.backgroundPosition = "";
  body.style.backgroundColor = "";

  if (!bg || !bg.type) return; // keep defaults

  const type = String(bg.type).toLowerCase();

  // IMAGE: apply as background-image on `body`
  if (type === "image" && bg.image_url) {
    try {
      body.style.backgroundImage = `url("${escapeHtml(bg.image_url)}")`;
      body.style.backgroundSize = "cover";
      body.style.backgroundRepeat = "no-repeat";
      body.style.backgroundAttachment = "fixed";
      body.style.backgroundPosition = "center";
    } catch (e) {
      console.warn('Failed to apply background image', e);
    }

    return;
  }

  // COLOR: solid background color
  if (type === "color" && bg.color) {
    body.style.backgroundColor = bg.color;
    return;
  }

  // GRADIENT: require at least 2 colors, otherwise fallback to defaults
  if (type === "gradient" && bg.gradient && Array.isArray(bg.gradient.colors)) {
    const colors = bg.gradient.colors.filter(Boolean);
    if (colors.length >= 2) {
      const angle = Number(bg.gradient.angle) || 0;
      body.style.backgroundImage = `linear-gradient(${angle}deg, ${colors.join(', ')})`;
      body.style.backgroundSize = "cover";
      body.style.backgroundRepeat = "no-repeat";
      body.style.backgroundAttachment = "fixed";
      body.style.backgroundPosition = "center";
      return;
    }
    // otherwise fallthrough to keep defaults
  }

  // CUSTOM: decode Base64, sanitize, and inject HTML/CSS
  if (type === "custom") {
    try {
      console.log('[minime] Rendering custom background, Base64 input:', bg.custom_code);
      
      const decoded = decodeCustomCode(bg.custom_code);
      console.log('[minime] Decoded custom code:', decoded);
      
      // If empty after decode, remove container and exit
      if (!decoded || !decoded.trim()) {
        console.log('[minime] Custom code is empty, removing container if exists');
        const existing = document.getElementById("lib-custom-bg");
        if (existing) existing.remove();
        return;
      }
      
      // If result contains no HTML tags, treat it as raw CSS → wrap it
      let finalContent = decoded.trim();
      if (!/<[a-z]/i.test(finalContent)) {
        console.log('[minime] No HTML tags detected, wrapping as <style>');
        finalContent = "<style>" + finalContent + "</style>";
      }
      
      // Remove script tags for security
      finalContent = finalContent.replace(/<script[\s\S]*?<\/script>/gi, "");
      
      console.log('[minime] Final content to inject:', finalContent.substring(0, 200));
      
      // Inject into dynamic container
      const container = ensureBackgroundContainer("lib-custom-bg", "lb-bg-custom");
      container.innerHTML = finalContent;
      
      console.log('[minime] Custom background injected successfully into #lib-custom-bg');
    } catch (e) {
      console.error('[minime] Failed to render custom background:', e);
    }
    return;
  }

  // If we reach here, background type unknown or invalid -> keep existing defaults
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Ensure a background container exists and apply default layout styles.
// Returns the element (new or existing).
function ensureBackgroundContainer(id, extraClass) {
  let el = document.getElementById(id);
  if (!el) {
    el = document.createElement('div');
    el.id = id;
    if (extraClass) el.className = extraClass;
    document.body.appendChild(el);
  } else {
    if (extraClass && !el.classList.contains(extraClass)) {
      el.classList.add(extraClass);
    }
  }

  // Default layout so container covers viewport and sits behind the card.
  el.style.position = 'fixed';
  el.style.top = '0';
  el.style.left = '0';
  el.style.width = '100%';
  el.style.height = '100%';
  el.style.zIndex = '-1';
  el.style.pointerEvents = 'none';
  el.setAttribute('aria-hidden', 'true');

  return el;
}

// ensureBackgroundContainer: Single code-path to create/reuse background
// containers (`#custom-bg`, `#lib-custom-bg`, etc.). It applies consistent
// layout styles (cover viewport, behind content) and marks the element
// `aria-hidden` so screen readers ignore background layers.


// =====================
// Custom background helper
// =====================
// NOTE: Custom background rendering is handled inline in renderBackground()
// function (line ~250). No separate helper needed since the logic is simple:
// decode Base64 → auto-wrap CSS if needed → strip scripts → inject into container.


// =====================
// Card Background
// =====================
// Apply card_background settings to the .lb-card element independently from page background.
// Supports solid color and gradient types.
function renderCardBackground(cardBg) {
  const card = document.querySelector('.lb-card');
  if (!card) {
    console.warn('[minime] Card element not found, skipping card background');
    return;
  }

  // Default fallback if no card_background provided
  if (!cardBg || !cardBg.type) {
    cardBg = {
      type: 'solid',
      color: '#ffffff',
      gradient: {
        colors: ['#ffffff', '#eeeeee'],
        angle: 135
      }
    };
  }

  const type = String(cardBg.type).toLowerCase();

  // SOLID: Apply solid color
  if (type === 'solid') {
    const color = cardBg.color || '#ffffff';
    card.style.background = color;
    return;
  }

  // GRADIENT: Construct linear-gradient from colors and angle
  if (type === 'gradient') {
    const gradient = cardBg.gradient || {};
    const colors = Array.isArray(gradient.colors) ? gradient.colors.filter(Boolean) : [];
    const angle = Number(gradient.angle) || 135;

    // Need at least 2 colors for a gradient, otherwise fallback to solid
    if (colors.length >= 2) {
      const gradientStr = `linear-gradient(${angle}deg, ${colors.join(', ')})`;
      card.style.background = gradientStr;
    } else {
      // Fallback to solid color if gradient colors are insufficient
      const fallbackColor = (colors.length > 0 ? colors[0] : null) || cardBg.color || '#ffffff';
      card.style.background = fallbackColor;
    }
    return;
  }

  // Unknown type: keep default
  console.warn('[minime] Unknown card_background type:', type);
}


// =====================
// UI Rendering
// =====================
// Render CTA buttons provided by `data.buttons` into the card
function renderButtons(buttons, card) {
  let linksWrap = card.querySelector(".lb-links");
  if (!linksWrap) {
    linksWrap = document.createElement("div");
    linksWrap.className = "lb-links";
  }

  linksWrap.innerHTML = "";

  if (!Array.isArray(buttons) || buttons.length === 0) {
    if (!card.contains(linksWrap)) {
      card.appendChild(linksWrap);
    }
    return;
  }

  buttons.forEach((btn) => {
    const label = (btn && btn.label) ? String(btn.label) : "";
    const value = (btn && btn.value) ? String(btn.value) : "";

    const a = document.createElement("a");
    a.className = "lb-btn lb-btn--primary";
    a.setAttribute("role", "link");
    a.setAttribute("target", "_blank");
    a.setAttribute("rel", "noopener noreferrer");

    const href = normalizeButtonHref(value);
    a.href = href;

    a.textContent = label || value || "Link";

    linksWrap.appendChild(a);
  });

  if (!card.contains(linksWrap)) {
    card.appendChild(linksWrap);
  }
}

// Build and mount the entire minime card UI from `data`
function renderApp(data) {
  let root = document.getElementById("app");
  if (!root) {
    root = document.createElement("div");
    root.id = "app";
    document.body.appendChild(root);
  }

  root.innerHTML = "";

  const page = document.createElement("div");
  page.className = "lb-page";

  const card = document.createElement("div");
  card.className = "lb-card";

  // Determine and apply theme based on card background
  const cardBg = data.card_background || data.background || {};
  const theme = getCardThemeFromBackground(cardBg);
  card.classList.add(theme === 'dark' ? 'lb-card--dark' : 'lb-card--light');

  // ----- Header -----
  const header = document.createElement("div");
  header.className = "lb-header";

  // Avatar: prefer site_icon_url, then site_icon, then avatar
  const avatarSrc =
    (data && data.site_icon_url) ||
    (data && data.site_icon) ||
    (data && data.avatar) ||
    "";

  if (avatarSrc) {
    const avatarWrap = document.createElement("div");
    avatarWrap.className = "lb-avatar-wrap";

    const avatar = document.createElement("img");
    avatar.className = "lb-avatar";
    avatar.src = avatarSrc;
    avatar.alt = data.site_title || data.name || "Avatar";

    avatarWrap.appendChild(avatar);
    header.appendChild(avatarWrap);
  }

  const textWrap = document.createElement("div");
  textWrap.className = "lb-text";

  // Title: use site_title directly (accept any characters)
  if (data.site_title) {
    const nameEl = document.createElement("h1");
    nameEl.className = "lb-name";
    nameEl.textContent = data.site_title;
    textWrap.appendChild(nameEl);
  } else if (data.name) {
    const nameEl = document.createElement("h1");
    nameEl.className = "lb-name";
    nameEl.textContent = data.name;
    textWrap.appendChild(nameEl);
  }

  // Subtitle: use site_tagline
  if (data.site_tagline) {
    const subtitleEl = document.createElement("p");
    subtitleEl.className = "lb-subtitle";
    subtitleEl.textContent = data.site_tagline;
    textWrap.appendChild(subtitleEl);
  } else if (data.subtitle) {
    const subtitleEl = document.createElement("p");
    subtitleEl.className = "lb-subtitle";
    subtitleEl.textContent = data.subtitle;
    textWrap.appendChild(subtitleEl);
  }

  // Bio
  if (data.bio) {
    const bioEl = document.createElement("p");
    bioEl.className = "lb-bio";
    bioEl.textContent = data.bio;
    textWrap.appendChild(bioEl);
  }

  header.appendChild(textWrap);
  card.appendChild(header);

  // ----- Social Icons -----
  if (Array.isArray(data.socials) && data.socials.length > 0) {
    const socialsWrap = document.createElement("div");
    socialsWrap.className = "lb-socials";

    const chunkSize = 5;

    const iconMap = {
      instagram: "fa-brands fa-instagram",
      facebook: "fa-brands fa-facebook",
      linkedin: "fa-brands fa-linkedin",
      youtube: "fa-brands fa-youtube",
      twitter: "fa-brands fa-x-twitter",
      x: "fa-brands fa-x-twitter",
      whatsapp: "fa-brands fa-whatsapp",
      telegram: "fa-brands fa-telegram",
      tiktok: "fa-brands fa-tiktok",
      snapchat: "fa-brands fa-snapchat",
      github: "fa-brands fa-github",
      dribbble: "fa-brands fa-dribbble",
      behance: "fa-brands fa-behance",
      email: "fa-solid fa-envelope",
      mail: "fa-solid fa-envelope",
      phone: "fa-solid fa-phone",
      call: "fa-solid fa-phone",
      website: "fa-solid fa-globe",
      globe: "fa-solid fa-globe",
    };

    for (let i = 0; i < data.socials.length; i += chunkSize) {
      const row = document.createElement("div");
      row.className = "lb-socials-row";

      const rowItems = data.socials.slice(i, i + chunkSize);

      rowItems.forEach((social) => {
        const a = document.createElement("a");
        a.href = social.url;
        a.target = "_blank";
        a.rel = "noopener noreferrer";
        a.className = "lb-social";

        const span = document.createElement("span");
        span.className = "lb-social-icon";

        const iconEl = document.createElement("i");
        const key = (social.icon || "").toLowerCase();
        iconEl.className = iconMap[key] || "fa-solid fa-circle";

        span.appendChild(iconEl);
        a.appendChild(span);
        row.appendChild(a);
      });

      socialsWrap.appendChild(row);
    }

    card.appendChild(socialsWrap);
  }

  // ----- Buttons (from data.buttons) -----
  renderButtons(data.buttons, card);

  // ----- Footer -----
  const footer = document.createElement("div");
  footer.className = "lb-footer";
  footer.textContent = data.branding_footer_text || "Powered by · Ayop · Headless WP · REST API";

  card.appendChild(footer);
  page.appendChild(card);
  root.appendChild(page);
}

// =====================
// Init
// =====================
async function initMinime() {
  // Check if debug mode is enabled via query parameter
  const urlParams = new URLSearchParams(window.location.search);
  const isDebugMode = urlParams.get('mm_debug') === '1';

  try {
    console.log('[minime] Fetching data from:', API_URL);
    const res = await fetch(API_URL, { cache: "no-store" });
    console.log('[minime] Response status:', res.status, res.statusText);
    
    if (!res.ok) {
      // Capture error details for debug mode
      let errorMessage = `HTTP ${res.status}: ${res.statusText}`;
      try {
        const errorData = await res.json();
        if (errorData.message) {
          errorMessage = `HTTP ${res.status}: ${errorData.message}`;
        } else if (errorData.error) {
          errorMessage = `HTTP ${res.status}: ${errorData.error}`;
        }
      } catch (jsonErr) {
        // JSON parsing failed, keep the basic error message
      }
      
      const error = new Error(errorMessage);
      error.status = res.status;
      error.statusText = res.statusText;
      throw error;
    }

    const data = await res.json();

    // Apply profile/title/meta/fav from API
    applyProfileData(data);

    // Render background using new API shape. If missing or invalid, keep defaults.
    try {
      if (data && data.background && typeof renderBackground === 'function') {
        renderBackground(data.background);
      }
    } catch (e) {
      console.warn('Background render failed', e);
    }

    // Render UI
    renderApp(data);

    // Apply card background (separate from page background)
    try {
      if (typeof renderCardBackground === 'function') {
        renderCardBackground(data.card_background);
      }
    } catch (e) {
      console.warn('Card background render failed', e);
    }
  } catch (err) {
    // Log original error for debugging
    console.error('minime init error', err);

    try {
      // Ensure a single root app container exists
      let root = document.getElementById('app');
      if (!root) {
        root = document.createElement('div');
        root.id = 'app';
        document.body.appendChild(root);
      }

      // Clear any partially rendered content so error box doesn't overlap
      root.innerHTML = '';

      // Reuse or create one .lb-error element inside the root
      let errEl = root.querySelector('.lb-error');
      if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'lb-error';
      }

      // Friendly message for users; keep original error in console only
      errEl.textContent =
        'Could not load minime at the moment. Please try again later.';

      // Append the error element (will be the only child)
      root.appendChild(errEl);

      // Add debug information if mm_debug=1 is present
      if (isDebugMode) {
        const debugEl = document.createElement('pre');
        debugEl.style.cssText = 'margin-top: 16px; padding: 12px; background: rgba(0,0,0,0.1); border-radius: 8px; font-size: 12px; text-align: left; overflow-x: auto; color: #4b5563;';
        
        let debugInfo = 'Debug Information:\n';
        debugInfo += '─────────────────\n';
        
        if (err.status) {
          debugInfo += `HTTP Status: ${err.status}\n`;
        }
        if (err.statusText) {
          debugInfo += `Status Text: ${err.statusText}\n`;
        }
        debugInfo += `Error Message: ${err.message || 'Unknown error'}\n`;
        debugInfo += `API URL: ${API_URL}\n`;
        debugInfo += `Timestamp: ${new Date().toISOString()}`;
        
        debugEl.textContent = debugInfo;
        root.appendChild(debugEl);
      }
    } catch (e) {
      // If even rendering the error fails, log it — but don't throw further.
      console.error('Failed to render error UI', e);
    }
  }
}

// Start
initMinime();
