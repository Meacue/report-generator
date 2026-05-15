import { useState } from "react";
import type { Settings, SettingsInput } from "../../types/api";

interface SettingsFormProps {
  initial: Settings;
  onSubmit: (payload: SettingsInput) => void;
  isSubmitting: boolean;
  saveMessage: string | null;
}

const WEBHOOK_URL_REGEX =
  /^https?:\/\/[^/\s]+(?::\d+)?\/rest\/\d+\/[A-Za-z0-9]+\/?$/;

const WEBHOOK_ERROR_MESSAGE =
  "Формат: https://<портал>.bitrix24.ru/rest/<user_id>/<api_key>/";

function buildInitialForm(settings: Settings): SettingsInput {
  return {
    gitlab_username: settings.gitlab_username ?? "",
    gitlab_email: settings.gitlab_email ?? "",
    bitrix24_webhook_url: "",
    llm_provider: settings.llm_provider,
    llm_system_prompt: settings.llm_system_prompt ?? "",
    enriched_prompt_enabled: settings.enriched_prompt_enabled,
    developer_name: settings.developer_name ?? "",
    developer_position: settings.developer_position ?? "",
    sync_schedule_time: settings.sync_schedule_time,
  };
}

export function SettingsForm({
  initial,
  onSubmit,
  isSubmitting,
  saveMessage,
}: SettingsFormProps) {
  const [form, setForm] = useState<SettingsInput>(() =>
    buildInitialForm(initial),
  );
  const [webhookError, setWebhookError] = useState<string | null>(null);

  const handleChange = (field: keyof SettingsInput, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleWebhookBlur = (value: string) => {
    const trimmed = value.trim();
    if (trimmed === "") {
      setWebhookError(null);
      return;
    }
    if (!WEBHOOK_URL_REGEX.test(trimmed)) {
      setWebhookError(WEBHOOK_ERROR_MESSAGE);
      return;
    }
    setWebhookError(null);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (webhookError !== null) {
      return;
    }
    // Only send non-empty password fields
    const payload: SettingsInput = { ...form };
    if (!payload.gitlab_token) delete payload.gitlab_token;
    if (!payload.bitrix24_webhook_url) delete payload.bitrix24_webhook_url;
    if (!payload.llm_api_key) delete payload.llm_api_key;
    onSubmit(payload);
  };

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
              color: statusColor(initial.has_gitlab_token),
              fontWeight: "normal",
            }}
          >
            {statusText(initial.has_gitlab_token)}
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
          Bitrix24 Webhook URL{" "}
          <span
            style={{
              color: statusColor(initial.bitrix24_webhook_configured),
              fontWeight: "normal",
            }}
          >
            {statusText(initial.bitrix24_webhook_configured)}
          </span>
        </label>
        <div
          style={{
            marginTop: "-4px",
            marginBottom: "8px",
            fontSize: "12px",
            color: "rgba(255, 255, 255, 0.5)",
          }}
        >
          Полный webhook URL (Bitrix24 &rarr; Разработчикам &rarr; Другое &rarr;
          Входящий вебхук). Формат:
          https://&lt;портал&gt;.bitrix24.ru/rest/&lt;user_id&gt;/&lt;api_key&gt;/
        </div>
        <input
          type="password"
          placeholder="https://your-portal.bitrix24.ru/rest/123/abc.../"
          value={form.bitrix24_webhook_url ?? ""}
          onChange={(e) => handleChange("bitrix24_webhook_url", e.target.value)}
          onBlur={(e) => handleWebhookBlur(e.target.value)}
          style={{
            ...inputStyle,
            border: webhookError ? "1px solid #e53e3e" : "1px solid #444",
          }}
        />
        {webhookError && (
          <div
            style={{
              marginTop: "6px",
              fontSize: "12px",
              color: "#e53e3e",
            }}
          >
            {webhookError}
          </div>
        )}
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
              color: statusColor(initial.has_llm_api_key),
              fontWeight: "normal",
            }}
          >
            {statusText(initial.has_llm_api_key)}
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
            Настройте тон, стиль и уровень детализации нарративов под требования
            вашего руководителя.
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
          disabled={isSubmitting || webhookError !== null}
          style={{
            padding: "10px 24px",
            background:
              isSubmitting || webhookError !== null ? "#999" : "#646cff",
            color: "#fff",
            border: "none",
            borderRadius: "6px",
            cursor:
              isSubmitting || webhookError !== null ? "not-allowed" : "pointer",
            fontSize: "15px",
          }}
        >
          {isSubmitting ? "Сохранение..." : "Сохранить"}
        </button>
        {saveMessage && (
          <span style={{ color: "#38a169", fontSize: "14px" }}>
            {saveMessage}
          </span>
        )}
      </div>
    </form>
  );
}
