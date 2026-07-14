import { BootstrapPayload, SyncResponse } from '../types';

type LoginPayload = {
  email: string;
  password: string;
  device_name: string;
  device_platform: string;
  app_version: string;
};

function normalizeBaseUrl(baseUrl: string) {
  return baseUrl.trim().replace(/\/+$/, '');
}

async function request<T>(
  baseUrl: string,
  path: string,
  options: RequestInit = {},
  token?: string | null
): Promise<T> {
  const response = await fetch(`${normalizeBaseUrl(baseUrl)}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers ?? {}),
    },
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      payload?.message ??
      payload?.errors?.email?.[0] ??
      'Request failed. Please try again.';

    throw new Error(message);
  }

  return payload as T;
}

export async function mobileLogin(baseUrl: string, payload: LoginPayload) {
  return request<{
    token: string;
    token_type: string;
    server_time: string;
    single_device_enforced: boolean;
    revoked_tokens: number;
    user: {
      id: number;
      name: string;
      email: string;
      role: 'bhw';
      assignment_label: string;
      assigned_barangay_id: number;
      assigned_purok_id: number;
    };
  }>(baseUrl, '/api/mobile/auth/login', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function mobileForgotPassword(baseUrl: string, email: string) {
  return request<{ success: boolean; message: string }>(
    baseUrl,
    '/api/mobile/auth/forgot-password',
    {
      method: 'POST',
      body: JSON.stringify({ email }),
    }
  );
}

export async function mobileBootstrap(baseUrl: string, token: string) {
  return request<BootstrapPayload>(baseUrl, '/api/mobile/bootstrap', {}, token);
}

export async function mobileSync(
  baseUrl: string,
  token: string,
  payload: Record<string, unknown>
) {
  return request<SyncResponse>(
    baseUrl,
    '/api/mobile/sync',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    token
  );
}

export async function mobileVerify(baseUrl: string, token: string) {
  return request<{ valid: boolean }>(baseUrl, '/api/mobile/verify', {}, token);
}

export async function mobileLogout(baseUrl: string, token: string) {
  return request<{ success: boolean }>(
    baseUrl,
    '/api/mobile/auth/logout',
    {
      method: 'POST',
      body: JSON.stringify({}),
    },
    token
  );
}
