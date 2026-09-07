function InventoryChart({ products }) {
  const maxStock = Math.max(...products.map((product) => Number(product.stock) || 0), 1);

  return (
    <div style={{ marginTop: 12 }}>
      <svg viewBox="0 0 320 140" width="100%" height="140" role="img" aria-label="Inventory stock overview">
        {products.slice(0, 6).map((product, index) => {
          const height = (Number(product.stock) / maxStock) * 90;
          const x = 20 + index * 48;
          return (
            <g key={product.id}>
              <rect x={x} y={120 - height} width="24" height={height} rx="4" fill="#4f46e5" />
              <text x={x + 12} y="132" textAnchor="middle" fontSize="10" fill="#64748b">
                {product.name.slice(0, 3)}
              </text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}

export default InventoryChart;
