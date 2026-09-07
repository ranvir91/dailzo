function ThemeToggle({ darkMode, onToggle }) {
  return (
    <button
      onClick={onToggle}
      style={{
        padding: '8px 12px',
        borderRadius: 999,
        border: 'none',
        background: darkMode ? '#334155' : '#e2e8f0',
        color: darkMode ? '#f8fafc' : '#0f172a',
        cursor: 'pointer',
      }}
    >
      {darkMode ? '☀️ Light' : '🌙 Dark'}
    </button>
  );
}

export default ThemeToggle;
