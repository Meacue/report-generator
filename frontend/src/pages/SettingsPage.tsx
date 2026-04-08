import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { settingsApi } from "../api/settings";
import type { SettingsInput } from "../types/api";

export function SettingsPage() {
  const queryClient = useQueryClient();
  const { data: settings, isLoading } = useQuery({
    queryKey: ["settings"],
    queryFn: settingsApi.get,
  });

  const updateSettings = useMutation({
    mutationFn: (data: SettingsInput) => settingsApi.update(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      setSaveMessage("Настройки сохранены");
      setTimeout(() => setSaveMessage(null), 3000);
    },
  });

  const [form, setForm] = useState<SettingsInput>({});
  const [saveMessage, setSaveMessage] = useState<string | null>(null);

  useEffect(() => {
    if (settings) {
      setForm({
        gitlab_username: settings.gitlab_username ?? "",
        gitlab_email: settings.gitlab_email ?? "",
        bitrix24_user_id: settings.bitrix24_user_id ?? "",
        llm_provider: settings.llm_provider,
        llm_system_prompt: settings.llm_system_prompt ?? "",
        enriched_prompt_enabled: settings.enriched_prompt_enabled,
        developer_name: settings.developer_name ?? "",
        developer_position: settings.developer_position ?? "",
        sync_schedule_time: settings.sync_schedule_time,
      });
    }
  }, [settings]);

  const handleChange = (field: keyof SettingsInput, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Only send non-empty password fields
    const payload: SettingsInput = { ...form };
    if (!payload.gitlab_token) delete payload.gitlab_token;
    if (!payload.bitrix24_api_key) delete payload.bitrix24_api_key;
    if (!payload.llm_api_key) delete payload.llm_api_key;
    updateSettings.mutate(payload);
  };

  if (isLoading) return <div>Загрузка...</div>;

  const labelStyle: React.CSSProperties = {
    display: "block",
    marginBottom: "8px",
    fontSize: "15px",
    fontWeight: 700,
    color: "#afafb3",
    letterSpacing: "0.02em",
  };

  const inputStyle: React.CSSProperties = {
    width: "100%",
    padding: "10px 12px",
    border: "1px solid #444",
    borderRadius: "6px",
    boxSizing: "border-box",
    background: "#2a2a3a",
    color: "rgba(255, 255, 255, 0.87)",
    fontSize: "14px",
  };

  const fieldStyle: React.CSSProperties = {
    marginBottom: "20px",
  };

  const statusText = (configured: boolean) =>
    configured ? "[Настроен]" : "[Не настроен]";

  const statusColor = (configured: boolean) =>
    configured ? "#38a169" : "#e53e3e";

  return (
    <div>
      <h1 style={{ marginTop: 0 }}>Настройки</h1>

      <form
        onSubmit={handleSubmit}
        style={{
          maxWidth: "600px",
          background: "#1e1e2e",
          padding: "24px",
          borderRadius: "8px",
          border: "1px solid #333",
        }}
      >
        <div style={fieldStyle}>
          <label style={labelStyle}>
            GitLab Token{" "}
            <span
              style={{
                color: statusColor(settings?.has_gitlab_token ?? false),
                fontWeight: "normal",
              }}
            >
              {statusText(settings?.has_gitlab_token ?? false)}
            </span>
          </label>
          <input
            type="password"
            placeholder="Введите новый токен для обновления"
            value={form.gitlab_token ?? ""}
            onChange={(e) => handleChange("gitlab_token", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>GitLab Username</label>
          <input
            type="text"
            value={form.gitlab_username ?? ""}
            onChange={(e) => handleChange("gitlab_username", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>
            GitLab Email
            <span
              style={{
                fontSize: "12px",
                fontWeight: "normal",
                color: "rgba(255, 255, 255, 0.5)",
                marginLeft: "8px",
              }}
            >
              Для фильтрации коммитов по автору
            </span>
          </label>
          <input
            type="email"
            placeholder="user@example.com"
            value={form.gitlab_email ?? ""}
            onChange={(e) => handleChange("gitlab_email", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>
            Bitrix24 API Key{" "}
            <span
              style={{
                color: statusColor(settings?.has_bitrix24_api_key ?? false),
                fontWeight: "normal",
              }}
            >
              {statusText(settings?.has_bitrix24_api_key ?? false)}
            </span>
          </label>
          <input
            type="password"
            placeholder="Введите новый ключ для обновления"
            value={form.bitrix24_api_key ?? ""}
            onChange={(e) => handleChange("bitrix24_api_key", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>Bitrix24 User ID</label>
          <input
            type="text"
            value={form.bitrix24_user_id ?? ""}
            onChange={(e) => handleChange("bitrix24_user_id", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>LLM Provider</label>
          <select
            value={form.llm_provider ?? "claude"}
            onChange={(e) => handleChange("llm_provider", e.target.value)}
            style={inputStyle}
          >
            <option value="claude">Claude</option>
            <option value="openai">OpenAI</option>
          </select>
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>
            LLM API Key{" "}
            <span
              style={{
                color: statusColor(settings?.has_llm_api_key ?? false),
                fontWeight: "normal",
              }}
            >
              {statusText(settings?.has_llm_api_key ?? false)}
            </span>
          </label>
          <input
            type="password"
            placeholder="Введите новый ключ для обновления"
            value={form.llm_api_key ?? ""}
            onChange={(e) => handleChange("llm_api_key", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>Системный промпт LLM</label>
          <textarea
            value={form.llm_system_prompt ?? ""}
            onChange={(e) => handleChange("llm_system_prompt", e.target.value)}
            rows={5}
            placeholder="Опишите тон, стиль и уровень детализации нарративов"
            style={{
              ...inputStyle,
              fontFamily: "inherit",
              resize: "vertical",
            }}
          />
          <div
            style={{
              marginTop: "4px",
              fontSize: "12px",
              color: "rgba(255, 255, 255, 0.5)",
              display: "flex",
              justifyContent: "space-between",
            }}
          >
            <span>
              Настройте тон, стиль и уровень детализации нарративов под
              требования вашего руководителя.
            </span>
            <button
              type="button"
              onClick={() => handleChange("llm_system_prompt", "")}
              style={{
                background: "none",
                border: "none",
                color: "#646cff",
                cursor: "pointer",
                fontSize: "12px",
                padding: 0,
                textDecoration: "underline",
              }}
            >
              Сбросить по умолчанию
            </button>
          </div>
        </div>

        <div style={fieldStyle}>
          <label
            style={{
              ...labelStyle,
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              cursor: "pointer",
            }}
          >
            <div>
              <span>Обогащённый контекст для LLM</span>
              <div
                style={{
                  fontSize: "12px",
                  fontWeight: "normal",
                  color: "rgba(255, 255, 255, 0.5)",
                  marginTop: "4px",
                }}
              >
                Добавляет описание MR, статистику изменений и список файлов в
                промпт
              </div>
            </div>
            <div
              onClick={() =>
                setForm((prev) => ({
                  ...prev,
                  enriched_prompt_enabled: !prev.enriched_prompt_enabled,
                }))
              }
              style={{
                width: "44px",
                height: "24px",
                borderRadius: "12px",
                background: form.enriched_prompt_enabled ? "#646cff" : "#444",
                position: "relative",
                transition: "background 0.2s",
                cursor: "pointer",
                flexShrink: 0,
                marginLeft: "16px",
              }}
            >
              <div
                style={{
                  width: "20px",
                  height: "20px",
                  borderRadius: "50%",
                  background: "#fff",
                  position: "absolute",
                  top: "2px",
                  left: form.enriched_prompt_enabled ? "22px" : "2px",
                  transition: "left 0.2s",
                }}
              />
            </div>
          </label>
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>Имя разработчика</label>
          <input
            type="text"
            value={form.developer_name ?? ""}
            onChange={(e) => handleChange("developer_name", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>Должность</label>
          <input
            type="text"
            value={form.developer_position ?? ""}
            onChange={(e) => handleChange("developer_position", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div style={fieldStyle}>
          <label style={labelStyle}>Время синхронизации</label>
          <input
            type="time"
            value={form.sync_schedule_time ?? "09:00"}
            onChange={(e) => handleChange("sync_schedule_time", e.target.value)}
            style={inputStyle}
          />
        </div>

        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "12px",
          }}
        >
          <button
            type="submit"
            disabled={updateSettings.isPending}
            style={{
              padding: "10px 24px",
              background: updateSettings.isPending ? "#999" : "#646cff",
              color: "#fff",
              border: "none",
              borderRadius: "6px",
              cursor: updateSettings.isPending ? "not-allowed" : "pointer",
              fontSize: "15px",
            }}
          >
            {updateSettings.isPending ? "Сохранение..." : "Сохранить"}
          </button>
          {saveMessage && (
            <span style={{ color: "#38a169", fontSize: "14px" }}>
              {saveMessage}
            </span>
          )}
        </div>
      </form>
    </div>
  );
}
