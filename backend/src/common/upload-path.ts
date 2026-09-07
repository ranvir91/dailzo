export function toUploadPath(value: string | null | undefined): string | null | undefined {
  if (value === null || value === undefined || !value) {
    return value;
  }

  const uploadsIndex = value.indexOf('/uploads/');
  if (uploadsIndex >= 0) {
    return value.slice(uploadsIndex + 1);
  }

  return value.startsWith('uploads/') ? value : value;
}