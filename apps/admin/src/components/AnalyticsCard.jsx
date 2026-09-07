function AnalyticsCard({ title, value, hint }) {
  return (
    <div style={{ background: 'linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)', borderRadius: 16, padding: 16, boxShadow: '0 10px 30px rgba(15,23,42,0.06)' }}>
      <div style={{ color: '#64748b', fontSize: 13 }}>{title}</div>
      <div style={{ fontSize: 24, fontWeight: 700, marginTop: 6 }}>{value}</div>
      <div style={{ color: '#94a3b8', fontSize: 12, marginTop: 4 }}>{hint}</div>
    </div>
  );
}

export default AnalyticsCard;
