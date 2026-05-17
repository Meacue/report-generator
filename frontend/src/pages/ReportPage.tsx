import { useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  useGenerateReport,
  useReportPreview,
  useExportReport,
  useExportPrompt,
} from "../hooks/useReports";
import type { GenerateReportParams } from "../api/reports";
import { ApiError } from "../api/client";
import type { LlmConfigErrorData } from "../types/api";
import { ReportDaysTab } from "../components/report/ReportDaysTab";
import { ReportTasksTab } from "../components/report/ReportTasksTab";
import { ReportsList } from "../components/report/ReportsList";

type ReportType = "daily" | "weekly" | "monthly" | "custom";
type TabKey = "days" | "tasks";

const REPORT_TYPE_LABELS: Record<ReportType, string> = {
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

function getMonday(date: Date): string {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  return d.toISOString().slice(0, 10);
}

function getFriday(date: Date): string {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1) + 4;
  d.setDate(diff);
  return d.toISOString().slice(0, 10);
}

function getMonthStart(date: Date): string {
  return new Date(date.getFullYear(), date.getMonth(), 1)
    .toISOString()
    .slice(0, 10);
}

function getMonthEnd(date: Date): string {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
    .toISOString()
    .slice(0, 10);
}

function todayStr(): string {
  return new Date().toISOString().slice(0, 10);
}

function extractLlmConfigError(error: unknown): LlmConfigErrorData | null {
  if (!(error instanceof ApiError) || error.status !== 422) {
    return null;
  }
  const data = error.data;
  if (data === null || typeof data !== "object") {
    return null;
  }
  const violations = (data as { violations?: unknown }).violations;
  if (!Array.isArray(violations)) {
    return null;
  }
  if (!violations.every((v): v is string => typeof v === "string")) {
    return null;
  }
  return data as LlmConfigErrorData;
}

export function ReportPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const reportId = id ? Number(id) : null;

  // Generation form state
  const [reportType, setReportType] = useState<ReportType>("daily");
  const [dateFrom, setDateFrom] = useState(todayStr());
  const [dateTo, setDateTo] = useState(todayStr());
  const [activeTab, setActiveTab] = useState<TabKey>("days");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const generateReport = useGenerateReport();
  const exportReport = useExportReport();
  const exportPrompt = useExportPrompt();
  const {
    data: report,
    isLoading: previewLoading,
    error: previewError,
  } = useReportPreview(reportId);

  const handleTypeChange = (type: ReportType) => {
    setReportType(type);
    const now = new Date();
    if (type === "daily") {
      const today = todayStr();
      setDateFrom(today);
      setDateTo(today);
    } else if (type === "weekly") {
      setDateFrom(getMonday(now));
      setDateTo(getFriday(now));
    } else if (type === "monthly") {
      setDateFrom(getMonthStart(now));
      setDateTo(getMonthEnd(now));
    }
  };

  const handleGenerate = () => {
    setErrorMessage(null);
    const params: GenerateReportParams = {
      type: reportType,
      date_from: dateFrom,
      date_to: dateTo,
    };
    generateReport.mutate(params, {
      onSuccess: (data) => {
        if (data?.id) {
          navigate(`/reports/${data.id}`);
        }
      },
      onError: (error) => {
        const llmConfigError = extractLlmConfigError(error);
        if (llmConfigError) {
          const violations = llmConfigError.violations
            .map((v) => `• ${v}`)
            .join("\n");
          setErrorMessage(
            `Конфигурация LLM некорректна:\n${violations}\n\nОткройте Настройки и заполните необходимые поля.`,
          );
          return;
        }
        setErrorMessage(
          "Не удалось сгенерировать отчёт. Проверьте наличие данных за выбранный период.",
        );
      },
    });
  };

  const handleExport = () => {
    if (reportId === null) return;
    exportReport.mutate(reportId);
  };

  const handleExportPrompt = () => {
    if (reportId === null) return;
    exportPrompt.mutate(reportId);
  };

  // Show generation form when no report ID in URL
  if (reportId === null) {
    return (
      <div>
        <h1 style={{ marginTop: 0 }}>Генерация отчёта</h1>

        <div
          style={{
            maxWidth: "600px",
            background: "var(--card-bg)",
            color: "var(--card-fg)",
            padding: "24px",
            borderRadius: "8px",
          }}
        >
          <div style={{ marginBottom: "16px" }}>
            <label
              style={{
                display: "block",
                marginBottom: "4px",
                fontSize: "14px",
                fontWeight: "bold",
              }}
            >
              Тип отчёта
            </label>
            <div style={{ display: "flex", gap: "8px" }}>
              {(
                Object.entries(REPORT_TYPE_LABELS) as [ReportType, string][]
              ).map(([value, label]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => handleTypeChange(value)}
                  style={{
                    padding: "8px 16px",
                    border:
                      reportType === value
                        ? "2px solid #646cff"
                        : "1px solid #ddd",
                    borderRadius: "6px",
                    background: reportType === value ? "#eef" : "#fff",
                    cursor: "pointer",
                    fontWeight: reportType === value ? "bold" : "normal",
                    color: reportType === value ? "#646cff" : "#333",
                  }}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          {reportType === "daily" && (
            <div style={{ marginBottom: "16px" }}>
              <label
                style={{
                  display: "block",
                  marginBottom: "4px",
                  fontSize: "14px",
                  fontWeight: "bold",
                }}
              >
                Дата
              </label>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => {
                  setDateFrom(e.target.value);
                  setDateTo(e.target.value);
                }}
                style={{
                  width: "100%",
                  padding: "8px",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  boxSizing: "border-box",
                }}
              />
            </div>
          )}

          {reportType === "weekly" && (
            <div style={{ marginBottom: "16px" }}>
              <label
                style={{
                  display: "block",
                  marginBottom: "4px",
                  fontSize: "14px",
                  fontWeight: "bold",
                }}
              >
                Неделя (выберите любой день недели)
              </label>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => {
                  const d = new Date(e.target.value);
                  setDateFrom(getMonday(d));
                  setDateTo(getFriday(d));
                }}
                style={{
                  width: "100%",
                  padding: "8px",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  boxSizing: "border-box",
                }}
              />
              <div
                style={{ marginTop: "4px", fontSize: "13px", color: "#888" }}
              >
                Период: {dateFrom} &mdash; {dateTo}
              </div>
            </div>
          )}

          {reportType === "monthly" && (
            <div style={{ marginBottom: "16px" }}>
              <label
                style={{
                  display: "block",
                  marginBottom: "4px",
                  fontSize: "14px",
                  fontWeight: "bold",
                }}
              >
                Месяц
              </label>
              <input
                type="month"
                value={dateFrom.slice(0, 7)}
                onChange={(e) => {
                  const d = new Date(e.target.value + "-01");
                  setDateFrom(getMonthStart(d));
                  setDateTo(getMonthEnd(d));
                }}
                style={{
                  width: "100%",
                  padding: "8px",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  boxSizing: "border-box",
                }}
              />
            </div>
          )}

          {reportType === "custom" && (
            <div
              style={{
                display: "flex",
                gap: "16px",
                marginBottom: "16px",
              }}
            >
              <div style={{ flex: 1 }}>
                <label
                  style={{
                    display: "block",
                    marginBottom: "4px",
                    fontSize: "14px",
                    fontWeight: "bold",
                  }}
                >
                  Дата начала
                </label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "8px",
                    border: "1px solid #ddd",
                    borderRadius: "4px",
                    boxSizing: "border-box",
                  }}
                />
              </div>
              <div style={{ flex: 1 }}>
                <label
                  style={{
                    display: "block",
                    marginBottom: "4px",
                    fontSize: "14px",
                    fontWeight: "bold",
                  }}
                >
                  Дата окончания
                </label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "8px",
                    border: "1px solid #ddd",
                    borderRadius: "4px",
                    boxSizing: "border-box",
                  }}
                />
              </div>
            </div>
          )}

          {errorMessage && (
            <div
              style={{
                padding: "12px",
                background: "#fff5f5",
                border: "1px solid #feb2b2",
                borderRadius: "6px",
                color: "#c53030",
                marginBottom: "16px",
                fontSize: "14px",
                whiteSpace: "pre-line",
              }}
            >
              {errorMessage}
            </div>
          )}

          <button
            type="button"
            onClick={handleGenerate}
            disabled={generateReport.isPending}
            style={{
              padding: "10px 24px",
              background: generateReport.isPending ? "#999" : "#646cff",
              color: "#fff",
              border: "none",
              borderRadius: "6px",
              cursor: generateReport.isPending ? "not-allowed" : "pointer",
              fontSize: "15px",
            }}
          >
            {generateReport.isPending ? "Генерация..." : "Сгенерировать отчёт"}
          </button>
        </div>

        <div style={{ marginTop: "32px" }}>
          <ReportsList />
        </div>
      </div>
    );
  }

  // Report preview
  if (previewLoading) {
    return <div>Загрузка отчёта...</div>;
  }

  if (previewError || !report) {
    return (
      <div>
        <h1 style={{ marginTop: 0 }}>Ошибка</h1>
        <p style={{ color: "#c53030" }}>
          Не удалось загрузить отчёт. Попробуйте обновить страницу.
        </p>
        <button
          type="button"
          onClick={() => navigate("/reports")}
          style={{
            padding: "8px 16px",
            background: "#646cff",
            color: "#fff",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          Назад к генерации
        </button>
      </div>
    );
  }

  const reportTypeLabel =
    REPORT_TYPE_LABELS[report.type as ReportType] ?? report.type;
  const statusLabel = STATUS_LABELS[report.status] ?? report.status;
  const statusColor = STATUS_COLORS[report.status] ?? "#999";

  return (
    <div>
      {/* Header */}
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "24px",
        }}
      >
        <div>
          <h1 style={{ marginTop: 0, marginBottom: "8px" }}>
            {reportTypeLabel} отчёт
          </h1>
          <div style={{ fontSize: "14px", color: "#666" }}>
            {report.date_from} &mdash; {report.date_to}
            <span
              style={{
                marginLeft: "12px",
                padding: "2px 10px",
                background: statusColor,
                color: "#fff",
                borderRadius: "12px",
                fontSize: "12px",
                fontWeight: "bold",
              }}
            >
              {statusLabel}
            </span>
          </div>
        </div>

        <div style={{ display: "flex", gap: "8px" }}>
          <button
            type="button"
            onClick={() => navigate("/reports")}
            style={{
              padding: "8px 16px",
              background: "#eee",
              color: "#333",
              border: "none",
              borderRadius: "6px",
              cursor: "pointer",
            }}
          >
            Новый отчёт
          </button>
          <button
            type="button"
            onClick={handleExport}
            disabled={exportReport.isPending}
            style={{
              padding: "8px 16px",
              background: exportReport.isPending ? "#999" : "#38a169",
              color: "#fff",
              border: "none",
              borderRadius: "6px",
              cursor: exportReport.isPending ? "not-allowed" : "pointer",
            }}
          >
            {exportReport.isPending ? "Экспорт..." : "Экспорт в Word"}
          </button>
          <button
            type="button"
            onClick={handleExportPrompt}
            disabled={exportPrompt.isPending}
            title="Скачать текстовый файл с данными отчёта для генерации нарратива в AI-чатботах"
            style={{
              padding: "8px 16px",
              background: "transparent",
              color: exportPrompt.isPending ? "#999" : "#646cff",
              border: exportPrompt.isPending
                ? "1px solid #999"
                : "1px solid #646cff",
              borderRadius: "6px",
              cursor: exportPrompt.isPending ? "not-allowed" : "pointer",
            }}
          >
            {exportPrompt.isPending ? "Экспорт..." : "Экспорт промпта для ИИ"}
          </button>
        </div>
      </div>

      {/* Tab navigation */}
      <div
        style={{
          display: "flex",
          gap: "0",
          marginBottom: "24px",
          borderBottom: "2px solid #eee",
        }}
      >
        <button
          type="button"
          onClick={() => setActiveTab("days")}
          style={{
            padding: "10px 24px",
            background: "transparent",
            border: "none",
            borderBottom:
              activeTab === "days"
                ? "2px solid #646cff"
                : "2px solid transparent",
            color: activeTab === "days" ? "#646cff" : "#666",
            fontWeight: activeTab === "days" ? "bold" : "normal",
            cursor: "pointer",
            marginBottom: "-2px",
            fontSize: "15px",
          }}
        >
          По дням
        </button>
        <button
          type="button"
          onClick={() => setActiveTab("tasks")}
          style={{
            padding: "10px 24px",
            background: "transparent",
            border: "none",
            borderBottom:
              activeTab === "tasks"
                ? "2px solid #646cff"
                : "2px solid transparent",
            color: activeTab === "tasks" ? "#646cff" : "#666",
            fontWeight: activeTab === "tasks" ? "bold" : "normal",
            cursor: "pointer",
            marginBottom: "-2px",
            fontSize: "15px",
          }}
        >
          По задачам
        </button>
      </div>

      {/* Tab content */}
      {activeTab === "days" && (
        <ReportDaysTab reportId={report.id} days={report.days} />
      )}
      {activeTab === "tasks" && (
        <ReportTasksTab reportId={report.id} tasks={report.tasks} />
      )}
    </div>
  );
}
