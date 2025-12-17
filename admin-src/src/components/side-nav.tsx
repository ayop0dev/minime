'use client';

import styles from './side-nav.module.css';

export interface NavTab {
  id: string;
  label: string;
  icon: string;
}

const TABS: NavTab[] = [
  { id: 'settings', label: 'settings', icon: '⚙️' },
  { id: 'posts', label: 'posts', icon: '📝' },
  { id: 'subscription', label: 'subscription', icon: '💳' },
];

interface SideNavProps {
  activeTab: string;
  onTabChange: (tabId: string) => void;
}

export default function SideNav({ activeTab, onTabChange }: SideNavProps) {
  return (
    <nav className={styles.nav}>
      <div className={styles.navHeader}>
        <h1 className={styles.title}>minime</h1>
        <p className={styles.subtitle}>admin</p>
      </div>

      <div className={styles.tabsList}>
        {TABS.map((tab) => (
          <button
            key={tab.id}
            className={`${styles.tabButton} ${
              activeTab === tab.id ? styles.active : ''
            }`}
            onClick={() => onTabChange(tab.id)}
            aria-current={activeTab === tab.id ? 'page' : undefined}
          >
            <span className={styles.icon}>{tab.icon}</span>
            <span className={styles.label}>{tab.label}</span>
          </button>
        ))}
      </div>

      <div className={styles.navFooter}>
        <button
          className={styles.logoutButton}
          onClick={async () => {
            const config = (window as any).MINIME_ADMIN_CONFIG;
            try {
              const restRoot = config?.restRoot || '/wp-json/';
              await fetch(restRoot + 'minime/v1/logout', {
                method: 'POST',
                credentials: 'include',
              });
            } catch (e) {
              // Ignore errors, proceed to redirect
            }
            // Redirect back to admin URL (will show login form)
            window.location.href = config?.logoutUrl || window.location.pathname;
          }}
        >
          <span className={styles.icon}>🚪</span>
          <span className={styles.label}>logout</span>
        </button>
        <div className={styles.footerItem}>
          <span className={styles.badge}>v1.0</span>
        </div>
      </div>
    </nav>
  );
}
