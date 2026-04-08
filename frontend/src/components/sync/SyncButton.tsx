import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { AxiosError } from "axios";
import { useSyncStatus, useTriggerSync } from "../../hooks/useSync";
import { useSyncSSE } from "../../hooks/useSyncSSE";

function stepLabel(step?: string | null): string {
  switch (step) {
    case "gitlab":
      return "GitLab";
    case "bitrix24":
      return "Bitrix24";
    case "matching":
      return "Сопоставление";
    default:
      return "Подготовка";
  }
}

export function SyncButton() {
  const { data: status, isLoading } = useSyncStatus();
  const triggerSync = useTriggerSync();
  const { progress, connect } = useSyncSSE();
  const queryClient = useQueryClient();

  const handleSync = async () => {
    try {
      await triggerSync.mutateAsync();
      connect();
    } catch (e) {
      if (e instanceof AxiosError && e.response?.status === 409) {
        connect();
      }
    }
  };

  useEffect(() => {
    if (progress?.status === "success" || progress?.status === "failed") {
      queryClient.invalidateQueries({ queryKey: ["sync-status"] });
    }
  }, [progress?.status, queryClient]);

  const isRunning =
    progress?.status === "in_progress" || status?.status === "in_progress";

  return (
    <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
      <button
        onClick={handleSync}
        disabled={triggerSync.isPending || isRunning}
        style={{
          padding: "8px 16px",
          background: triggerSync.isPending || isRunning ? "#999" : "#646cff",
          color: "#fff",
          border: "none",
          borderRadius: "6px",
          cursor:
            triggerSync.isPending || isRunning ? "not-allowed" : "pointer",
        }}
      >
        {isRunning
          ? `Синхронизация: ${stepLabel(progress?.current_step)}...`
          : "Синхронизировать"}
      </button>

      {progress?.status === "failed" && (
        <span style={{ fontSize: "14px", color: "#e53e3e" }}>
          Ошибка: {progress.error_message}
        </span>
      )}

      {!isLoading && status && !isRunning && progress?.status !== "failed" && (
        <span style={{ fontSize: "14px", color: "#666" }}>
          {status.status === "never"
            ? "Ещё не синхронизировано"
            : `Последняя: ${status.last_sync_at ? new Date(status.last_sync_at).toLocaleString("ru") : "\u2014"} (${status.items_synced} элементов)`}
        </span>
      )}
    </div>
  );
}
