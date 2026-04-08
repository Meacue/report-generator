import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useReportsList, useExportReport } from "../../hooks/useReports";

const REPORT_TYPE_LABELS: Record<string, string> = {
  daily: "Дневной",
  weekly: "Недельный",
  monthly: "Месячный",
  custom: "Произвольный",
};

const STATUS_LABELS: Record<string, string> = {
  draft: "Черновик",
  generated: "Сгенерирован",
  exported: "Экспортирован",
};

const STATUS_COLORS: Record<string, string> = {
  draft: "#ed8936",
  generated: "#38a169",
  exported: "#3182ce",
};

export function ReportsList() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [sortDirection, setSortDirection] = useState<"desc" | "asc">("desc");
  const exportReport = useExportReport();

  const { data, isLoading } = useReportsList({
    page,
    per_page: 15,
    sort_direction: sortDirection,
  });

  const items = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "16px",
        }}
      >
        <h2 style={{ margin: 0 }}>История отчётов</h2>
        <button
          type="button"
          onClick={() =>
            setSortDirection((d) => (d === "desc" ? "asc" : "desc"))
          }
          style={{
            padding: "8px 16px",
            background: "transparent",
            color: "#646cff",
            border: "1px solid #646cff",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          {sortDirection === "desc"
            ? "Сначала новые \u2193"
            : "Сначала старые \u2191"}
        </button>
      </div>

      {isLoading && (
        <div style={{ padding: "24px", color: "#888" }}>
          Загрузка отчётов...
        </div>
      )}

      {!isLoading && items.length === 0 && (
        <div style={{ padding: "24px", color: "#888" }}>
          Нет сгенерированных отчётов
        </div>
      )}

      {!isLoading && items.length > 0 && (
        <>
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
            <thead>
              <tr>
                {["Тип", "Период", "Статус", "Дата создания", "Действия"].map(
                  (header) => (
                    <th
                      key={header}
                      style={{
                        textAlign: "left",
                        padding: "12px 16px",
                        borderBottom: "2px solid #eee",
                        fontSize: "13px",
                        color: "#888",
                        fontWeight: "600",
                      }}
                    >
                      {header}
                    </th>
                  ),
                )}
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr
                  key={item.id}
                  onClick={() => navigate(`/reports/${item.id}`)}
                  style={{
                    borderBottom: "1px solid #f0f0f0",
                    cursor: "pointer",
                  }}
                >
                  <td style={{ padding: "12px 16px", fontSize: "14px" }}>
                    {REPORT_TYPE_LABELS[item.type] ?? item.type}
                  </td>
                  <td style={{ padding: "12px 16px", fontSize: "14px" }}>
                    {item.date_from} &mdash; {item.date_to}
                  </td>
                  <td style={{ padding: "12px 16px", fontSize: "14px" }}>
                    <span
                      style={{
                        padding: "2px 10px",
                        background: STATUS_COLORS[item.status] ?? "#999",
                        color: "#fff",
                        borderRadius: "12px",
                        fontSize: "12px",
                        fontWeight: "bold",
                      }}
                    >
                      {STATUS_LABELS[item.status] ?? item.status}
                    </span>
                  </td>
                  <td style={{ padding: "12px 16px", fontSize: "14px" }}>
                    {new Date(item.created_at).toLocaleDateString("ru-RU")}
                  </td>
                  <td style={{ padding: "12px 16px", fontSize: "14px" }}>
                    <div style={{ display: "flex", gap: "8px" }}>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          exportReport.mutate(item.id);
                        }}
                        disabled={item.status === "draft"}
                        style={{
                          padding: "6px 12px",
                          background:
                            item.status === "draft" ? "#ccc" : "#38a169",
                          color: "#fff",
                          border: "none",
                          borderRadius: "4px",
                          cursor:
                            item.status === "draft" ? "not-allowed" : "pointer",
                          fontSize: "13px",
                        }}
                      >
                        Скачать
                      </button>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          navigate(`/reports/${item.id}`);
                        }}
                        style={{
                          padding: "6px 12px",
                          background: "#646cff",
                          color: "#fff",
                          border: "none",
                          borderRadius: "4px",
                          cursor: "pointer",
                          fontSize: "13px",
                        }}
                      >
                        Открыть
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {meta && meta.last_page > 1 && (
            <div
              style={{
                display: "flex",
                justifyContent: "center",
                alignItems: "center",
                gap: "16px",
                marginTop: "16px",
              }}
            >
              <button
                type="button"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                style={{
                  padding: "6px 12px",
                  background: page <= 1 ? "#eee" : "#fff",
                  color: page <= 1 ? "#999" : "#333",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  cursor: page <= 1 ? "not-allowed" : "pointer",
                }}
              >
                &larr; Назад
              </button>
              <span style={{ fontSize: "14px", color: "#666" }}>
                Страница {meta.current_page} из {meta.last_page}
              </span>
              <button
                type="button"
                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                disabled={page >= meta.last_page}
                style={{
                  padding: "6px 12px",
                  background: page >= meta.last_page ? "#eee" : "#fff",
                  color: page >= meta.last_page ? "#999" : "#333",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  cursor: page >= meta.last_page ? "not-allowed" : "pointer",
                }}
              >
                Вперёд &rarr;
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
