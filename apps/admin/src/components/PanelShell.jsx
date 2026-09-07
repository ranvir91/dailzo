import { useEffect, useState } from 'react';
import {
  FaAngleDoubleLeft,
  FaAngleDoubleRight,
  FaBars,
  FaBoxOpen,
  FaClipboardList,
  FaCog,
  FaMapMarkerAlt,
  FaPowerOff,
  FaTachometerAlt,
  FaThLarge,
  FaTicketAlt,
  FaUsers,
} from 'react-icons/fa';

const NAV_ICONS = {
  Overview: FaTachometerAlt,
  Categories: FaThLarge,
  Products: FaBoxOpen,
  Orders: FaClipboardList,
  Customers: FaUsers,
  'Serviceable Pincodes': FaMapMarkerAlt,
  Coupons: FaTicketAlt,
  Settings: FaCog,
};

function Logo({ size = 36 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect width="40" height="40" rx="11" fill="url(#dailzo-logo-gradient)" />
      <text x="50%" y="54%" textAnchor="middle" dominantBaseline="middle" fontFamily="system-ui, sans-serif" fontSize="20" fontWeight="800" fill="white">
        D
      </text>
      <defs>
        <linearGradient id="dailzo-logo-gradient" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#4338ca" />
          <stop offset="1" stopColor="#7c3aed" />
        </linearGradient>
      </defs>
    </svg>
  );
}

function PanelShell({ title, subtitle, children, darkMode, onLogout, navItems, activeView, onNavigate }) {
  const shellBackground = darkMode ? 'radial-gradient(circle at top, #020617 0%, #0f172a 52%, #111827 100%)' : 'radial-gradient(circle at top, #f8fafc 0%, #eef2ff 45%, #e2e8f0 100%)';
  const sidebarBackground = darkMode ? 'linear-gradient(180deg, #111827 0%, #020617 100%)' : 'linear-gradient(180deg, #0f172a 0%, #111827 100%)';
  const headingColor = darkMode ? '#f8fafc' : '#0f172a';
  const mutedColor = darkMode ? '#94a3b8' : '#64748b';
  const [isMobile, setIsMobile] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [collapsed, setCollapsed] = useState(() => window.localStorage.getItem('dailzo-admin-sidebar-collapsed') === 'true');

  useEffect(() => {
    const syncViewport = () => {
      const mobile = window.innerWidth < 900;
      setIsMobile(mobile);
      setSidebarOpen(!mobile);
    };

    syncViewport();
    window.addEventListener('resize', syncViewport);
    return () => window.removeEventListener('resize', syncViewport);
  }, []);

  const handleNavigate = (label) => {
    onNavigate?.(label);
    if (isMobile) {
      setSidebarOpen(false);
    }
  };

  const toggleCollapsed = () => {
    setCollapsed((value) => {
      const next = !value;
      window.localStorage.setItem('dailzo-admin-sidebar-collapsed', String(next));
      return next;
    });
  };

  const isCollapsed = collapsed && !isMobile;
  const sidebarWidth = isCollapsed ? 76 : 260;

  return (
    <div style={{ display: 'flex', minHeight: '100vh', background: shellBackground, position: 'relative' }}>
      <aside
        style={{
          width: sidebarWidth,
          flexShrink: 0,
          background: sidebarBackground,
          color: 'white',
          padding: isCollapsed ? '24px 12px' : 24,
          display: 'flex',
          flexDirection: 'column',
          gap: 16,
          position: isMobile ? 'fixed' : 'sticky',
          top: 0,
          left: sidebarOpen ? 0 : isMobile ? '-280px' : 0,
          bottom: 0,
          zIndex: 1100,
          transition: 'left 180ms ease, width 180ms ease, padding 180ms ease',
          boxShadow: sidebarOpen ? '12px 0 30px rgba(2, 6, 23, 0.16)' : 'none',
          overflowY: 'auto',
          overflowX: 'hidden',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8, paddingBottom: 8, borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
          {isCollapsed ? (
            <Logo />
          ) : (
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <Logo />
              <div>
                <h2 style={{ margin: 0, letterSpacing: '-0.03em' }}>Dailzo</h2>
                <p style={{ opacity: 0.78, margin: 0 }}>Admin operations</p>
              </div>
            </div>
          )}
          {!isMobile ? (
            <button
              type="button"
              onClick={toggleCollapsed}
              title={isCollapsed ? 'Expand menu' : 'Collapse menu'}
              style={{
                flexShrink: 0,
                width: 32,
                height: 32,
                display: 'grid',
                placeItems: 'center',
                borderRadius: 8,
                border: '1px solid rgba(255,255,255,0.14)',
                background: 'rgba(255,255,255,0.08)',
                color: 'white',
                cursor: 'pointer',
              }}
            >
              {isCollapsed ? <FaAngleDoubleRight size={13} /> : <FaAngleDoubleLeft size={13} />}
            </button>
          ) : null}
        </div>

        {navItems?.length ? (
          <nav style={{ display: 'grid', gap: 6 }}>
            {navItems.map((item) => {
              const Icon = NAV_ICONS[item];
              return (
                <button
                  key={item}
                  type="button"
                  onClick={() => handleNavigate(item)}
                  title={isCollapsed ? item : undefined}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: isCollapsed ? 'center' : 'flex-start',
                    gap: 10,
                    textAlign: 'left',
                    padding: isCollapsed ? '10px 0' : '10px 12px',
                    borderRadius: 10,
                    border: 'none',
                    cursor: 'pointer',
                    fontWeight: activeView === item ? 700 : 500,
                    color: activeView === item ? 'white' : 'rgba(255,255,255,0.72)',
                    background: activeView === item ? 'rgba(255,255,255,0.14)' : 'transparent',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                  }}
                >
                  {Icon ? <Icon size={15} style={{ flexShrink: 0 }} /> : null}
                  {isCollapsed ? null : item}
                </button>
              );
            })}
          </nav>
        ) : null}

        <div style={{ marginTop: 'auto' }}>
          <button
            type="button"
            onClick={onLogout}
            title={isCollapsed ? 'Logout' : undefined}
            style={{
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: isCollapsed ? 'center' : 'flex-start',
              gap: 10,
              padding: '10px 12px',
              borderRadius: 12,
              border: '1px solid rgba(255,255,255,0.14)',
              color: 'white',
              background: 'rgba(255,255,255,0.08)',
              cursor: 'pointer',
              fontWeight: 700,
              overflow: 'hidden',
              whiteSpace: 'nowrap',
            }}
          >
            <FaPowerOff size={14} style={{ flexShrink: 0 }} />
            {isCollapsed ? null : 'Logout'}
          </button>
        </div>
      </aside>

      {isMobile && sidebarOpen ? (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(2, 6, 23, 0.45)', zIndex: 1000 }} onClick={() => setSidebarOpen(false)} />
      ) : null}

      <main style={{ flex: 1, padding: 24, background: shellBackground, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
        {isMobile ? (
          <button
            type="button"
            onClick={() => setSidebarOpen((value) => !value)}
            style={{ position: 'fixed', top: 20, left: 20, zIndex: 1200, border: 'none', borderRadius: 999, width: 44, height: 44, display: 'grid', placeItems: 'center', background: darkMode ? '#111827' : 'white', color: headingColor, cursor: 'pointer', boxShadow: '0 12px 20px rgba(15, 23, 42, 0.16)' }}
          >
            <FaBars size={16} />
          </button>
        ) : null}

        {title || subtitle ? (
          <div style={{ marginBottom: 24, padding: '18px 20px', borderRadius: 20, background: darkMode ? 'rgba(15, 23, 42, 0.72)' : 'rgba(255,255,255,0.72)', border: '1px solid rgba(148, 163, 184, 0.14)', boxShadow: '0 18px 40px rgba(15, 23, 42, 0.08)' }}>
            {title ? <h1 style={{ margin: 0, fontSize: 26, letterSpacing: '-0.04em', color: headingColor }}>{title}</h1> : null}
            {subtitle ? <p style={{ margin: '4px 0 0', color: mutedColor }}>{subtitle}</p> : null}
          </div>
        ) : null}

        <div style={{ flex: 1 }}>
          {children}
        </div>
      </main>
    </div>
  );
}

export default PanelShell;
