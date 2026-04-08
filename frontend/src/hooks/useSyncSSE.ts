import { useState, useEffect, useCallback, useRef } from "react";

interface SyncProgress {
  status: "in_progress" | "success" | "failed";
  current_step?: "gitlab" | "bitrix24" | "matching" | null;
  error_message?: string | null;
}

export function useSyncSSE() {
  const [progress, setProgress] = useState<SyncProgress | null>(null);
  const eventSourceRef = useRef<EventSource | null>(null);

  const connect = useCallback(() => {
    eventSourceRef.current?.close();

    const es = new EventSource("/api/sync/stream");
    eventSourceRef.current = es;

    const handleEvent = (event: MessageEvent) => {
      const data: SyncProgress = JSON.parse(event.data);
      setProgress(data);

      if (data.status === "success" || data.status === "failed") {
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
    };
  }, []);

  const disconnect = useCallback(() => {
    eventSourceRef.current?.close();
    eventSourceRef.current = null;
    setProgress(null);
  }, []);

  useEffect(() => {
    return () => {
      eventSourceRef.current?.close();
    };
  }, []);

  return { progress, connect, disconnect };
}
