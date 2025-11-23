// minime – External Admin Panel
// Works with minime/v1 plugin (no ACF)

(function () {
  const MINIME = window.MINIME_CONFIG || {};
  const BASE_URL = MINIME.baseUrl || '/';
  const ADMIN_SLUG = MINIME.adminSlug || 'admin';
  const FRONT_SLUG = MINIME.frontSlug || 'minime';
  const REST_ROOT = MINIME.restRoot || (BASE_URL + 'wp-json/minime/v1');
  const API_BASE = REST_ROOT;

  let authToken = null;
  let settings = {
    public_url: ''
  };

  // DOM refs
  const loginView = document.getElementById("lb-login-view");
  const settingsView = document.getElementById("lb-settings-view");
  const loginForm = document.getElementById("lb-login-form");
  const resetForm = document.getElementById("lb-reset-form");
  const loginEmail = document.getElementById("lb-login-email");
  const loginPassword = document.getElementById("lb-login-password");
  const resetEmail = document.getElementById("lb-reset-email");
  const rememberCheckbox = document.getElementById("lb-remember-me");
  const loginStatus = document.getElementById("lb-login-status");
  const resetStatus = document.getElementById("lb-reset-status");
  const forgotPasswordLink = document.getElementById("lb-forgot-password");
  const backToLoginBtn = document.getElementById("lb-back-to-login");
  const logoutBtn = document.getElementById("lb-logout-btn");
  const settingsStatus = document.getElementById("lb-settings-status");

  // Password reset parameters from URL
  let resetPasswordParams = null;

  // Profile
  const siteTitleInput = document.getElementById("lb-site-title");
  const siteTaglineInput = document.getElementById("lb-site-tagline");
  const bioTextarea = document.getElementById("lb-bio");
  const footerTextInput = document.getElementById("lb-footer-text");
  const avatarPreview = document.getElementById("lb-avatar-preview");
  const avatarFileInput = document.getElementById("lb-avatar-file");
  const siteIconIdInput = document.getElementById("lb-site-icon-id");

  // Background
  const bgTypeSelect = document.getElementById("lb-bg-type");
  const bgImageBlock = document.getElementById("lb-bg-image-block");
  const bgColorBlock = document.getElementById("lb-bg-color-block");
  const bgGradientBlock = document.getElementById("lb-bg-gradient-block");
  const bgCustomBlock = document.getElementById("lb-bg-custom-block");

  const bgImageFileInput = document.getElementById("lb-bg-image-file");
  const bgImageUploadBtn = document.getElementById("lb-bg-image-upload-btn");
  const bgImageIdInput = document.getElementById("lb-bg-image-id");

  const bgColorInput = document.getElementById("lb-bg-color");
  const bgGradColor1 = document.getElementById("lb-bg-grad-color-1");
  const bgGradColor2 = document.getElementById("lb-bg-grad-color-2");
  const bgGradColor3 = document.getElementById("lb-bg-grad-color-3");
  const bgGradientAngleInput = document.getElementById("lb-bg-gradient-angle");
  const bgCustomCodeTextarea = document.getElementById("lb-bg-custom-code");

  // Card Background
  const cardBgTypeSelect = document.getElementById("lb-card-bg-type");
  const cardBgSolidBlock = document.getElementById("lb-card-bg-solid-block");
  const cardBgGradientBlock = document.getElementById("lb-card-bg-gradient-block");
  const cardBgColorInput = document.getElementById("lb-card-bg-color");
  const cardBgGradColor1 = document.getElementById("lb-card-bg-grad-color-1");
  const cardBgGradColor2 = document.getElementById("lb-card-bg-grad-color-2");
  const cardBgGradColor3 = document.getElementById("lb-card-bg-grad-color-3");
  const cardBgGradientAngleInput = document.getElementById("lb-card-bg-gradient-angle");

  // Socials + Buttons
  const socialsList = document.getElementById("lb-socials-list");
  const addSocialBtn = document.getElementById("lb-add-social");
  const buttonsList = document.getElementById("lb-buttons-list");
  const addButtonBtn = document.getElementById("lb-add-button");
  const saveAllBtn = document.getElementById("lb-save-all");

  /* ---------------------------------------------------------------------- */
  /*  Helpers
  /* ---------------------------------------------------------------------- */

  function showLogin() {
    if (loginView) loginView.hidden = false;
    if (settingsView) settingsView.hidden = true;
  }

  function showSettings() {
    if (loginView) loginView.hidden = true;
    if (settingsView) settingsView.hidden = false;
  }

  function setLoginStatus(msg, isError) {
    if (!loginStatus) return;
    loginStatus.textContent = msg || "";
    loginStatus.style.color = isError ? "#f87171" : "#9ca3af";
  }

  function setResetStatus(msg, isError) {
    if (!resetStatus) return;
    resetStatus.textContent = msg || "";
    resetStatus.style.color = isError ? "#f87171" : "#9ca3af";
  }

  function setSettingsStatus(msg, isError) {
    if (!settingsStatus) return;
    settingsStatus.textContent = msg || "";
    settingsStatus.style.color = isError ? "#f87171" : "#9ca3af";
  }

  function saveToken(token, email, remember) {
    authToken = token;
    if (remember) {
      localStorage.setItem("mm_token", token);
      if (email) {
        localStorage.setItem("mm_email", email);
      }
    } else {
      localStorage.removeItem("mm_token");
      localStorage.removeItem("mm_email");
    }
  }

  function clearTokenAndLogout(showAlert) {
    authToken = null;
    localStorage.removeItem("mm_token");
    localStorage.removeItem("mm_email");
    if (showAlert) {
      alert("Your session has expired or you are not authorized. Please log in again.");
    }
    // Redirect to admin page to show login form
    // Dynamic detection to avoid hardcoded paths
    const base = window.location.origin + window.location.pathname.split('/admin')[0];
    window.location = base + '/admin';
  }

  async function apiFetch(path, options = {}) {
    const url = API_BASE + path;
    const opts = { ...options };

    opts.headers = opts.headers || {};
    opts.headers["Accept"] = "application/json";

    // Only set JSON content-type if body is not FormData
    if (opts.body && !(opts.body instanceof FormData)) {
      opts.headers["Content-Type"] = "application/json";
    }

    if (authToken) {
      opts.headers["X-Minime-Token"] = authToken;
    }

    const res = await fetch(url, opts);

    if (res.status === 403) {
      let data;
      try {
        data = await res.json();
      } catch (e) {
        data = null;
      }
      console.error("[minime Admin] 403 Forbidden", data || res.statusText);
      clearTokenAndLogout(true);
      throw new Error("Forbidden");
    }

    if (!res.ok) {
      let text;
      try {
        text = await res.text();
      } catch (e) {
        text = res.statusText;
      }
      console.error("[minime Admin] Request failed", res.status, text);
      throw new Error("Request failed with status " + res.status);
    }

    const ct = res.headers.get("content-type") || "";
    if (ct.includes("application/json")) {
      return res.json();
    }
    return res.text();
  }

  async function uploadFile(file) {
    if (!file) return null;
    if (!authToken) {
      alert("You must be logged in to upload files.");
      return null;
    }

    const formData = new FormData();
    formData.append("file", file);

    const data = await apiFetch("/upload-image", {
      method: "POST",
      body: formData
    });

    if (!data || !data.ok) {
      throw new Error("Upload failed");
    }
    return data;
  }

  function updateBackgroundBlocks() {
    const type = bgTypeSelect ? bgTypeSelect.value : "image";

    if (bgImageBlock) bgImageBlock.hidden = type !== "image";
    if (bgColorBlock) bgColorBlock.hidden = type !== "color";
    if (bgGradientBlock) bgGradientBlock.hidden = type !== "gradient";
    if (bgCustomBlock) bgCustomBlock.hidden = type !== "custom";
  }

  function updateCardBackgroundBlocks() {
    const type = cardBgTypeSelect ? cardBgTypeSelect.value : "solid";

    if (cardBgSolidBlock) cardBgSolidBlock.hidden = type !== "solid";
    if (cardBgGradientBlock) cardBgGradientBlock.hidden = type !== "gradient";
  }

  function updateColorPreview() {
    if (!bgColorInput) return;
    
    const color = bgColorInput.value;
    bgColorInput.style.backgroundColor = color;
    bgColorInput.style.border = '1px solid rgba(255,255,255,0.2)';
    
    // Calculate luminance to determine text color for readability
    const hex = color.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Set text color based on luminance
    bgColorInput.style.color = luminance > 0.5 ? '#111111' : '#ffffff';
  }

  function updateGradientPreview(index) {
    const inputs = [bgGradColor1, bgGradColor2, bgGradColor3];
    const input = inputs[index];
    
    if (!input) return;
    
    const color = input.value;
    if (color) {
      input.style.backgroundColor = color;
      input.style.border = '1px solid rgba(255,255,255,0.2)';
      
      // Calculate luminance to determine text color for readability
      const hex = color.replace('#', '');
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      
      // Set text color based on luminance
      input.style.color = luminance > 0.5 ? '#111111' : '#ffffff';
    } else {
      // Reset to default if no color
      input.style.backgroundColor = '';
      input.style.border = '';
      input.style.color = '';
    }
  }

  function updateAllGradientPreviews() {
    updateGradientPreview(0);
    updateGradientPreview(1);
    updateGradientPreview(2);
  }

  function updateCardBgColorPreview() {
    if (!cardBgColorInput) return;
    
    const color = cardBgColorInput.value;
    cardBgColorInput.style.backgroundColor = color;
    cardBgColorInput.style.border = '1px solid rgba(255,255,255,0.2)';
    
    const hex = color.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    cardBgColorInput.style.color = luminance > 0.5 ? '#111111' : '#ffffff';
  }

  function updateCardBgGradientPreview(index) {
    const inputs = [cardBgGradColor1, cardBgGradColor2, cardBgGradColor3];
    const input = inputs[index];
    
    if (!input) return;
    
    const color = input.value;
    if (color) {
      input.style.backgroundColor = color;
      input.style.border = '1px solid rgba(255,255,255,0.2)';
      
      const hex = color.replace('#', '');
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      
      input.style.color = luminance > 0.5 ? '#111111' : '#ffffff';
    } else {
      input.style.backgroundColor = '';
      input.style.border = '';
      input.style.color = '';
    }
  }

  function updateAllCardBgGradientPreviews() {
    updateCardBgGradientPreview(0);
    updateCardBgGradientPreview(1);
    updateCardBgGradientPreview(2);
  }

  function createSocialRow(row = { type: "", value: "" }) {
    const div = document.createElement("div");
    div.className = "list-row lb-social-row";

    const select = document.createElement("select");
    select.className = "lb-social-type";
    select.innerHTML = `
      <option value="">Select type</option>
      <option value="instagram">Instagram</option>
      <option value="facebook">Facebook</option>
      <option value="linkedin">LinkedIn</option>
      <option value="x">X / Twitter</option>
      <option value="tiktok">TikTok</option>
      <option value="snapchat">Snapchat</option>
      <option value="telegram">Telegram</option>
      <option value="whatsapp">WhatsApp</option>
      <option value="email">Email</option>
      <option value="phone">Phone</option>
      <option value="youtube">YouTube</option>
      <option value="github">GitHub</option>
      <option value="dribbble">Dribbble</option>
      <option value="behance">Behance</option>
      <option value="other">Other</option>
    `;
    select.value = row.type || "";

    const input = document.createElement("input");
    input.type = "text";
    input.className = "lb-social-value";
    input.placeholder = "Username, URL, phone, email…";
    input.value = row.value || "";

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "btn btn-small btn-ghost";
    removeBtn.textContent = "Remove";
    removeBtn.addEventListener("click", () => {
      div.remove();
    });

    div.appendChild(select);
    div.appendChild(input);
    div.appendChild(removeBtn);
    return div;
  }

  function createButtonRow(row = { label: "", value: "" }) {
    const div = document.createElement("div");
    div.className = "list-row lb-button-row";

    const labelInput = document.createElement("input");
    labelInput.type = "text";
    labelInput.className = "lb-button-label";
    labelInput.placeholder = "Button label";
    labelInput.value = row.label || "";

    const valueInput = document.createElement("input");
    valueInput.type = "text";
    valueInput.className = "lb-button-value";
    valueInput.placeholder = "URL, phone, WhatsApp, email…";
    valueInput.value = row.value || "";

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "btn btn-small btn-ghost";
    removeBtn.textContent = "Remove";
    removeBtn.addEventListener("click", () => {
      div.remove();
    });

    div.appendChild(labelInput);
    div.appendChild(valueInput);
    div.appendChild(removeBtn);

    return div;
  }

  function renderSocials(socials) {
    if (!socialsList) return;
    socialsList.innerHTML = "";
    (socials || []).forEach((row) => {
      socialsList.appendChild(createSocialRow(row));
    });
  }

  function renderButtons(buttons) {
    if (!buttonsList) return;
    buttonsList.innerHTML = "";
    (buttons || []).forEach((row) => {
      buttonsList.appendChild(createButtonRow(row));
    });
  }

  /* ---------------------------------------------------------------------- */
  /*  LOAD INITIAL DATA
  /* ---------------------------------------------------------------------- */

  async function loadInitialData() {
    try {
      setSettingsStatus("Loading data…");
      const data = await apiFetch("/data", { method: "GET" });

      console.log("[minime Admin] Loaded data:", data);

      // Identity / bio
      siteTitleInput.value = data.site_title || "";
      siteTaglineInput.value = data.site_tagline || "";
      bioTextarea.value = data.bio || "";
      footerTextInput.value = data.branding_footer_text || "Powered by · Ayop · Headless WP · REST API";

      // Avatar / site icon
      const iconId = data.site_icon_id || 0;
      const iconUrl = data.site_icon_url || "";
      siteIconIdInput.value = iconId ? String(iconId) : "";
      if (iconUrl) {
        avatarPreview.src = iconUrl;
      } else {
        avatarPreview.src =
          "data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='80' height='80' rx='40' fill='%23111827'/%3E%3Cpath d='M40 20a12 12 0 1 1 0 24 12 12 0 0 1 0-24Zm0 28c-12.15 0-22 6.27-22 14v2h44v-2c0-7.73-9.85-14-22-14Z' fill='%239ca3af'/%3E%3C/svg%3E";
      }

      // Background
      const bg = data.background || {};
      bgTypeSelect.value = bg.type || "image";

      bgImageIdInput.value = bg.image_id ? String(bg.image_id) : "";
      bgColorInput.value = bg.color || "#000000";
      
      // Update color preview
      updateColorPreview();

      const gradient = bg.gradient || {};
      const colors = gradient.colors || [];
      bgGradColor1.value = colors[0] || "#111827";
      bgGradColor2.value = colors[1] || "#1f2937";
      bgGradColor3.value = colors[2] || "";
      bgGradientAngleInput.value =
        typeof gradient.angle === "number" ? gradient.angle : 180;
      
      // Update gradient previews
      updateAllGradientPreviews();

      // Decode custom_code from Base64
      try {
        const encoded = bg.custom_code || "";
        const decoded = encoded ? decodeURIComponent(escape(atob(encoded))) : "";
        bgCustomCodeTextarea.value = decoded;
      } catch (e) {
        console.warn("Failed to decode custom_code, using raw value", e);
        bgCustomCodeTextarea.value = bg.custom_code || "";
      }

      updateBackgroundBlocks();

      // Card Background
      const cardBg = data.card_background || {};
      if (cardBgTypeSelect) cardBgTypeSelect.value = cardBg.type || "solid";
      if (cardBgColorInput) {
        cardBgColorInput.value = cardBg.color || "#ffffff";
        updateCardBgColorPreview();
      }

      const cardGradient = cardBg.gradient || {};
      const cardColors = cardGradient.colors || [];
      if (cardBgGradColor1) cardBgGradColor1.value = cardColors[0] || "#ffffff";
      if (cardBgGradColor2) cardBgGradColor2.value = cardColors[1] || "#eeeeee";
      if (cardBgGradColor3) cardBgGradColor3.value = cardColors[2] || "";
      if (cardBgGradientAngleInput) {
        cardBgGradientAngleInput.value =
          typeof cardGradient.angle === "number" ? cardGradient.angle : 135;
      }
      
      updateAllCardBgGradientPreviews();
      updateCardBackgroundBlocks();

      // Socials + Buttons
      renderSocials(data.socials || []);
      renderButtons(data.buttons || []);

      // Homepage control
      const keepHomepageCheckbox = document.getElementById('mm-keep-homepage');
      if (keepHomepageCheckbox) {
        keepHomepageCheckbox.checked = !!data.keep_homepage;
      }

      // Store public_url in settings object
      settings.public_url = data.public_url || (window.location.origin + '/');

      // Setup "View my minime" button
      updateViewPublicButton();

      setSettingsStatus("Connected to WordPress.");
    } catch (err) {
      console.error(err);
      setSettingsStatus("Failed to load data. Check console for details.", true);
    }
  }

  /* ---------------------------------------------------------------------- */
  /*  SAVE ALL
  /* ---------------------------------------------------------------------- */

  async function handleSaveAll() {
    if (!authToken) {
      alert("You must be logged in to save.");
      return;
    }

    try {
      setSettingsStatus("Saving…");

      const site_title = siteTitleInput.value.trim();
      const site_tagline = siteTaglineInput.value.trim();
      const bio = bioTextarea.value;

      const site_icon_id = parseInt(siteIconIdInput.value, 10) || 0;

      const bgType = bgTypeSelect.value || "image";
      
      // Encode custom_code to Base64 to prevent JSON corruption
      const rawCustom = bgCustomCodeTextarea.value || "";
      const encodedCustom = btoa(unescape(encodeURIComponent(rawCustom)));
      
      const bg = {
        type: bgType,
        image_id: parseInt(bgImageIdInput.value, 10) || 0,
        color: bgColorInput.value || "#000000",
        gradient: {
          colors: [bgGradColor1.value, bgGradColor2.value, bgGradColor3.value].filter(
            (c) => !!c
          ),
          angle: parseInt(bgGradientAngleInput.value, 10) || 180,
        },
        custom_code: encodedCustom,
      };

      // Card Background
      const cardBgType = cardBgTypeSelect ? cardBgTypeSelect.value : "solid";
      const cardBg = {
        type: cardBgType,
        color: cardBgColorInput ? cardBgColorInput.value : "#ffffff",
        gradient: {
          colors: [
            cardBgGradColor1 ? cardBgGradColor1.value : "",
            cardBgGradColor2 ? cardBgGradColor2.value : "",
            cardBgGradColor3 ? cardBgGradColor3.value : ""
          ].filter((c) => !!c),
          angle: cardBgGradientAngleInput ? parseInt(cardBgGradientAngleInput.value, 10) || 135 : 135,
        },
      };

      // Socials
      const socials = [];
      if (socialsList) {
        socialsList.querySelectorAll(".lb-social-row").forEach((row) => {
          const typeEl = row.querySelector(".lb-social-type");
          const valueEl = row.querySelector(".lb-social-value");
          if (!typeEl || !valueEl) return;

          const type = (typeEl.value || "").trim();
          const value = (valueEl.value || "").trim();

          if (!type && !value) return;

          socials.push({ type, value });
        });
      }

      // Buttons
      const buttons = [];
      if (buttonsList) {
        buttonsList.querySelectorAll(".lb-button-row").forEach((row) => {
          const labelEl = row.querySelector(".lb-button-label");
          const valueEl = row.querySelector(".lb-button-value");
          if (!labelEl || !valueEl) return;

          const label = (labelEl.value || "").trim();
          const value = (valueEl.value || "").trim();
          if (!label && !value) return;

          buttons.push({ label, value });
        });
      }

      // Homepage control
      const keepHomepageCheckbox = document.getElementById('mm-keep-homepage');
      const keep_homepage = keepHomepageCheckbox ? keepHomepageCheckbox.checked : false;

      const branding_footer_text = footerTextInput.value.trim();

      const payload = {
        site_title,
        site_tagline,
        site_icon_id,
        bio,
        background: bg,
        card_background: cardBg,
        socials,
        buttons,
        keep_homepage,
        branding_footer_text,
      };

      const res = await apiFetch("/save", {
        method: "POST",
        body: JSON.stringify(payload),
      });

      console.log("[minime Admin] Save result:", res);
      setSettingsStatus("Saved successfully.");
      alert("All changes saved successfully.");
    } catch (err) {
      console.error(err);
      setSettingsStatus("Error: Save failed. Check console for details.", true);
      alert("Save failed. See console for details.");
    }
  }

  /* ---------------------------------------------------------------------- */
  /*  LOGIN
  /* ---------------------------------------------------------------------- */

  async function handleLoginSubmit(event) {
    event.preventDefault();
    if (!loginEmail || !loginPassword) return;

    const username = loginEmail.value.trim();
    const password = loginPassword.value;
    const remember = rememberCheckbox ? rememberCheckbox.checked : false;

    if (!username || !password) {
      setLoginStatus("Please enter your username/email and password.", true);
      return;
    }

    setLoginStatus("Signing in…");

    try {
      const loginUrl = API_BASE + "/login";
      console.log('[minime Admin] Attempting login to:', loginUrl);
      const res = await fetch(loginUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          username,
          password,
          remember,
        }),
      });

      if (!res.ok) {
        let data;
        try {
          data = await res.json();
        } catch (e) {
          data = null;
        }
        console.error("[minime Admin] Login failed", res.status, data);
        setLoginStatus("Login failed. Check your credentials.", true);
        return;
      }

      const data = await res.json();
      if (!data || !data.ok || !data.token) {
        console.error("[minime Admin] Login error", data);
        setLoginStatus("Login failed. Unexpected response.", true);
        return;
      }

      saveToken(data.token, username, remember);
      setLoginStatus("");
      
      // Redirect to admin page after successful login
      // Dynamic detection to avoid hardcoded paths
      const base = window.location.origin + window.location.pathname.split('/admin')[0];
      window.location = base + '/admin';
    } catch (err) {
      console.error(err);
      setLoginStatus("Login request failed. Check console.", true);
    }
  }

  function handleLogout() {
    clearTokenAndLogout(false);
  }

  /* ---------------------------------------------------------------------- */
  /*  PASSWORD RESET
  /* ---------------------------------------------------------------------- */

  function showResetForm() {
    if (loginForm) loginForm.style.display = 'none';
    if (resetForm) resetForm.style.display = 'flex';
    setLoginStatus('');
    setResetStatus('');
  }

  function showLoginForm() {
    if (resetForm) resetForm.style.display = 'none';
    if (loginForm) loginForm.style.display = 'flex';
    setLoginStatus('');
    setResetStatus('');
  }

  function showPasswordResetForm() {
    // Hide normal login and "forgot password" forms
    if (loginForm) loginForm.style.display = 'none';
    if (resetForm) resetForm.style.display = 'none';
    
    // Show login view container
    if (loginView) loginView.hidden = false;
    if (settingsView) settingsView.hidden = true;
    
    // Create password reset form if it doesn't exist
    let passwordResetForm = document.getElementById('lb-password-reset-form');
    
    if (!passwordResetForm) {
      passwordResetForm = document.createElement('form');
      passwordResetForm.id = 'lb-password-reset-form';
      passwordResetForm.className = 'stack';
      passwordResetForm.innerHTML = `
        <div class="field">
          <label for="lb-new-password">New Password</label>
          <input id="lb-new-password" type="password" placeholder="Enter your new password" required />
        </div>
        <div class="field">
          <label for="lb-confirm-password">Confirm Password</label>
          <input id="lb-confirm-password" type="password" placeholder="Re-enter your new password" required />
        </div>
        <button type="submit" class="btn btn-primary">Set New Password</button>
        <div class="status" id="lb-password-reset-status"></div>
      `;
      
      // Insert into login view, after reset form
      if (loginView) {
        if (resetForm && resetForm.parentNode === loginView) {
          loginView.insertBefore(passwordResetForm, resetForm.nextSibling);
        } else if (loginForm && loginForm.parentNode === loginView) {
          loginView.insertBefore(passwordResetForm, loginForm.nextSibling);
        } else {
          loginView.appendChild(passwordResetForm);
        }
      }
      
      // Add submit handler
      passwordResetForm.addEventListener('submit', handlePasswordResetSubmit);
    }
    
    passwordResetForm.style.display = 'flex';
    setPasswordResetStatus('');
  }

  function setPasswordResetStatus(msg, isError) {
    const status = document.getElementById('lb-password-reset-status');
    if (!status) return;
    status.textContent = msg || "";
    status.style.color = isError ? "#f87171" : "#9ca3af";
  }

  function checkResetPasswordParams() {
    const params = new URLSearchParams(window.location.search);
    const minimeReset = params.get('minime_reset');
    const key = params.get('key');
    const login = params.get('login');
    
    if (minimeReset === '1' && key && login) {
      resetPasswordParams = { key, login };
      return true;
    }
    
    return false;
  }

  async function handleResetSubmit(event) {
    event.preventDefault();
    if (!resetEmail) return;

    const email = resetEmail.value.trim();

    if (!email) {
      setResetStatus("Please enter your email address.", true);
      return;
    }

    setResetStatus("Sending reset link…");

    try {
      const resetUrl = API_BASE + "/request-password-reset";
      const res = await fetch(resetUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ email }),
      });

      const data = await res.json();

      if (!res.ok) {
        setResetStatus(data.message || "Failed to send reset link.", true);
        return;
      }

      if (data && data.ok) {
        setResetStatus(data.message || "If this email is registered, you will receive a password reset link shortly.", false);
        resetEmail.value = '';
      } else {
        setResetStatus("Failed to send reset link.", true);
      }
    } catch (err) {
      console.error(err);
      setResetStatus("Request failed. Check console.", true);
    }
  }

  async function handlePasswordResetSubmit(event) {
    event.preventDefault();
    
    if (!resetPasswordParams) {
      setPasswordResetStatus("Missing reset parameters.", true);
      return;
    }
    
    const newPasswordInput = document.getElementById('lb-new-password');
    const confirmPasswordInput = document.getElementById('lb-confirm-password');
    
    if (!newPasswordInput || !confirmPasswordInput) {
      setPasswordResetStatus("Form error. Please refresh the page.", true);
      return;
    }
    
    const newPassword = newPasswordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    
    // Validation
    if (!newPassword) {
      setPasswordResetStatus("Please enter a new password.", true);
      return;
    }
    
    if (newPassword !== confirmPassword) {
      setPasswordResetStatus("Passwords do not match.", true);
      return;
    }
    
    if (newPassword.length < 8) {
      setPasswordResetStatus("Password must be at least 8 characters long.", true);
      return;
    }
    
    setPasswordResetStatus("Resetting password…");
    
    try {
      const res = await fetch(API_BASE + "/reset-password", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          key: resetPasswordParams.key,
          login: resetPasswordParams.login,
          password: newPassword,
        }),
      });
      
      const data = await res.json();
      
      if (!res.ok) {
        const errorMsg = data.message || "This reset link is invalid or has expired.";
        setPasswordResetStatus(errorMsg, true);
        return;
      }
      
      if (data && data.ok) {
        setPasswordResetStatus("Your password has been reset successfully. You can now log in.", false);
        
        // Clear form
        newPasswordInput.value = '';
        confirmPasswordInput.value = '';
        
        // Redirect to login page after 3 seconds
        setTimeout(() => {
          // Remove query parameters and reload
          const base = window.location.origin + window.location.pathname;
          window.location.href = base;
        }, 3000);
      } else {
        setPasswordResetStatus("Password reset failed. Please try again.", true);
      }
    } catch (err) {
      console.error(err);
      setPasswordResetStatus("Request failed. Check console.", true);
    }
  }

  function updateViewPublicButton() {
    const viewPublicBtn = document.getElementById('mm-view-public');
    if (viewPublicBtn) {
      if (settings.public_url) {
        viewPublicBtn.disabled = false;
        viewPublicBtn.onclick = function() {
          window.open(settings.public_url, '_blank');
        };
      } else {
        viewPublicBtn.disabled = true;
        viewPublicBtn.onclick = null;
      }
    }
  }

  /* ---------------------------------------------------------------------- */
  /*  RESTORE SESSION ON LOAD
  /* ---------------------------------------------------------------------- */

  function tryRestoreSession() {
    // Check if this is a password reset link
    if (checkResetPasswordParams()) {
      showLogin();
      showPasswordResetForm();
      return;
    }
    
    const storedToken = localStorage.getItem("mm_token");
    const storedEmail = localStorage.getItem("mm_email");

    if (storedToken) {
      authToken = storedToken;
      if (loginEmail && storedEmail) {
        loginEmail.value = storedEmail;
      }
      showSettings();
      loadInitialData().catch((err) => {
        console.error(err);
        clearTokenAndLogout(false);
      });
    } else {
      showLogin();
    }
  }

  /* ---------------------------------------------------------------------- */
  /*  EVENT BINDINGS
  /* ---------------------------------------------------------------------- */

  document.addEventListener("DOMContentLoaded", () => {
    if (loginForm) {
      loginForm.addEventListener("submit", handleLoginSubmit);
    }

    if (resetForm) {
      resetForm.addEventListener("submit", handleResetSubmit);
    }

    if (forgotPasswordLink) {
      forgotPasswordLink.addEventListener("click", (e) => {
        e.preventDefault();
        showResetForm();
      });
    }

    if (backToLoginBtn) {
      backToLoginBtn.addEventListener("click", () => {
        showLoginForm();
      });
    }

    if (logoutBtn) {
      logoutBtn.addEventListener("click", handleLogout);
    }

    if (bgTypeSelect) {
      bgTypeSelect.addEventListener("change", updateBackgroundBlocks);
    }

    if (cardBgTypeSelect) {
      cardBgTypeSelect.addEventListener("change", updateCardBackgroundBlocks);
    }

    if (cardBgColorInput) {
      cardBgColorInput.addEventListener("input", updateCardBgColorPreview);
      cardBgColorInput.addEventListener("change", updateCardBgColorPreview);
    }

    if (cardBgGradColor1) {
      cardBgGradColor1.addEventListener("input", () => updateCardBgGradientPreview(0));
      cardBgGradColor1.addEventListener("change", () => updateCardBgGradientPreview(0));
    }

    if (cardBgGradColor2) {
      cardBgGradColor2.addEventListener("input", () => updateCardBgGradientPreview(1));
      cardBgGradColor2.addEventListener("change", () => updateCardBgGradientPreview(1));
    }

    if (cardBgGradColor3) {
      cardBgGradColor3.addEventListener("input", () => updateCardBgGradientPreview(2));
      cardBgGradColor3.addEventListener("change", () => updateCardBgGradientPreview(2));
    }

    if (bgColorInput) {
      bgColorInput.addEventListener("input", updateColorPreview);
      bgColorInput.addEventListener("change", updateColorPreview);
    }

    if (bgGradColor1) {
      bgGradColor1.addEventListener("input", () => updateGradientPreview(0));
      bgGradColor1.addEventListener("change", () => updateGradientPreview(0));
    }

    if (bgGradColor2) {
      bgGradColor2.addEventListener("input", () => updateGradientPreview(1));
      bgGradColor2.addEventListener("change", () => updateGradientPreview(1));
    }

    if (bgGradColor3) {
      bgGradColor3.addEventListener("input", () => updateGradientPreview(2));
      bgGradColor3.addEventListener("change", () => updateGradientPreview(2));
    }

    if (addSocialBtn) {
      addSocialBtn.addEventListener("click", () => {
        socialsList.appendChild(createSocialRow());
      });
    }

    if (addButtonBtn) {
      addButtonBtn.addEventListener("click", () => {
        buttonsList.appendChild(createButtonRow());
      });
    }

    if (saveAllBtn) {
      saveAllBtn.addEventListener("click", handleSaveAll);
    }

    if (avatarFileInput) {
      avatarFileInput.addEventListener("change", async () => {
        const file = avatarFileInput.files && avatarFileInput.files[0];
        if (!file) return;
        try {
          setSettingsStatus("Uploading avatar…");
          const res = await uploadFile(file);
          if (res && res.id && res.url) {
            siteIconIdInput.value = String(res.id);
            avatarPreview.src = res.url;
            setSettingsStatus("Avatar uploaded.");
          } else {
            setSettingsStatus("Avatar upload failed.", true);
          }
        } catch (err) {
          console.error(err);
          setSettingsStatus("Avatar upload failed.", true);
        }
      });
    }

    if (bgImageUploadBtn) {
      bgImageUploadBtn.addEventListener("click", async () => {
        const file = bgImageFileInput.files && bgImageFileInput.files[0];
        if (!file) {
          alert("Select an image first.");
          return;
        }
        try {
          setSettingsStatus("Uploading background image…");
          const res = await uploadFile(file);
          if (res && res.id) {
            bgImageIdInput.value = String(res.id);
            setSettingsStatus("Background image uploaded.");
          } else {
            setSettingsStatus("Background image upload failed.", true);
          }
        } catch (err) {
          console.error(err);
          setSettingsStatus("Background image upload failed.", true);
        }
      });
    }

    // Setup "View my minime" button event listener
    const viewPublicBtn = document.getElementById('mm-view-public');
    if (viewPublicBtn) {
      viewPublicBtn.addEventListener('click', function() {
        if (settings.public_url) {
          window.open(settings.public_url, '_blank');
        }
      });
    }

    // Initial
    tryRestoreSession();
  });
})();