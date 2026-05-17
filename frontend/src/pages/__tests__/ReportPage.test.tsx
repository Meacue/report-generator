import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { ApiError } from "../../api/client";
import { ReportPage } from "../ReportPage";

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock("../../api/reports", () => ({
  reportsApi: {
    generate: vi.fn(),
    list: vi.fn().mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),
    getPreview: vi.fn(),
  },
}));

// We also need to mock the hooks so they call the mocked api module.
// useGenerateReport internally calls reportsApi.generate via useMutation.
// Because we mock the module, the hook will use our mocked version.

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function buildQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });
}

function renderReportPage(path = "/reports"): void {
  render(
    <QueryClientProvider client={buildQueryClient()}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/reports" element={<ReportPage />} />
          <Route path="/reports/:id" element={<ReportPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("ReportPage — LLM config error handling", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows llm config error inline when backend returns 422 with violations", async () => {
    const { reportsApi } = await import("../../api/reports");

    vi.mocked(reportsApi.generate).mockRejectedValue(
      new ApiError("Request failed with status 422", 422, {
        error: "LLM configuration is invalid",
        violations: ["LLM_MAX_TOKENS must be >= 1"],
        settings_url: "/settings",
      }),
    );

    renderReportPage();

    const generateButton = await screen.findByRole("button", {
      name: /Сгенерировать/i,
    });

    await userEvent.click(generateButton);

    // After the frontend agent implements 422/violations handling, this text must appear.
    // Until then (TDD red), the component falls through to the generic error message.
    await waitFor(() => {
      expect(screen.getByText(/LLM_MAX_TOKENS/i)).toBeInTheDocument();
    });
  });

  it("shows generic error when backend returns other 4xx without violations", async () => {
    const { reportsApi } = await import("../../api/reports");

    vi.mocked(reportsApi.generate).mockRejectedValue(
      new ApiError("Request failed with status 400", 400, {
        error: "Bad request",
      }),
    );

    renderReportPage();

    const generateButton = await screen.findByRole("button", {
      name: /Сгенерировать/i,
    });

    await userEvent.click(generateButton);

    await waitFor(() => {
      expect(
        screen.getByText(/Не удалось сгенерировать отчёт/i),
      ).toBeInTheDocument();
    });
  });
});
