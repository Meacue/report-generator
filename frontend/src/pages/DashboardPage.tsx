import { SyncButton } from "../components/sync/SyncButton";
import { useSyncStatus } from "../hooks/useSync";

export function DashboardPage() {
  const { data: status, isLoading } = useSyncStatus();

  return (
    <div>
      <h1 style={{ marginTop: 0 }}>Панель управления</h1>

      <section
        style={{
          background: "#f9f9f9",
          borderRadius: "8px",
          padding: "20px",
          marginBottom: "24px",
        }}
      >
        <h2 style={{ marginTop: 0, fontSize: "18px" }}>Синхронизация</h2>
        <SyncButton />

        {!isLoading && status && status.status !== "never" && (
          <div style={{ marginTop: "16px", fontSize: "14px", color: "#555" }}>
            <p>
              <strong>Статус:</strong> {status.status}
            </p>
            {status.source && (
              <p>
                <strong>Источник:</strong> {status.source}
              </p>
            )}
            <p>
              <strong>Синхронизировано элементов:</strong> {status.items_synced}
            </p>
          </div>
        )}
      </section>
    </div>
  );
}
