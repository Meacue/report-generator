import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SettingsForm } from "./SettingsForm";
import type { Settings } from "../../types/api";

const baseSettings: Settings = {
  gitlab_username: "octocat",
  gitlab_email: "octo@example.com",
  bitrix24_user_id: null,
  bitrix24_webhook_configured: false,
  llm_provider: "claude",
  llm_system_prompt: null,
  enriched_prompt_enabled: false,
  developer_name: "Octo Cat",
  developer_position: "Engineer",
  sync_schedule_time: "09:00",
  has_gitlab_token: false,
  has_bitrix24_api_key: false,
  has_llm_api_key: false,
};

const renderForm = (overrides: Partial<Settings> = {}, onSubmit = vi.fn()) => {
  const initial: Settings = { ...baseSettings, ...overrides };
  render(
    <SettingsForm
      initial={initial}
      onSubmit={onSubmit}
      isSubmitting={false}
      saveMessage={null}
    />,
  );
  return { onSubmit };
};

describe("SettingsForm", () => {
  it("renders webhook URL input and hides legacy fields", () => {
    renderForm();

    expect(screen.queryByText("Bitrix24 API Key")).not.toBeInTheDocument();
    expect(screen.queryByText("Bitrix24 User ID")).not.toBeInTheDocument();
    expect(
      screen.getByPlaceholderText(/your-portal\.bitrix24\.ru/),
    ).toBeInTheDocument();
  });

  it("shows configured indicator when bitrix24_webhook_configured is true", () => {
    renderForm({ bitrix24_webhook_configured: true });

    const webhookLabel = screen.getByText(/Bitrix24 Webhook URL/);
    expect(webhookLabel).toHaveTextContent("[Настроен]");
  });

  it("shows not configured indicator when bitrix24_webhook_configured is false", () => {
    renderForm({ bitrix24_webhook_configured: false });

    const webhookLabel = screen.getByText(/Bitrix24 Webhook URL/);
    expect(webhookLabel).toHaveTextContent("[Не настроен]");
  });

  it("shows inline error on blur with invalid url", async () => {
    renderForm();
    const user = userEvent.setup();

    const input = screen.getByPlaceholderText(/your-portal\.bitrix24\.ru/);
    await user.type(input, "foo");
    await user.tab();

    expect(
      screen.getByText(
        "Формат: https://<портал>.bitrix24.ru/rest/<user_id>/<api_key>/",
      ),
    ).toBeInTheDocument();
  });

  it("does not show error for empty input on blur", async () => {
    renderForm();
    const user = userEvent.setup();

    const input = screen.getByPlaceholderText(/your-portal\.bitrix24\.ru/);
    await user.click(input);
    await user.tab();

    expect(
      screen.queryByText(
        "Формат: https://<портал>.bitrix24.ru/rest/<user_id>/<api_key>/",
      ),
    ).not.toBeInTheDocument();
  });

  it("blocks submit while error is present", async () => {
    const { onSubmit } = renderForm();
    const user = userEvent.setup();

    const input = screen.getByPlaceholderText(/your-portal\.bitrix24\.ru/);
    await user.type(input, "not-a-url");
    await user.tab();

    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("submits canonical webhook url", async () => {
    const { onSubmit } = renderForm();
    const user = userEvent.setup();

    const input = screen.getByPlaceholderText(/your-portal\.bitrix24\.ru/);
    const webhook = "https://example-portal.bitrix24.ru/rest/1/testwebhookkey00/";
    await user.type(input, webhook);
    await user.tab();

    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    expect(onSubmit).toHaveBeenCalledOnce();
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({ bitrix24_webhook_url: webhook }),
    );
  });

  it("omits empty webhook from payload", async () => {
    const { onSubmit } = renderForm();
    const user = userEvent.setup();

    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    expect(onSubmit).toHaveBeenCalledOnce();
    const payload = onSubmit.mock.calls[0][0] as Record<string, unknown>;
    expect(payload).not.toHaveProperty("bitrix24_webhook_url");
  });
});
