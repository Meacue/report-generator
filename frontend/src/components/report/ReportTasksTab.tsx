import { useState } from "react";
import type { ReportTaskPreview } from "../../api/reports";
import {
  useUpdateTaskNarrative,
  useRegenerateTask,
  useUndoTask,
} from "../../hooks/useReports";
import { formatDuration } from "../../utils/formatDuration";
import { displayTaskTitle } from "../../utils/taskTitle";

interface Props {
  reportId: number;
  tasks: ReportTaskPreview[];
}

interface GroupedTasks {
  projectName: string;
  tasks: ReportTaskPreview[];
}

function groupByProject(tasks: ReportTaskPreview[]): GroupedTasks[] {
  const groups = new Map<string, ReportTaskPreview[]>();
  for (const task of tasks) {
    const key = task.project_name || "Без проекта";
    const list = groups.get(key) ?? [];
    list.push(task);
    groups.set(key, list);
  }
  return Array.from(groups.entries()).map(([projectName, items]) => ({
    projectName,
    tasks: items,
  }));
}

export function ReportTasksTab({ reportId, tasks }: Props) {
  const updateNarrative = useUpdateTaskNarrative(reportId);
  const regenerateTask = useRegenerateTask(reportId);
  const undoTask = useUndoTask(reportId);
  const [editingTaskId, setEditingTaskId] = useState<number | null>(null);
  const [editText, setEditText] = useState("");

  const handleEdit = (task: ReportTaskPreview) => {
    setEditingTaskId(task.id);
    setEditText(task.narrative ?? "");
  };

  const handleSave = (taskId: number) => {
    updateNarrative.mutate(
      { taskId, narrative: editText },
      {
        onSuccess: () => setEditingTaskId(null),
      },
    );
  };

  const handleCancel = () => {
    setEditingTaskId(null);
    setEditText("");
  };

  if (tasks.length === 0) {
    return (
      <div
        style={{
          padding: "24px",
          textAlign: "center",
          color: "#888",
        }}
      >
        Нет задач за выбранный период
      </div>
    );
  }

  const grouped = groupByProject(tasks);

  return (
    <div>
      {grouped.map((group) => (
        <div key={group.projectName} style={{ marginBottom: "24px" }}>
          <h3
            style={{
              fontSize: "16px",
              color: "#444",
              marginBottom: "12px",
              borderBottom: "1px solid #eee",
              paddingBottom: "8px",
            }}
          >
            {group.projectName}
          </h3>

          {group.tasks.map((task) => (
            <div
              key={task.id}
              style={{
                background: "var(--card-bg)",
                color: "var(--card-fg)",
                borderRadius: "8px",
                padding: "16px 20px",
                marginBottom: "12px",
              }}
            >
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  marginBottom: "8px",
                }}
              >
                <div
                  style={{
                    fontWeight: "bold",
                    fontSize: "14px",
                    display: "flex",
                    alignItems: "center",
                    flexWrap: "wrap",
                    gap: "6px",
                  }}
                >
                  {task.task ? (
                    <>
                      <span style={{ color: "#888", fontWeight: "normal" }}>
                        B24-{task.task.bitrix24_task_id}
                      </span>{" "}
                      {displayTaskTitle(
                        task.task.title,
                        task.task.bitrix24_task_id,
                      )}
                    </>
                  ) : (
                    <span style={{ color: "#888" }}>
                      Задача без привязки к Bitrix24
                    </span>
                  )}
                  {task.task &&
                    task.task.seconds_tracked !== null &&
                    task.task.seconds_tracked > 0 && (
                      <span
                        title="Отслежено времени в Bitrix24 за период отчёта"
                        aria-label={`Отслежено ${formatDuration(
                          task.task.seconds_tracked,
                        )}`}
                        style={{
                          padding: "2px 8px",
                          background: "#f0fff4",
                          color: "#276749",
                          borderRadius: "10px",
                          fontSize: "11px",
                          fontWeight: "normal",
                          border: "1px solid #c6f6d5",
                        }}
                      >
                        {formatDuration(task.task.seconds_tracked)}
                      </span>
                    )}
                  {task.is_edited && (
                    <span
                      style={{
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
                </div>
              </div>

              {editingTaskId === task.id ? (
                <div>
                  <textarea
                    value={editText}
                    onChange={(e) => setEditText(e.target.value)}
                    rows={4}
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
                  <div
                    style={{
                      marginTop: "8px",
                      display: "flex",
                      gap: "8px",
                    }}
                  >
                    <button
                      type="button"
                      onClick={() => handleSave(task.id)}
                      disabled={updateNarrative.isPending}
                      style={{
                        padding: "6px 16px",
                        background: updateNarrative.isPending
                          ? "#999"
                          : "#38a169",
                        color: "#fff",
                        border: "none",
                        borderRadius: "4px",
                        cursor: updateNarrative.isPending
                          ? "not-allowed"
                          : "pointer",
                      }}
                    >
                      {updateNarrative.isPending
                        ? "Сохранение..."
                        : "Сохранить"}
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
                      color: task.narrative ? "#333" : "#aaa",
                      whiteSpace: "pre-wrap",
                      marginBottom: "10px",
                    }}
                  >
                    {task.narrative ?? "Нарратив ещё не сгенерирован"}
                  </div>
                  <div style={{ display: "flex", gap: "8px" }}>
                    <button
                      type="button"
                      onClick={() => handleEdit(task)}
                      style={{
                        padding: "4px 12px",
                        background: "#eee",
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
                      onClick={() => regenerateTask.mutate(task.id)}
                      disabled={regenerateTask.isPending}
                      style={{
                        padding: "4px 12px",
                        background: "#eee",
                        border: "none",
                        borderRadius: "4px",
                        cursor: regenerateTask.isPending
                          ? "not-allowed"
                          : "pointer",
                        fontSize: "13px",
                      }}
                    >
                      {regenerateTask.isPending
                        ? "Генерация..."
                        : "Регенерировать"}
                    </button>
                    <button
                      type="button"
                      onClick={() => undoTask.mutate(task.id)}
                      disabled={undoTask.isPending}
                      style={{
                        padding: "4px 12px",
                        background: "#eee",
                        border: "none",
                        borderRadius: "4px",
                        cursor: undoTask.isPending ? "not-allowed" : "pointer",
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
      ))}
    </div>
  );
}
