import { useState, useEffect, useCallback, useRef } from "react";

interface SyncProgress {
  status: "in_progress" | "success" | "failed";
  current_step?: "gitlab" | "bitrix24" | "matching" | null;
  error_message?: string | null;
}

const MAX_RETRIES = 5;

export function useSyncSSE() {
  const [progress, setProgress] = useState<SyncProgress | null>(null);
  const eventSourceRef = useRef<EventSource | null>(null);
  const disconnectedByUserRef = useRef<boolean>(false);
  const retryCountRef = useRef<number>(0);
  const reconnectTimerRef = useRef<number | null>(null);
  // Holds the latest `connect` implementation so the reconnect timer can call
  // it recursively without referring to the binding before it is initialised
  // (which the react-hooks/immutability rule in eslint-plugin-react-hooks v7
  // flags as a TDZ access).
  const connectRef = useRef<() => void>(() => {});

  const connect = useCallback(() => {
    disconnectedByUserRef.current = false;
    if (reconnectTimerRef.current) {
      clearTimeout(reconnectTimerRef.current);
      reconnectTimerRef.current = null;
    }
    eventSourceRef.current?.close();

    const es = new EventSource("/api/sync/stream");
    eventSourceRef.current = es;

    const handleEvent = (event: MessageEvent) => {
      const data: SyncProgress = JSON.parse(event.data);
      setProgress(data);
      // reset backoff on any event so transient blips don't exhaust retries
      retryCountRef.current = 0;

      if (data.status === "success" || data.status === "failed") {
        // suppress reconnect after clean server close on terminal status
        disconnectedByUserRef.current = true;
        es.close();
        eventSourceRef.current = null;
      }
    };

    es.addEventListener("state", handleEvent);
    es.addEventListener("progress", handleEvent);
    es.addEventListener("done", handleEvent);

    es.onerror = () => {
      es.close();
      eventSourceRef.current = null;
      if (disconnectedByUserRef.current) return;
      if (retryCountRef.current >= MAX_RETRIES) return;
      const delay = Math.min(2000 * 2 ** retryCountRef.current, 15000);
      retryCountRef.current += 1;
      reconnectTimerRef.current = window.setTimeout(() => {
        connectRef.current();
      }, delay);
    };
  }, []);

  // Keep the ref in sync with the latest `connect` callback identity so the
  // recursive reconnect path always uses the current implementation.
  useEffect(() => {
    connectRef.current = connect;
  }, [connect]);

  const disconnect = useCallback(() => {
    disconnectedByUserRef.current = true;
    if (reconnectTimerRef.current) {
      clearTimeout(reconnectTimerRef.current);
      reconnectTimerRef.current = null;
    }
    eventSourceRef.current?.close();
    eventSourceRef.current = null;
    setProgress(null);
  }, []);

  useEffect(() => {
    return () => {
      eventSourceRef.current?.close();
      if (reconnectTimerRef.current) {
        clearTimeout(reconnectTimerRef.current);
        reconnectTimerRef.current = null;
      }
    };
  }, []);

  return { progress, connect, disconnect };
}
