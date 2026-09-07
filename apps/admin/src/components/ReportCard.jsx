function ReportCard({ title, body }) {
  return (
    <div style={{ background: 'linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)', borderRadius: 16, padding: 16, boxShadow: '0 10px 30px rgba(15,23,42,0.06)' }}>
      <div style={{ fontWeight: 700, marginBottom: 6 }}>{title}</div>
      <div style={{ color: '#64748b', fontSize: 13 }}>{body}</div>
    </div>
  );
}

export default ReportCard;
