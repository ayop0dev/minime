'use client';

import { ReactNode } from 'react';
import styles from './admin-shell.module.css';
import SideNav from './side-nav';
import SettingsPanel from './panels/settings-panel';
import PostsPanel from './panels/posts-panel';
import SubscriptionPanel from './panels/subscription-panel';
import { useState } from 'react';

type TabId = 'settings' | 'posts' | 'subscription';

export default function AdminShell() {
  const [activeTab, setActiveTab] = useState<TabId>('settings');

  const renderPanel = (): ReactNode => {
    switch (activeTab) {
      case 'settings':
        return <SettingsPanel />;
      case 'posts':
        return <PostsPanel />;
      case 'subscription':
        return <SubscriptionPanel />;
      default:
        return null;
    }
  };

  return (
    <div className={styles.shell}>
      <SideNav activeTab={activeTab} onTabChange={(tabId) => setActiveTab(tabId as TabId)} />
      <main className={styles.main}>
        <div className={styles.content}>{renderPanel()}</div>
      </main>
    </div>
  );
}
