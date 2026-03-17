import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
if (typeof window !== 'undefined') {
  (window as unknown as Record<string, unknown>).Pusher = Pusher;
}

let echoInstance: Echo<'reverb'> | null = null;

/**
 * Get the Sanctum Bearer token from localStorage
 */
function getAuthToken(): string {
  if (typeof window === 'undefined') return '';
  return localStorage.getItem('auth_token') || '';
}

/**
 * Get or create the Laravel Echo instance configured for Reverb WebSocket
 */
export function getEcho(): Echo<'reverb'> {
  if (echoInstance) return echoInstance;

  const backendUrl = process.env.NEXT_PUBLIC_BACKEND_URL
    || process.env.NEXT_PUBLIC_API_URL?.replace(/\/api$/, '')
    || 'http://localhost:8000';

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY || '',
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || 'localhost',
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT) || 8080,
    wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT) || 8080,
    forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${backendUrl}/api/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${getAuthToken()}`,
        Accept: 'application/json',
      },
    },
  });

  return echoInstance;
}

/**
 * Disconnect Echo instance and clear cached instance
 * (forces re-creation with fresh token on next getEcho() call)
 */
export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}

export default getEcho;
