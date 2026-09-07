// Access tokens are issued as `access-<userId>-<timestamp>` (see AuthService). There is no
// JWT/session middleware in this app yet, so this parses that token to identify the caller.
export function getUserIdFromAuthHeader(authorizationHeader: string | undefined): string | null {
  if (!authorizationHeader) {
    return null;
  }

  const token = authorizationHeader.replace(/^Bearer\s+/i, '').trim();
  const match = token.match(/^access-(.+)-\d+$/);
  return match ? match[1] : null;
}
