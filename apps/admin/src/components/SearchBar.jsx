function SearchBar({ value, onChange, placeholder }) {
  return (
    <input
      value={value}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder}
      style={{
        width: '100%',
        padding: '10px 12px',
        borderRadius: 10,
        border: '1px solid #dbe2ea',
        background: '#f8fafc',
      }}
    />
  );
}

export default SearchBar;
