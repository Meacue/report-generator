import { useState } from "react";
import type { ReportDayPreview } from "../../api/reports";
import {
  useUpdateDayNarrative,
  useRegenerateDay,
  useUndoDay,
} from "../../hooks/useReports";

interface Props {
  reportId: number;
  days: ReportDayPreview[];
}

const DAY_NAMES: Record<number, string> = {
  0: "Воскресенье",
  1: "Понедельник",
  2: "Вторник",
  3: "Среда",
  4: "Четверг",
  5: "Пятница",
  6: "Суббота",
};

const MONTH_NAMES: Record<number, string> = {
  0: "января",
  1: "февраля",
  2: "марта",
  3: "апреля",
  4: "мая",
  5: "июня",
  6: "июля",
  7: "августа",
  8: "сентября",
  9: "октября",
  10: "ноября",
  11: "декабря",
};

function formatDateRussian(dateStr: string): string {
  const d = new Date(dateStr + "T00:00:00");
  const dayName = DAY_NAMES[d.getDay()];
  const day = d.getDate();
  const month = MONTH_NAMES[d.getMonth()];
  const year = d.getFullYear();
  return `${dayName}, ${day} ${month} ${year}`;
}

export function ReportDaysTab({ reportId, days }: Props) {
  const updateNarrative = useUpdateDayNarrative(reportId);
  const regenerateDay = useRegenerateDay(reportId);
  const undoDay = useUndoDay(reportId);
  const [editingDate, setEditingDate] = useState<string | null>(null);
  const [editText, setEditText] = useState("");

  const handleEdit = (day: ReportDayPreview) => {
    setEditingDate(day.date);
    setEditText(day.narrative ?? "");
  };

  const handleSave = (date: string) => {
    updateNarrative.mutate(
      { date, narrative: editText },
      {
        onSuccess: () => setEditingDate(null),
      },
    );
  };

  const handleCancel = () => {
    setEditingDate(null);
    setEditText("");
  };

  if (days.length === 0) {
    return (
      <div
        style={{
          padding: "24px",
          textAlign: "center",
          color: "#888",
        }}
      >
        Нет данных за выбранный период
      </div>
    );
  }

  return (
    <div>
      {days.map((day) => (
        <div
          key={day.date}
          style={{
            background: "var(--card-bg)",
            color: "var(--card-fg)",
            borderRadius: "8px",
            padding: "20px",
            marginBottom: "16px",
          }}
        >
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              marginBottom: "12px",
            }}
          >
            <h3 style={{ margin: 0, fontSize: "16px" }}>
              {formatDateRussian(day.date)}
              {day.is_edited && (
                <span
                  style={{
                    marginLeft: "8px",
                    padding: "2px 8px",
                    background: "#ebf4ff",
                    color: "#3182ce",
                    borderRadius: "10px",
                    fontSize: "11px",
                    fontWeight: "normal",
                  }}
                >
                  изменено
                </span>
              )}
            </h3>
          </div>

          {day.source === "bitrix24_fallback" && (
            <div
              style={{
                padding: "10px 14px",
                background: "#fffff0",
                border: "1px solid #fefcbf",
                borderRadius: "6px",
                color: "#975a16",
                marginBottom: "12px",
                fontSize: "13px",
              }}
            >
              Нет коммитов. Описание сгенерировано из активных задач Bitrix24.
            </div>
          )}

          {editingDate === day.date ? (
            <div>
              <textarea
                value={editText}
                onChange={(e) => setEditText(e.target.value)}
                rows={6}
                style={{
                  width: "100%",
                  padding: "10px",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                  boxSizing: "border-box",
                  fontFamily: "inherit",
                  fontSize: "14px",
                  resize: "vertical",
                }}
              />
              <div style={{ marginTop: "8px", display: "flex", gap: "8px" }}>
                <button
                  type="button"
                  onClick={() => handleSave(day.date)}
                  disabled={updateNarrative.isPending}
                  style={{
                    padding: "6px 16px",
                    background: updateNarrative.isPending ? "#999" : "#38a169",
                    color: "#fff",
                    border: "none",
                    borderRadius: "4px",
                    cursor: updateNarrative.isPending
                      ? "not-allowed"
                      : "pointer",
                  }}
                >
                  {updateNarrative.isPending ? "Сохранение..." : "Сохранить"}
                </button>
                <button
                  type="button"
                  onClick={handleCancel}
                  style={{
                    padding: "6px 16px",
                    background: "#eee",
                    color: "#333",
                    border: "none",
                    borderRadius: "4px",
                    cursor: "pointer",
                  }}
                >
                  Отмена
                </button>
              </div>
            </div>
          ) : (
            <div>
              <div
                style={{
                  fontSize: "14px",
                  lineHeight: "1.6",
                  color: day.narrative ? "#333" : "#aaa",
                  whiteSpace: "pre-wrap",
                  marginBottom: "12px",
                }}
              >
                {day.narrative ?? "Нарратив ещё не сгенерирован"}
              </div>
              <div style={{ display: "flex", gap: "8px" }}>
                <button
                  type="button"
                  onClick={() => handleEdit(day)}
                  style={{
                    padding: "4px 12px",
                    background: "#eee",
                    color: "#333",
                    border: "none",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontSize: "13px",
                  }}
                >
                  Редактировать
                </button>
                <button
                  type="button"
                  onClick={() => regenerateDay.mutate(day.date)}
                  disabled={regenerateDay.isPending}
                  style={{
                    padding: "4px 12px",
                    background: "#eee",
                    color: "#333",
                    border: "none",
                    borderRadius: "4px",
                    cursor: regenerateDay.isPending ? "not-allowed" : "pointer",
                    fontSize: "13px",
                  }}
                >
                  {regenerateDay.isPending ? "Генерация..." : "Регенерировать"}
                </button>
                <button
                  type="button"
                  onClick={() => undoDay.mutate(day.date)}
                  disabled={undoDay.isPending}
                  style={{
                    padding: "4px 12px",
                    background: "#eee",
                    color: "#333",
                    border: "none",
                    borderRadius: "4px",
                    cursor: undoDay.isPending ? "not-allowed" : "pointer",
                    fontSize: "13px",
                  }}
                >
                  Отменить
                </button>
              </div>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
