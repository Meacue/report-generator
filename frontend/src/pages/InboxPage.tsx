import { useState } from "react";
import { InboxCard } from "../components/inbox/InboxCard";
import {
  useInbox,
  useAssignBranch,
  useIgnoreBranch,
  useCreateInboxTask,
} from "../hooks/useInbox";
import type { InboxParams } from "../api/inbox";

type FilterType = "all" | "probable" | "none";

const filterOptions: { value: FilterType; label: string }[] = [
  { value: "all", label: "Все" },
  { value: "probable", label: "Вероятные" },
  { value: "none", label: "Нет совпадений" },
];

export function InboxPage() {
  const [filter, setFilter] = useState<FilterType>("all");
  const [page, setPage] = useState(1);
  const [sortDirection, setSortDirection] = useState<"asc" | "desc">("desc");

  const params: InboxParams = {
    page,
    per_page: 20,
    filter,
    sort_direction: sortDirection,
  };
  const { data, isLoading, isError } = useInbox(params);

  const assignMutation = useAssignBranch();
  const ignoreMutation = useIgnoreBranch();
  const createTaskMutation = useCreateInboxTask();

  const isMutating =
    assignMutation.isPending ||
    ignoreMutation.isPending ||
    createTaskMutation.isPending;

  const handleFilterChange = (newFilter: FilterType) => {
    setFilter(newFilter);
    setPage(1);
  };

  const items = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div style={{ maxWidth: "800px", margin: "0 auto", padding: "24px" }}>
      <h1 style={{ fontSize: "24px", marginBottom: "16px" }}>Входящие</h1>

      <div
        style={{
          display: "flex",
          gap: "8px",
          marginBottom: "20px",
        }}
      >
        {filterOptions.map((opt) => (
          <button
            key={opt.value}
            onClick={() => handleFilterChange(opt.value)}
            style={{
              padding: "8px 16px",
              borderRadius: "6px",
              border: "1px solid #d1d5db",
              background: filter === opt.value ? "#3b82f6" : "#fff",
              color: filter === opt.value ? "#fff" : "#374151",
              cursor: "pointer",
              fontWeight: filter === opt.value ? 600 : 400,
            }}
          >
            {opt.label}
          </button>
        ))}
        <button
          onClick={() => {
            setSortDirection((prev) => (prev === "desc" ? "asc" : "desc"));
            setPage(1);
          }}
          style={{
            marginLeft: "auto",
            padding: "8px 16px",
            borderRadius: "6px",
            border: "1px solid #9ca3af",
            background: "#fff",
            color: "#374151",
            cursor: "pointer",
            fontWeight: 400,
          }}
        >
          {sortDirection === "desc"
            ? "Сначала новые \u2193"
            : "Сначала старые \u2191"}
        </button>
      </div>

      {isLoading && (
        <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
          Загрузка...
        </div>
      )}

      {isError && (
        <div style={{ textAlign: "center", padding: "40px", color: "#ef4444" }}>
          Ошибка загрузки данных
        </div>
      )}

      {!isLoading && !isError && items.length === 0 && (
        <div style={{ textAlign: "center", padding: "40px", color: "#10b981" }}>
          Все ветки привязаны!
        </div>
      )}

      {items.map((item) => (
        <InboxCard
          key={item.id}
          item={item}
          onAssign={(branchId, taskId) =>
            assignMutation.mutate({ branchId, taskId })
          }
          onIgnore={(branchId) => ignoreMutation.mutate(branchId)}
          onCreateTask={(branchId, title) =>
            createTaskMutation.mutate({ branchId, title })
          }
          isLoading={isMutating}
        />
      ))}

      {meta && meta.last_page > 1 && (
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            gap: "12px",
            marginTop: "20px",
          }}
        >
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
            style={{
              padding: "8px 16px",
              borderRadius: "6px",
              border: "1px solid #d1d5db",
              background: page <= 1 ? "#f3f4f6" : "#fff",
              cursor: page <= 1 ? "default" : "pointer",
            }}
          >
            Назад
          </button>
          <span
            style={{
              padding: "8px 0",
              fontSize: "14px",
              color: "#666",
            }}
          >
            {page} / {meta.last_page}
          </span>
          <button
            onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
            disabled={page >= meta.last_page}
            style={{
              padding: "8px 16px",
              borderRadius: "6px",
              border: "1px solid #d1d5db",
              background: page >= meta.last_page ? "#f3f4f6" : "#fff",
              cursor: page >= meta.last_page ? "default" : "pointer",
            }}
          >
            Далее
          </button>
        </div>
      )}
    </div>
  );
}
