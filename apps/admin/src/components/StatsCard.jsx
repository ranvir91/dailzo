function StatsCard({ label, value }) {
  return (
    <div style={{ background: '#fff', padding: 16, borderRadius: 12, boxShadow: '0 5px 15px rgba(0,0,0,0.05)' }}>
      <div style={{ color: '#666', fontSize: 13 }}>{label}</div>
      <div style={{ fontSize: 24, fontWeight: 700, marginTop: 6 }}>{value}</div>
    </div>
  );
}

export default StatsCard;
