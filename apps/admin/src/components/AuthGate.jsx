import { useState } from 'react';
import { API_BASE, setSession } from '../lib/session';

function AuthGate({ onLoginSuccess }) {
  const [phone, setPhone] = useState('9999999998');
  const [otp, setOtp] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [devOtp, setDevOtp] = useState('');
  const [sendingOtp, setSendingOtp] = useState(false);
  const [loggingIn, setLoggingIn] = useState(false);
  const [error, setError] = useState('');

  const sendOtp = async (event) => {
    event.preventDefault();
    setError('');
    setSendingOtp(true);
    try {
      const response = await fetch(`${API_BASE}/auth/send-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone }),
      });
      const json = await response.json();
      if (!json.success) {
        throw new Error(json.message || 'Unable to send OTP');
      }
      setOtpSent(true);
      setDevOtp(json.data?.otp ?? '');
    } catch (err) {
      setError(err.message || 'Unable to send OTP');
    } finally {
      setSendingOtp(false);
    }
  };

  const login = async (event) => {
    event.preventDefault();
    setError('');
    setLoggingIn(true);
    try {
      const response = await fetch(`${API_BASE}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone, otp }),
      });
      const json = await response.json();
      if (!json.success) {
        throw new Error(json.message || 'Login failed');
      }
      if (json.data.user.role !== 'ADMIN') {
        throw new Error('This account does not have admin access');
      }
      setSession({
        accessToken: json.data.accessToken,
        refreshToken: json.data.refreshToken,
        userId: json.data.user.id,
        phone: json.data.user.phone,
      });
      onLoginSuccess();
    } catch (err) {
      setError(err.message || 'Login failed');
    } finally {
      setLoggingIn(false);
    }
  };

  return (
    <div style={loginPageStyle}>
      <div style={loginGlowA} />
      <div style={loginGlowB} />
      <form onSubmit={otpSent ? login : sendOtp} style={loginCardStyle}>
        <div style={{ marginBottom: 18 }}>
          <div style={loginBadgeStyle}>Dailzo Admin</div>
          <h2 style={{ margin: '12px 0 8px', fontSize: 28, lineHeight: 1.15 }}>Secure admin access</h2>
          <p style={{ color: '#64748b', margin: 0 }}>Sign in with an admin phone number to manage products, categories, orders, and store settings.</p>
        </div>
        <label style={loginLabelStyle}>
          Admin phone number
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="9999999998"
            style={loginInputStyle}
            disabled={otpSent}
          />
        </label>
        {otpSent ? (
          <label style={loginLabelStyle}>
            OTP
            <input
              type="text"
              value={otp}
              onChange={(e) => setOtp(e.target.value)}
              placeholder="4-digit OTP"
              style={loginInputStyle}
              autoFocus
            />
          </label>
        ) : null}
        {otpSent && devOtp ? <div style={devOtpStyle}>Dev OTP (no SMS configured): {devOtp}</div> : null}
        {error ? <div style={loginErrorStyle}>{error}</div> : null}
        <button type="submit" style={loginButtonStyle} disabled={sendingOtp || loggingIn}>
          {otpSent ? (loggingIn ? 'Signing in…' : 'Verify & Sign In') : (sendingOtp ? 'Sending OTP…' : 'Send OTP')}
        </button>
        {otpSent ? (
          <button
            type="button"
            style={secondaryLinkStyle}
            onClick={() => { setOtpSent(false); setOtp(''); setDevOtp(''); setError(''); }}
          >
            Use a different phone number
          </button>
        ) : null}
      </form>
    </div>
  );
}

const loginPageStyle = {
  minHeight: '100vh',
  display: 'grid',
  placeItems: 'center',
  background: 'radial-gradient(circle at top, #f8fafc 0%, #eef2ff 35%, #e2e8f0 100%)',
  position: 'relative',
  overflow: 'hidden',
  padding: 24,
};

const loginGlowA = {
  position: 'absolute',
  width: 360,
  height: 360,
  borderRadius: '50%',
  background: 'rgba(79, 70, 229, 0.16)',
  filter: 'blur(30px)',
  top: -120,
  right: -100,
};

const loginGlowB = {
  position: 'absolute',
  width: 260,
  height: 260,
  borderRadius: '50%',
  background: 'rgba(15, 118, 110, 0.12)',
  filter: 'blur(28px)',
  bottom: -80,
  left: -60,
};

const loginCardStyle = {
  width: '100%',
  maxWidth: 420,
  position: 'relative',
  zIndex: 1,
  background: 'rgba(255, 255, 255, 0.78)',
  backdropFilter: 'blur(18px)',
  border: '1px solid rgba(148, 163, 184, 0.22)',
  padding: 32,
  borderRadius: 24,
  boxShadow: '0 24px 80px rgba(15, 23, 42, 0.12)',
  display: 'grid',
  gap: 12,
};

const loginBadgeStyle = {
  display: 'inline-flex',
  alignItems: 'center',
  padding: '6px 10px',
  borderRadius: 999,
  fontSize: 12,
  fontWeight: 700,
  letterSpacing: '0.08em',
  textTransform: 'uppercase',
  color: '#4338ca',
  background: '#eef2ff',
};

const loginLabelStyle = {
  display: 'grid',
  gap: 8,
  color: '#0f172a',
  fontSize: 14,
  fontWeight: 600,
};

const loginInputStyle = {
  width: '100%',
  padding: '12px 14px',
  borderRadius: 14,
  border: '1px solid #dbe2ea',
  background: '#f8fafc',
  fontSize: 14,
  outline: 'none',
};

const devOtpStyle = {
  color: '#166534',
  background: '#f0fdf4',
  border: '1px solid #bbf7d0',
  borderRadius: 12,
  padding: '10px 12px',
  fontSize: 13,
  fontWeight: 600,
};

const loginErrorStyle = {
  color: '#b91c1c',
  background: '#fef2f2',
  border: '1px solid #fecaca',
  borderRadius: 12,
  padding: '10px 12px',
  marginTop: 4,
};

const loginButtonStyle = {
  width: '100%',
  marginTop: 4,
  padding: '12px 14px',
  borderRadius: 14,
  border: 'none',
  background: 'linear-gradient(135deg, #4338ca 0%, #4f46e5 100%)',
  color: 'white',
  fontWeight: 700,
  cursor: 'pointer',
  boxShadow: '0 14px 24px rgba(79, 70, 229, 0.24)',
};

const secondaryLinkStyle = {
  background: 'transparent',
  border: 'none',
  color: '#4f46e5',
  fontWeight: 600,
  cursor: 'pointer',
  padding: 0,
  fontSize: 13,
  justifySelf: 'center',
};

export default AuthGate;
