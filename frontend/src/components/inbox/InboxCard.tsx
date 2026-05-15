import { useState } from "react";
import type { InboxItem } from "../../types/api";

interface InboxCardProps {
  item: InboxItem;
  onAssign: (branchId: number, taskId: number) => void;
  onIgnore: (branchId: number) => void;
  onCreateTask: (branchId: number, title: string) => void;
  isLoading: boolean;
}

export function InboxCard({
  item,
  onAssign,
  onIgnore,
  onCreateTask,
  isLoading,
}: InboxCardProps) {
  const [taskIdInput, setTaskIdInput] = useState("");
  const [newTaskTitle, setNewTaskTitle] = useState("");
  const [showCreateTask, setShowCreateTask] = useState(false);

  const confidenceColor =
    {
      probable: "#f59e0b",
      none: "#ef4444",
    }[item.confidence_level ?? "none"] ?? "#6b7280";

  const confidenceLabel =
    {
      probable: "Вероятное совпадение",
      none: "Нет совпадений",
    }[item.confidence_level ?? "none"] ?? "Не определено";

  return (
    <div
      style={{
        border: "1px solid #e5e7eb",
        borderRadius: "8px",
        padding: "16px",
        marginBottom: "12px",
        background: "var(--card-bg)",
        color: "var(--card-fg)",
      }}
    >
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "start",
        }}
      >
        <div>
          <h3 style={{ margin: "0 0 4px", fontSize: "15px" }}>
            {item.branch_name}
          </h3>
          <div style={{ fontSize: "13px", color: "#666" }}>
            {item.parsed_info && <span>Info: {item.parsed_info} | </span>}
            {item.parsed_task_number && (
              <span>Task #: {item.parsed_task_number} | </span>
            )}
            {item.parsed_date && <span>Дата: {item.parsed_date} | </span>}
            <span>Коммитов: {item.commits_count}</span>
          </div>
          {item.last_commit && (
            <div style={{ fontSize: "12px", color: "#999", marginTop: "4px" }}>
              Последний коммит: {item.last_commit}
            </div>
          )}
        </div>
        <span
          style={{
            padding: "2px 8px",
            borderRadius: "12px",
            fontSize: "12px",
            color: "#fff",
            background: confidenceColor,
          }}
        >
          {confidenceLabel}
        </span>
      </div>

      <div
        style={{
          marginTop: "12px",
          display: "flex",
          gap: "8px",
          alignItems: "center",
          flexWrap: "wrap",
        }}
      >
        <input
          type="number"
          placeholder="Номер задачи Bitrix24"
          value={taskIdInput}
          onChange={(e) => setTaskIdInput(e.target.value)}
          style={{
            width: "100px",
            padding: "6px 8px",
            borderRadius: "4px",
            border: "1px solid #ccc",
          }}
        />
        <button
          onClick={() => {
            if (taskIdInput) onAssign(item.id, Number(taskIdInput));
          }}
          disabled={isLoading || !taskIdInput}
          style={{
            padding: "6px 12px",
            background: "#10b981",
            color: "#fff",
            border: "none",
            borderRadius: "4px",
            cursor: "pointer",
          }}
        >
          Привязать
        </button>
        <button
          onClick={() => onIgnore(item.id)}
          disabled={isLoading}
          style={{
            padding: "6px 12px",
            background: "#6b7280",
            color: "#fff",
            border: "none",
            borderRadius: "4px",
            cursor: "pointer",
          }}
        >
          Игнорировать
        </button>
        <button
          onClick={() => setShowCreateTask(!showCreateTask)}
          style={{
            padding: "6px 12px",
            background: "#3b82f6",
            color: "#fff",
            border: "none",
            borderRadius: "4px",
            cursor: "pointer",
          }}
        >
          Создать задачу
        </button>
      </div>

      {showCreateTask && (
        <div style={{ marginTop: "8px", display: "flex", gap: "8px" }}>
          <input
            type="text"
            placeholder="Название задачи"
            value={newTaskTitle}
            onChange={(e) => setNewTaskTitle(e.target.value)}
            style={{
              flex: 1,
              padding: "6px 8px",
              borderRadius: "4px",
              border: "1px solid #ccc",
            }}
          />
          <button
            onClick={() => {
              if (newTaskTitle) {
                onCreateTask(item.id, newTaskTitle);
                setNewTaskTitle("");
                setShowCreateTask(false);
              }
            }}
            disabled={isLoading || !newTaskTitle}
            style={{
              padding: "6px 12px",
              background: "#3b82f6",
              color: "#fff",
              border: "none",
              borderRadius: "4px",
              cursor: "pointer",
            }}
          >
            Создать
          </button>
        </div>
      )}
    </div>
  );
}
