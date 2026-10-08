// API and public web routes share the verified deployment origin.
export function bhwUpdatePageUrl(baseUrl: string): string | null {
  try {
    const url = new URL(baseUrl);
    if (url.protocol !== 'https:' || url.username || url.password ||
        url.hostname === 'localhost' || url.hostname.endsWith('.localhost') ||
        url.hostname === '[::1]' || /^127\./.test(url.hostname)) return null;
    return new URL('/mobile/bhw/update', url.origin).toString();
  } catch {
    return null;
  }
}
