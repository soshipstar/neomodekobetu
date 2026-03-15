import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
if (typeof window !== 'undefined') {
  (window as unknown as Record<string, unknown>).Pusher = Pusher;
}

let echoInstance: Echo<'reverb'> | null = null;

/**
 * Get or create the Laravel Echo instance configured for Reverb WebSocket
 */
export function getEcho(): Echo<'reverb'> {
  if (echoInstance) return echoInstance;

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY || '',
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || 'localhost',
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT) || 8080,
    wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT) || 8080,
    forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/broadcasting/auth`,
    auth: {
      headers: {
        'X-XSRF-TOKEN': getXsrfToken() || '',
      },
    },
  });

  return echoInstance;
}

/**
 * Disconnect Echo instance
 */
export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}

function getXsrfToken(): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  if (!match) return null;
  return decodeURIComponent(match[1]);
}

export default getEcho;
