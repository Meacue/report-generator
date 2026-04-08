import { useState } from "react";
import {
  useMappings,
  useCreateMapping,
  useDeleteMapping,
} from "../hooks/useMappings";

interface MappingForm {
  gitlab_repo_id: string;
  gitlab_repo_name: string;
  bitrix24_project_id: string;
  bitrix24_project_name: string;
}

const emptyForm: MappingForm = {
  gitlab_repo_id: "",
  gitlab_repo_name: "",
  bitrix24_project_id: "",
  bitrix24_project_name: "",
};

export function ProjectsPage() {
  const { data: mappings, isLoading, error } = useMappings();
  const createMapping = useCreateMapping();
  const deleteMapping = useDeleteMapping();

  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<MappingForm>(emptyForm);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const gitlabId = Number(form.gitlab_repo_id);
    const bitrixId = Number(form.bitrix24_project_id);
    if (isNaN(gitlabId) || isNaN(bitrixId)) return;

    createMapping.mutate(
      {
        gitlab_repo_id: gitlabId,
        gitlab_repo_name: form.gitlab_repo_name,
        bitrix24_project_id: bitrixId,
        bitrix24_project_name: form.bitrix24_project_name,
      },
      {
        onSuccess: () => {
          setForm(emptyForm);
          setShowForm(false);
        },
      },
    );
  };

  const handleChange = (field: keyof MappingForm, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  if (isLoading) return <div>Загрузка...</div>;
  if (error) return <div>Ошибка загрузки маппингов</div>;

  return (
    <div>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "20px",
        }}
      >
        <h1 style={{ marginTop: 0 }}>Проекты (маппинги)</h1>
        <button
          onClick={() => setShowForm(!showForm)}
          style={{
            padding: "8px 16px",
            background: "#646cff",
            color: "#fff",
            border: "none",
            borderRadius: "6px",
            cursor: "pointer",
          }}
        >
          {showForm ? "Отмена" : "Добавить маппинг"}
        </button>
      </div>

      {showForm && (
        <form
          onSubmit={handleSubmit}
          style={{
            background: "#f9f9f9",
            padding: "20px",
            borderRadius: "8px",
            marginBottom: "20px",
            display: "grid",
            gridTemplateColumns: "1fr 1fr",
            gap: "12px",
          }}
        >
          <div>
            <label
              style={{
                display: "block",
                marginBottom: "4px",
                fontSize: "14px",
              }}
            >
              GitLab Repo ID
            </label>
            <input
              type="number"
              value={form.gitlab_repo_id}
              onChange={(e) => handleChange("gitlab_repo_id", e.target.value)}
              required
              style={{
                width: "100%",
                padding: "8px",
                border: "1px solid #ddd",
                borderRadius: "4px",
                boxSizing: "border-box",
              }}
            />
          </div>
          <div>
            <label
              style={{
                display: "block",
                marginBottom: "4px",
                fontSize: "14px",
              }}
            >
              GitLab Repo Name
            </label>
            <input
              type="text"
              value={form.gitlab_repo_name}
              onChange={(e) => handleChange("gitlab_repo_name", e.target.value)}
              required
              style={{
                width: "100%",
                padding: "8px",
                border: "1px solid #ddd",
                borderRadius: "4px",
                boxSizing: "border-box",
              }}
            />
          </div>
          <div>
            <label
              style={{
                display: "block",
                marginBottom: "4px",
                fontSize: "14px",
              }}
            >
              Bitrix24 Project ID
            </label>
            <input
              type="number"
              value={form.bitrix24_project_id}
              onChange={(e) =>
                handleChange("bitrix24_project_id", e.target.value)
              }
              required
              style={{
                width: "100%",
                padding: "8px",
                border: "1px solid #ddd",
                borderRadius: "4px",
                boxSizing: "border-box",
              }}
            />
          </div>
          <div>
            <label
              style={{
                display: "block",
                marginBottom: "4px",
                fontSize: "14px",
              }}
            >
              Bitrix24 Project Name
            </label>
            <input
              type="text"
              value={form.bitrix24_project_name}
              onChange={(e) =>
                handleChange("bitrix24_project_name", e.target.value)
              }
              required
              style={{
                width: "100%",
                padding: "8px",
                border: "1px solid #ddd",
                borderRadius: "4px",
                boxSizing: "border-box",
              }}
            />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <button
              type="submit"
              disabled={createMapping.isPending}
              style={{
                padding: "8px 24px",
                background: createMapping.isPending ? "#999" : "#646cff",
                color: "#fff",
                border: "none",
                borderRadius: "6px",
                cursor: createMapping.isPending ? "not-allowed" : "pointer",
              }}
            >
              {createMapping.isPending ? "Сохранение..." : "Сохранить"}
            </button>
          </div>
        </form>
      )}

      <table
        style={{
          width: "100%",
          borderCollapse: "collapse",
          background: "#fff",
        }}
      >
        <thead>
          <tr style={{ borderBottom: "2px solid #eee", textAlign: "left" }}>
            <th style={{ padding: "12px" }}>ID</th>
            <th style={{ padding: "12px" }}>GitLab репозиторий</th>
            <th style={{ padding: "12px" }}>Bitrix24 проект</th>
            <th style={{ padding: "12px" }}>Создан</th>
            <th style={{ padding: "12px" }}>Действия</th>
          </tr>
        </thead>
        <tbody>
          {mappings && mappings.length > 0 ? (
            mappings.map((m) => (
              <tr key={m.id} style={{ borderBottom: "1px solid #eee" }}>
                <td style={{ padding: "12px" }}>{m.id}</td>
                <td style={{ padding: "12px" }}>
                  {m.gitlab_repo_name}{" "}
                  <span style={{ color: "#999", fontSize: "12px" }}>
                    (ID: {m.gitlab_repo_id})
                  </span>
                </td>
                <td style={{ padding: "12px" }}>
                  {m.bitrix24_project_name}{" "}
                  <span style={{ color: "#999", fontSize: "12px" }}>
                    (ID: {m.bitrix24_project_id})
                  </span>
                </td>
                <td
                  style={{ padding: "12px", fontSize: "13px", color: "#666" }}
                >
                  {m.created_at
                    ? new Date(m.created_at).toLocaleDateString("ru")
                    : "\u2014"}
                </td>
                <td style={{ padding: "12px" }}>
                  <button
                    onClick={() => deleteMapping.mutate(m.id)}
                    disabled={deleteMapping.isPending}
                    style={{
                      padding: "4px 12px",
                      background: "#e53e3e",
                      color: "#fff",
                      border: "none",
                      borderRadius: "4px",
                      cursor: "pointer",
                      fontSize: "13px",
                    }}
                  >
                    Удалить
                  </button>
                </td>
              </tr>
            ))
          ) : (
            <tr>
              <td
                colSpan={5}
                style={{ padding: "24px", textAlign: "center", color: "#999" }}
              >
                Маппинги не найдены
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
