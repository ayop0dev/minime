'use client';

import { useState, useEffect } from 'react';
import styles from './panel.module.css';
import settingsStyles from './settings-panel.module.css';
import { isHexColorDark } from '@/lib/color';

// UTF-8 safe Base64 helpers (handles Unicode/Arabic characters)
const b64Encode = (str: string): string => btoa(unescape(encodeURIComponent(str)));
const b64Decode = (b64: string): string => decodeURIComponent(escape(atob(b64)));

interface SocialLink {
  id: string;
  channel: 'youtube' | 'instagram' | 'facebook' | 'twitter' | 'tiktok' | 'linkedin' | 'github' | 'email' | 'phone' | 'whatsapp' | 'telegram' | 'snapchat' | 'website';
  value: string;
}

interface Button {
  id: string;
  label: string;
  url: string;
}

interface PageBackgroundState {
  type: 'solid' | 'gradient' | 'image' | 'sandbox';
  solidColor?: string;
  gradient?: {
    color1: string;
    color2: string;
    angle: number;
  };
  image?: {
    file?: File;
    previewUrl?: string;
    imageId?: number;
  };
  sandbox?: {
    code: string;
  };
}

export default function SettingsPanel() {
  // Loading and error states
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Profile/Identity
  const [siteTitle, setSiteTitle] = useState('');
  const [tagline, setTagline] = useState('');
  const [bio, setBio] = useState('');
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [siteIconId, setSiteIconId] = useState<number>(0);

  // Social Links
  const [socialLinks, setSocialLinks] = useState<SocialLink[]>([]);

  // Buttons
  const [buttons, setButtons] = useState<Button[]>([]);

  // Visuals
  const [cardBackground, setCardBackground] = useState('#ffffff');
  const [pageBackground, setPageBackground] = useState<PageBackgroundState>({
    type: 'solid',
    solidColor: '#f5f5f5',
    gradient: {
      color1: '#ffffff',
      color2: '#f5f5f5',
      angle: 135,
    },
    image: {
      previewUrl: undefined,
    },
    sandbox: {
      code: '',
    },
  });

  // Footer
  const [showFooterBranding, setShowFooterBranding] = useState(true);
  const [footerText, setFooterText] = useState('');

  // Fetch admin data on mount
  useEffect(() => {
    fetchAdminData();
  }, []);

  const fetchAdminData = async () => {
    try {
      setIsLoading(true);
      setError(null);

      // Get nonce and REST root from window config
      const config = (window as any).MINIME_ADMIN_CONFIG;
      
      if (!config?.nonce) {
        throw new Error('Admin config not found. Nonce missing.');
      }

      // Use the REST root from config instead of hardcoded path
      const restRoot = config.restRoot || '/wp-json/';

      // Fetch admin data
      const fetchUrl = restRoot + 'minime/v1/admin';

      const response = await fetch(fetchUrl, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'include',  // Important: include cookies for authentication
      });

      if (!response.ok) {
        const contentType = response.headers.get('content-type');
        let errorData: any = {};
        if (contentType?.includes('application/json')) {
          errorData = await response.json();
        } else {
          const text = await response.text();
          errorData = { message: text.substring(0, 100) };
        }
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data = await response.json();

      // Populate form from API response (API uses snake_case)
      setSiteTitle(data.site_title || '');
      setTagline(data.site_tagline || '');
      setBio(data.bio || '');
      if (data.site_icon_url) {
        setAvatarPreview(data.site_icon_url);
      }
      if (data.site_icon_id) {
        setSiteIconId(data.site_icon_id);
      }
      // Map socials from API format to form format
      if (data.socials && Array.isArray(data.socials)) {
        const mappedSocials = data.socials.map((s: any, idx: number) => ({
          id: String(idx + 1),
          channel: s.type || 'youtube',
          value: s.value || s.url || '',
        }));
        setSocialLinks(mappedSocials);
      }
      // Map buttons from API format to form format
      if (data.buttons && Array.isArray(data.buttons)) {
        const mappedButtons = data.buttons.map((b: any, idx: number) => ({
          id: String(idx + 1),
          label: b.label || '',
          url: b.value || b.url || '',
        }));
        setButtons(mappedButtons);
      }
      // Card background
      if (data.card_background?.color) {
        setCardBackground(data.card_background.color);
      }
      // Page background
      if (data.background) {
        // Normalize gradient: API returns { colors: [], angle } but UI expects { color1, color2, angle }
        const apiGradient = data.background.gradient;
        const normalizedGradient = apiGradient ? {
          color1: apiGradient.colors?.[0] || '#ffffff',
          color2: apiGradient.colors?.[1] || '#f5f5f5',
          angle: apiGradient.angle || 135,
        } : { color1: '#ffffff', color2: '#f5f5f5', angle: 135 };

        // Decode sandbox code from Base64 (UTF-8 safe)
        let sandboxCode = '';
        if (data.background.sandbox?.code) {
          try {
            sandboxCode = b64Decode(data.background.sandbox.code);
          } catch (e) {
            console.warn('Failed to decode sandbox code:', e);
          }
        }

        setPageBackground({
          type: data.background.type || 'solid',
          solidColor: data.background.color || '#f5f5f5',
          gradient: normalizedGradient,
          image: {
            previewUrl: data.background.image_url || undefined,
            imageId: data.background.image_id || 0,
          },
          sandbox: { code: sandboxCode },
        });
      }
      // Backward-compatible: prefer branding_footer_text, fall back to footer_text
      setFooterText(data.branding_footer_text || data.footer_text || '');
      setShowFooterBranding(data.show_footer_branding !== false);
    } catch (err) {
      console.error('Failed to load settings:', err);
      setError(err instanceof Error ? err.message : 'Failed to load settings');
    } finally {
      setIsLoading(false);
    }
  };

  // Avatar Upload Handler
  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Show preview immediately
    const reader = new FileReader();
    reader.onloadend = () => {
      setAvatarPreview(reader.result as string);
    };
    reader.readAsDataURL(file);

    // Upload to server
    try {
      const config = (window as any).MINIME_ADMIN_CONFIG;
      if (!config?.nonce) {
        throw new Error('Admin config not found.');
      }

      const formData = new FormData();
      formData.append('file', file);

      const restRoot = config.restRoot || '/wp-json/';
      const response = await fetch(restRoot + 'minime/v1/upload-image', {
        method: 'POST',
        headers: {
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'include',
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data = await response.json();
      setSiteIconId(data.id);
      setAvatarPreview(data.url);
    } catch (err) {
      console.error('Failed to upload avatar:', err);
      setError(err instanceof Error ? err.message : 'Failed to upload avatar');
    }
  };

  // Page Background Handlers
  const handlePageBackgroundTypeChange = (newType: 'solid' | 'gradient' | 'image' | 'sandbox') => {
    setPageBackground({ ...pageBackground, type: newType });
  };

  const handleSandboxCodeChange = (code: string) => {
    // Limit to 100KB
    if (code.length > 102400) {
      setError('Sandbox code exceeds 100KB limit');
      return;
    }
    setPageBackground({
      ...pageBackground,
      sandbox: { code },
    });
  };

  const handlePageBackgroundSolidChange = (color: string) => {
    setPageBackground({
      ...pageBackground,
      type: 'solid',
      solidColor: color,
    });
  };

  const handlePageBackgroundGradientChange = (
    field: 'color1' | 'color2' | 'angle',
    value: string | number
  ) => {
    setPageBackground({
      ...pageBackground,
      gradient: {
        ...pageBackground.gradient!,
        [field]: value,
      },
    });
  };

  const handlePageBackgroundImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Show preview immediately
    const reader = new FileReader();
    reader.onloadend = () => {
      setPageBackground((prev) => ({
        ...prev,
        type: 'image',
        image: {
          ...prev.image,
          previewUrl: reader.result as string,
        },
      }));
    };
    reader.readAsDataURL(file);

    // Upload to server
    try {
      const config = (window as any).MINIME_ADMIN_CONFIG;
      if (!config?.nonce) {
        throw new Error('Admin config not found.');
      }

      const formData = new FormData();
      formData.append('file', file);

      const restRoot = config.restRoot || '/wp-json/';
      const response = await fetch(restRoot + 'minime/v1/upload-image', {
        method: 'POST',
        headers: {
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'include',
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data = await response.json();
      setPageBackground((prev) => ({
        ...prev,
        type: 'image',
        image: {
          previewUrl: data.url,
          imageId: data.id,
        },
      }));
    } catch (err) {
      console.error('Failed to upload background image:', err);
      setError(err instanceof Error ? err.message : 'Failed to upload background image');
    }
  };

  const handlePageBackgroundImageRemove = () => {
    setPageBackground({
      ...pageBackground,
      image: {
        previewUrl: undefined,
      },
    });
  };

  // Social Links Handlers
  const addSocialLink = () => {
    setSocialLinks([
      ...socialLinks,
      { id: Date.now().toString(), channel: 'youtube', value: '' },
    ]);
  };

  const updateSocialLink = (
    id: string,
    field: 'channel' | 'value',
    value: string
  ) => {
    setSocialLinks(
      socialLinks.map((link) =>
        link.id === id ? { ...link, [field]: value } : link
      )
    );
  };

  const removeSocialLink = (id: string) => {
    setSocialLinks(socialLinks.filter((link) => link.id !== id));
  };

  // Buttons Handlers
  const addButton = () => {
    setButtons([...buttons, { id: Date.now().toString(), label: '', url: '' }]);
  };

  const updateButton = (id: string, field: 'label' | 'url', value: string) => {
    setButtons(
      buttons.map((btn) =>
        btn.id === id ? { ...btn, [field]: value } : btn
      )
    );
  };

  const removeButton = (id: string) => {
    setButtons(buttons.filter((btn) => btn.id !== id));
  };

  // Save Handler
  const handleSave = async () => {
    try {
      setIsSaving(true);
      setError(null);
      setSuccessMessage(null);

      // Get nonce from window config
      const config = (window as any).MINIME_ADMIN_CONFIG;
      
      if (!config?.nonce) {
        throw new Error('Admin config not found. Nonce missing.');
      }

      // Convert form data to API format (snake_case)
      const payload = {
        site_title: siteTitle,
        site_tagline: tagline,
        bio: bio,
        site_icon_id: siteIconId || undefined,
        // Map socialLinks to API format
        socials: socialLinks.map((s) => ({
          type: s.channel,
          value: s.value,
        })),
        // Map buttons to API format
        buttons: buttons.map((b) => ({
          label: b.label,
          value: b.url,
        })),
        // Card background
        card_background: {
          color: cardBackground,
        },
        // Page background
        background: {
          type: pageBackground.type,
          color: pageBackground.solidColor,
          gradient: pageBackground.gradient ? {
            colors: [pageBackground.gradient.color1, pageBackground.gradient.color2],
            angle: pageBackground.gradient.angle || 135,
          } : undefined,
          image_id: pageBackground.image?.imageId || 0,
          sandbox: pageBackground.sandbox?.code ? {
            code: b64Encode(pageBackground.sandbox.code),
          } : undefined,
        },
        branding_footer_text: footerText,
      };

      // Get REST root and nonce from config
      const restRoot = config?.restRoot || '/wp-json/';
      const saveUrl = restRoot + 'minime/v1/save';

      const response = await fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        credentials: 'include',  // Important: include cookies for authentication
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      await response.json();

      setSuccessMessage('Settings saved successfully!');
      setTimeout(() => setSuccessMessage(null), 5000);
    } catch (err) {
      console.error('Failed to save settings:', err);
      setError(err instanceof Error ? err.message : 'Failed to save settings');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className={styles.panel}>
      {/* Loading State */}
      {isLoading && (
        <div style={{ padding: '40px', textAlign: 'center', color: '#999' }}>
          Loading settings...
        </div>
      )}

      {/* Error Message */}
      {error && (
        <div
          style={{
            padding: '12px 16px',
            marginBottom: '16px',
            backgroundColor: '#fee',
            border: '1px solid #fcc',
            borderRadius: '4px',
            color: '#c33',
            fontSize: '14px',
          }}
        >
          ❌ {error}
        </div>
      )}

      {/* Success Message */}
      {successMessage && (
        <div
          style={{
            padding: '12px 16px',
            marginBottom: '16px',
            backgroundColor: '#efe',
            border: '1px solid #cfc',
            borderRadius: '4px',
            color: '#3c3',
            fontSize: '14px',
          }}
        >
          ✅ {successMessage}
        </div>
      )}

      {/* Content (hidden while loading) */}
      {!isLoading && (
        <>
          <header className={styles.header}>
            <h1 className={styles.title}>settings</h1>
            <p className={styles.description}>manage your minime card and profile</p>
          </header>

      <div className={settingsStyles.settingsContainer}>
        {/* Card 1: Profile */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>profile</h3>
            <span className={styles.badge}>your identity</span>
          </div>

          <div className={settingsStyles.twoColumn}>
            {/* Left Column */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>site title</label>
                <input
                  type="text"
                  className={settingsStyles.input}
                  placeholder="site title"
                  value={siteTitle}
                  onChange={(e) => setSiteTitle(e.target.value)}
                />
              </div>

              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>tagline</label>
                <input
                  type="text"
                  className={settingsStyles.input}
                  placeholder="short tagline"
                  value={tagline}
                  onChange={(e) => setTagline(e.target.value)}
                />
              </div>

              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>bio</label>
                <textarea
                  className={settingsStyles.textarea}
                  placeholder="write a short bio for the card"
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  rows={4}
                />
              </div>
            </div>

            {/* Right Column - Avatar */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>avatar</label>
                <div className={settingsStyles.avatarPreview}>
                  {avatarPreview ? (
                    <img
                      src={avatarPreview}
                      alt="avatar preview"
                      className={settingsStyles.avatarImage}
                    />
                  ) : (
                    <div className={settingsStyles.avatarPlaceholder}>
                      <span className={settingsStyles.avatarIcon}>📷</span>
                    </div>
                  )}
                </div>

                <div className={settingsStyles.fileInputWrapper}>
                  <input
                    type="file"
                    accept="image/*"
                    className={settingsStyles.fileInput}
                    onChange={handleAvatarUpload}
                    id="avatar-upload"
                  />
                  <label htmlFor="avatar-upload" className={settingsStyles.fileLabel}>
                    choose image
                  </label>
                </div>

                <p className={settingsStyles.helperText}>
                  this image will be used as both the card avatar and the site
                  favicon.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Card 2: Links & Buttons */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>links & buttons</h3>
            <span className={styles.badge}>call to action</span>
          </div>

          <div className={settingsStyles.twoColumn}>
            {/* Left Column - Social Links */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.sectionTitle}>social links</div>

              <div className={settingsStyles.repeatableSection}>
                {socialLinks.length === 0 ? (
                  <p className={settingsStyles.emptyState}>no social links yet</p>
                ) : (
                  socialLinks.map((link) => (
                    <div key={link.id} className={settingsStyles.repeatableRow}>
                      <select
                        className={settingsStyles.select}
                        value={link.channel}
                        onChange={(e) =>
                          updateSocialLink(link.id, 'channel', e.target.value)
                        }
                      >
                        <option value="youtube">youtube</option>
                        <option value="instagram">instagram</option>
                        <option value="facebook">facebook</option>
                        <option value="twitter">twitter / x</option>
                        <option value="tiktok">tiktok</option>
                        <option value="linkedin">linkedin</option>
                        <option value="github">github</option>
                        <option value="email">email</option>
                        <option value="phone">phone</option>
                        <option value="whatsapp">whatsapp</option>
                        <option value="telegram">telegram</option>
                        <option value="snapchat">snapchat</option>
                        <option value="website">website</option>
                      </select>

                      <input
                        type="text"
                        className={settingsStyles.input}
                        placeholder="value"
                        value={link.value}
                        onChange={(e) =>
                          updateSocialLink(link.id, 'value', e.target.value)
                        }
                      />

                      <button
                        className={settingsStyles.removeButton}
                        onClick={() => removeSocialLink(link.id)}
                        aria-label="remove social link"
                      >
                        ✕
                      </button>
                    </div>
                  ))
                )}
              </div>

              <button
                className={settingsStyles.addButton}
                onClick={addSocialLink}
              >
                + add social link
              </button>
            </div>

            {/* Right Column - Buttons */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.sectionTitle}>buttons</div>

              <div className={settingsStyles.repeatableSection}>
                {buttons.length === 0 ? (
                  <p className={settingsStyles.emptyState}>no buttons yet</p>
                ) : (
                  buttons.map((button) => (
                    <div key={button.id} className={settingsStyles.repeatableRow}>
                      <input
                        type="text"
                        className={settingsStyles.input}
                        placeholder="button label"
                        value={button.label}
                        onChange={(e) =>
                          updateButton(button.id, 'label', e.target.value)
                        }
                      />

                      <input
                        type="url"
                        className={settingsStyles.input}
                        placeholder="url"
                        value={button.url}
                        onChange={(e) =>
                          updateButton(button.id, 'url', e.target.value)
                        }
                      />

                      <button
                        className={settingsStyles.removeButton}
                        onClick={() => removeButton(button.id)}
                        aria-label="remove button"
                      >
                        ✕
                      </button>
                    </div>
                  ))
                )}
              </div>

              <button className={settingsStyles.addButton} onClick={addButton}>
                + add button
              </button>
            </div>
          </div>
        </div>

        {/* Card 3: Visuals */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>visuals</h3>
            <span className={styles.badge}>colors & appearance</span>
          </div>

          <div className={settingsStyles.twoColumn}>
            {/* Left Column - Card Background */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>
                  card background
                </label>
                <div className={settingsStyles.colorPickerWrapper}>
                  <input
                    type="color"
                    className={settingsStyles.colorPicker}
                    value={cardBackground}
                    onChange={(e) => setCardBackground(e.target.value)}
                  />
                  <span className={settingsStyles.colorValue}>
                    {cardBackground}
                  </span>
                </div>
              </div>
            </div>

            {/* Right Column - Page Background */}
            <div className={settingsStyles.column}>
              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>
                  page background
                </label>

                {/* Segmented Control */}
                <div className={settingsStyles.segmentedControl}>
                  {(['solid', 'gradient', 'image', 'sandbox'] as const).map((type) => (
                    <button
                      key={type}
                      className={`${settingsStyles.segmentButton} ${
                        pageBackground.type === type ? settingsStyles.active : ''
                      }`}
                      onClick={() => handlePageBackgroundTypeChange(type)}
                    >
                      {type}
                    </button>
                  ))}
                </div>

                {/* Solid Mode */}
                {pageBackground.type === 'solid' && (
                  <div className={settingsStyles.formGroup}>
                    <div className={settingsStyles.colorPickerWrapper}>
                      <input
                        type="color"
                        value={pageBackground.solidColor || '#f5f5f5'}
                        onChange={(e) =>
                          handlePageBackgroundSolidChange(e.target.value)
                        }
                      />
                      <span className={settingsStyles.colorValue}>
                        {pageBackground.solidColor || '#f5f5f5'}
                      </span>
                    </div>
                  </div>
                )}

                {/* Gradient Mode */}
                {pageBackground.type === 'gradient' && (
                  <div className={settingsStyles.gradientModeContainer}>
                    <div className={settingsStyles.gradientRow}>
                      <div className={settingsStyles.gradientColorGroup}>
                        <label className={settingsStyles.inputLabel}>color 1</label>
                        <input
                          type="color"
                          value={pageBackground.gradient?.color1 || '#ffffff'}
                          onChange={(e) =>
                            handlePageBackgroundGradientChange(
                              'color1',
                              e.target.value
                            )
                          }
                        />
                      </div>
                      <div className={settingsStyles.gradientColorGroup}>
                        <label className={settingsStyles.inputLabel}>color 2</label>
                        <input
                          type="color"
                          value={pageBackground.gradient?.color2 || '#f5f5f5'}
                          onChange={(e) =>
                            handlePageBackgroundGradientChange(
                              'color2',
                              e.target.value
                            )
                          }
                        />
                      </div>
                    </div>

                    <div className={settingsStyles.angleSliderGroup}>
                      <label className={settingsStyles.inputLabel}>
                        angle: {pageBackground.gradient?.angle || 135}°
                      </label>
                      <input
                        type="range"
                        min="0"
                        max="360"
                        value={pageBackground.gradient?.angle || 135}
                        onChange={(e) =>
                          handlePageBackgroundGradientChange(
                            'angle',
                            parseInt(e.target.value)
                          )
                        }
                      />
                    </div>

                    <div
                      className={settingsStyles.gradientPreviewSwatch}
                      style={{
                        background: `linear-gradient(${
                          pageBackground.gradient?.angle || 135
                        }deg, ${pageBackground.gradient?.color1 || '#ffffff'}, ${
                          pageBackground.gradient?.color2 || '#f5f5f5'
                        })`,
                      }}
                    />
                  </div>
                )}

                {/* Image Mode */}
                {pageBackground.type === 'image' && (
                  <div className={settingsStyles.imageModeContainer}>
                    {!pageBackground.image?.previewUrl ? (
                      <div className={settingsStyles.imageUploadWrapper}>
                        <label
                          htmlFor="pageImageInput"
                          className={settingsStyles.fileLabel}
                        >
                          choose image
                        </label>
                        <input
                          id="pageImageInput"
                          type="file"
                          accept="image/*"
                          className={settingsStyles.fileInput}
                          onChange={handlePageBackgroundImageUpload}
                        />
                        <p className={settingsStyles.uploadHint}>
                          PNG, JPG, GIF (max 5MB)
                        </p>
                      </div>
                    ) : (
                      <div className={settingsStyles.imagePreviewContainer}>
                        <img
                          src={pageBackground.image.previewUrl}
                          alt="preview"
                          className={settingsStyles.imagePreview}
                        />
                        <button
                          className={settingsStyles.clearImageButton}
                          onClick={handlePageBackgroundImageRemove}
                        >
                          remove image
                        </button>
                      </div>
                    )}
                  </div>
                )}

                {/* Sandbox Mode */}
                {pageBackground.type === 'sandbox' && (
                  <div className={settingsStyles.formGroup}>
                    <p className={settingsStyles.helperText}>
                      Paste CSS or HTML/CSS/JS code for a custom background. Raw CSS is auto-wrapped.
                      External scripts and stylesheets are stripped for security. Max 100KB.
                    </p>
                    <textarea
                      className={settingsStyles.textarea}
                      placeholder="body { background: linear-gradient(45deg, #ff6b6b, #4ecdc4); }"
                      value={pageBackground.sandbox?.code || ''}
                      onChange={(e) => handleSandboxCodeChange(e.target.value)}
                      rows={10}
                      style={{ fontFamily: 'monospace', fontSize: '12px' }}
                    />
                    <p className={settingsStyles.helperText} style={{ marginTop: '8px' }}>
                      {((pageBackground.sandbox?.code?.length || 0) / 1024).toFixed(1)}KB / 100KB
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Card 4: Footer */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>footer</h3>
            <span className={styles.badge}>branding</span>
          </div>

          <div className={settingsStyles.footerSection}>
            <div className={settingsStyles.checkboxGroup}>
              <input
                type="checkbox"
                id="footer-branding"
                className={settingsStyles.checkbox}
                checked={showFooterBranding}
                onChange={(e) => setShowFooterBranding(e.target.checked)}
              />
              <label htmlFor="footer-branding" className={settingsStyles.checkboxLabel}>
                show footer branding text
              </label>
            </div>

            {!showFooterBranding && (
              <div className={settingsStyles.formGroup}>
                <label className={settingsStyles.inputLabel}>branding text</label>
                <textarea
                  className={settingsStyles.textarea}
                  placeholder="enter your branding text or copyright notice"
                  value={footerText}
                  onChange={(e) => setFooterText(e.target.value)}
                  rows={3}
                />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Actions */}
      <div className={settingsStyles.actionArea}>
        <button
          className={settingsStyles.primaryButton}
          onClick={handleSave}
          disabled={isSaving}
        >
          {isSaving ? 'saving...' : 'save all changes'}
        </button>
        <button 
          className={settingsStyles.secondaryButton}
          onClick={() => {
            const config = (window as any).MINIME_ADMIN_CONFIG;
            const publicUrl = config?.publicUrl || '/';
            window.open(publicUrl, '_blank');
          }}
        >
          view my minime
        </button>
      </div>
        </>
      )}
    </div>
  );
}
