import styles from './panel.module.css';

export default function PostsPanel() {
  const mockPosts = [
    {
      id: 1,
      title: 'getting started with minime',
      status: 'published',
      date: '2025-01-15',
      count: 2,
    },
    {
      id: 2,
      title: 'advanced configuration guide',
      status: 'draft',
      date: '2025-01-14',
      count: 5,
    },
    {
      id: 3,
      title: 'api reference documentation',
      status: 'published',
      date: '2025-01-12',
      count: 12,
    },
  ];

  return (
    <div className={styles.panel}>
      <header className={styles.header}>
        <h1 className={styles.title}>posts</h1>
        <p className={styles.description}>manage all your posts and content</p>
      </header>

      <div className={styles.listContainer}>
        {/* Posts List Card */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>recent posts</h3>
            <span className={styles.badge}>{mockPosts.length} posts</span>
          </div>

          <div className={styles.list}>
            {mockPosts.map((post, index) => (
              <div key={post.id} className={styles.listItem}>
                <div className={styles.listItemContent}>
                  <div>
                    <h4 className={styles.postTitle}>{post.title}</h4>
                    <p className={styles.postMeta}>
                      {post.date} • {post.status}
                    </p>
                  </div>
                  <span className={styles.postCount}>{post.count}</span>
                </div>
                {index < mockPosts.length - 1 && (
                  <div className={styles.listDivider} />
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Stats Card */}
        <div className={styles.card}>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>stats</h3>
          </div>
          <div className={styles.statsGrid}>
            <div className={styles.statItem}>
              <p className={styles.statLabel}>published</p>
              <p className={styles.statValue}>2</p>
            </div>
            <div className={styles.statItem}>
              <p className={styles.statLabel}>drafts</p>
              <p className={styles.statValue}>1</p>
            </div>
            <div className={styles.statItem}>
              <p className={styles.statLabel}>total views</p>
              <p className={styles.statValue}>19</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
