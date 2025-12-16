import styles from './panel.module.css';

export default function SubscriptionPanel() {
  return (
    <div className={styles.panel}>
      <header className={styles.header}>
        <h1 className={styles.title}>subscription</h1>
        <p className={styles.description}>manage your premium plan and billing</p>
      </header>

      <div className={styles.grid}>
        {/* Current Plan Card */}
        <div className={`${styles.card} ${styles.largeCard}`}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>current plan</h3>
            <span className={styles.badge}>active</span>
          </div>
          <div className={styles.planContent}>
            <div className={styles.planSection}>
              <p className={styles.planName}>pro</p>
              <p className={styles.planPrice}>$9.99 / month</p>
              <p className={styles.planDescription}>unlimited posts, advanced analytics, priority support</p>
            </div>
            <div className={styles.planFeatures}>
              <div className={styles.feature}>
                <span className={styles.featureIcon}>✓</span>
                <span className={styles.featureText}>unlimited posts</span>
              </div>
              <div className={styles.feature}>
                <span className={styles.featureIcon}>✓</span>
                <span className={styles.featureText}>analytics</span>
              </div>
              <div className={styles.feature}>
                <span className={styles.featureIcon}>✓</span>
                <span className={styles.featureText}>priority support</span>
              </div>
            </div>
          </div>
        </div>

        {/* Billing Card */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>billing</h3>
            <span className={styles.badge}>next renewal</span>
          </div>
          <div className={styles.cardContent}>
            <div className={styles.billingItem}>
              <label className={styles.label}>renewal date</label>
              <p className={styles.value}>2025-02-15</p>
            </div>
            <div className={styles.billingItem}>
              <label className={styles.label}>payment method</label>
              <p className={styles.value}>•••• 4242</p>
            </div>
            <div className={styles.billingItem}>
              <label className={styles.label}>auto-renewal</label>
              <p className={styles.value}>enabled</p>
            </div>
          </div>
        </div>

        {/* Usage Card */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>usage</h3>
            <span className={styles.badge}>this month</span>
          </div>
          <div className={styles.cardContent}>
            <div className={styles.usageItem}>
              <div className={styles.usageLabel}>
                <label className={styles.label}>posts created</label>
                <span className={styles.usageValue}>8 / unlimited</span>
              </div>
              <div className={styles.progressBar}>
                <div className={styles.progress} style={{ width: '8%' }}></div>
              </div>
            </div>
            <div className={styles.usageItem}>
              <div className={styles.usageLabel}>
                <label className={styles.label}>api calls</label>
                <span className={styles.usageValue}>342 / 10,000</span>
              </div>
              <div className={styles.progressBar}>
                <div className={styles.progress} style={{ width: '3.4%' }}></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
