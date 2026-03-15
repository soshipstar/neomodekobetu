'use client';

import { useEffect, useCallback, useRef } from 'react';
import { getEcho, disconnectEcho } from '@/lib/echo';
import type Echo from 'laravel-echo';

interface UseWebSocketOptions {
  channel: string;
  event: string;
  isPrivate?: boolean;
  onMessage: (data: unknown) => void;
  enabled?: boolean;
}

/**
 * Generic WebSocket hook using Laravel Echo
 */
export function useWebSocket({
  channel,
  event,
  isPrivate = true,
  onMessage,
  enabled = true,
}: UseWebSocketOptions) {
  const callbackRef = useRef(onMessage);
  callbackRef.current = onMessage;

  useEffect(() => {
    if (!enabled || !channel || !event) return;

    let echo: Echo<'reverb'>;
    try {
      echo = getEcho();
    } catch {
      return;
    }

    const ch = isPrivate
      ? echo.private(channel)
      : echo.channel(channel);

    ch.listen(event, (data: unknown) => {
      callbackRef.current(data);
    });

    return () => {
      if (isPrivate) {
        echo.leave(channel);
      } else {
        echo.leave(channel);
      }
    };
  }, [channel, event, isPrivate, enabled]);

  const disconnect = useCallback(() => {
    disconnectEcho();
  }, []);

  return { disconnect };
}
