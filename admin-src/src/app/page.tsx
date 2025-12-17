'use client';

import { useState, useEffect } from 'react';
import AdminShell from '@/components/admin-shell';
import styles from './page.module.css';

export default function AdminPage() {
  const [isLoggedIn, setIsLoggedIn] = useState<boolean | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  
  // Login form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  
  // Password reset state
  const [showResetForm, setShowResetForm] = useState(false);
  const [resetEmail, setResetEmail] = useState('');
  const [resetSent, setResetSent] = useState(false);

  useEffect(() => {
    // Check if user is logged in from window config
    const config = (window as any).MINIME_ADMIN_CONFIG;
    setIsLoggedIn(config?.isLoggedIn === true);
  }, []);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    try {
      const config = (window as any).MINIME_ADMIN_CONFIG;
      const restRoot = config?.restRoot || '/wp-json/';

      const response = await fetch(restRoot + 'minime/v1/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ username: email, password, remember: true }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Login failed');
      }

      // Reload page to get fresh nonce
      window.location.reload();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setIsLoading(false);
    }
  };

  const handlePasswordReset = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    try {
      const config = (window as any).MINIME_ADMIN_CONFIG;
      const restRoot = config?.restRoot || '/wp-json/';

      const response = await fetch(restRoot + 'minime/v1/request-password-reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: resetEmail }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Password reset request failed');
      }

      setResetSent(true);
      setSuccessMessage('If this email is registered, you will receive a password reset link shortly.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Password reset failed');
    } finally {
      setIsLoading(false);
    }
  };

  // Loading state while checking auth
  if (isLoggedIn === null) {
    return (
      <div className={styles.loginContainer}>
        <div className={styles.loginCard}>
          <p style={{ textAlign: 'center', color: '#666' }}>loading...</p>
        </div>
      </div>
    );
  }

  // Show login form if not authenticated
  if (!isLoggedIn) {
    return (
      <div className={styles.loginContainer}>
        <div className={styles.loginCard}>
          <h1 className={styles.loginTitle}>minime</h1>
          <p className={styles.loginSubtitle}>sign in to your dashboard</p>

          {error && (
            <div className={styles.errorMessage}>❌ {error}</div>
          )}

          {successMessage && (
            <div className={styles.successMessage}>✅ {successMessage}</div>
          )}

          {!showResetForm ? (
            <form onSubmit={handleLogin} className={styles.loginForm}>
              <div className={styles.formGroup}>
                <label className={styles.label}>email or username</label>
                <input
                  type="text"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className={styles.input}
                  placeholder="you@example.com"
                  required
                  disabled={isLoading}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label}>password</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className={styles.input}
                  placeholder="••••••••"
                  required
                  disabled={isLoading}
                />
              </div>

              <button
                type="submit"
                className={styles.submitButton}
                disabled={isLoading}
              >
                {isLoading ? 'signing in...' : 'sign in'}
              </button>

              <button
                type="button"
                className={styles.linkButton}
                onClick={() => {
                  setShowResetForm(true);
                  setError(null);
                  setSuccessMessage(null);
                }}
              >
                forgot password?
              </button>
            </form>
          ) : (
            <form onSubmit={handlePasswordReset} className={styles.loginForm}>
              {!resetSent ? (
                <>
                  <div className={styles.formGroup}>
                    <label className={styles.label}>email address</label>
                    <input
                      type="email"
                      value={resetEmail}
                      onChange={(e) => setResetEmail(e.target.value)}
                      className={styles.input}
                      placeholder="you@example.com"
                      required
                      disabled={isLoading}
                    />
                  </div>

                  <button
                    type="submit"
                    className={styles.submitButton}
                    disabled={isLoading}
                  >
                    {isLoading ? 'sending...' : 'send reset link'}
                  </button>
                </>
              ) : null}

              <button
                type="button"
                className={styles.linkButton}
                onClick={() => {
                  setShowResetForm(false);
                  setResetSent(false);
                  setError(null);
                  setSuccessMessage(null);
                }}
              >
                back to sign in
              </button>
            </form>
          )}
        </div>
      </div>
    );
  }

  // User is logged in - show admin shell
  return <AdminShell />;
}
