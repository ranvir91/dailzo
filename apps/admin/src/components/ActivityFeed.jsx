function ActivityFeed({ items }) {
  return (
    <div style={{ marginTop: 12 }}>
      {items.map((item, index) => (
        <div key={index} style={{ borderBottom: '1px solid #eef2f7', padding: '10px 0', color: '#64748b' }}>
          {item}
        </div>
      ))}
    </div>
  );
}

export default ActivityFeed;
