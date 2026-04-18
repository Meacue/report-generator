import apiClient from "./client";

export interface GenerateReportParams {
  type: "daily" | "weekly" | "monthly" | "custom";
  date_from: string;
  date_to: string;
}

export interface ReportPreview {
  id: number;
  type: string;
  date_from: string;
  date_to: string;
  status: string;
  days: ReportDayPreview[];
  tasks: ReportTaskPreview[];
}

export interface ReportDayPreview {
  id: number;
  date: string;
  narrative: string | null;
  source: string;
  is_edited: boolean;
}

export interface ReportTaskPreview {
  id: number;
  task_id: number | null;
  narrative: string | null;
  project_name: string;
  is_edited: boolean;
  task?: {
    id: number;
    bitrix24_task_id: number;
    title: string | null;
    status: string;
    seconds_tracked: number | null;
  };
}

export interface ReportListItem {
  id: number;
  type: string;
  date_from: string;
  date_to: string;
  status: string;
  created_at: string;
}

export interface ReportListParams {
  page?: number;
  per_page?: number;
  sort_direction?: "desc" | "asc";
}

export const reportsApi = {
  list: (params: ReportListParams = {}) =>
    apiClient
      .get<{
        data: ReportListItem[];
        meta: {
          current_page: number;
          last_page: number;
          per_page: number;
          total: number;
        };
      }>("/reports", { params })
      .then((r) => r.data),

  generate: (params: GenerateReportParams) =>
    apiClient
      .post<{ data: ReportPreview }>("/reports/generate", params)
      .then((r) => r.data.data),

  getPreview: (id: number) =>
    apiClient
      .get<{ data: ReportPreview }>(`/reports/${id}/preview`)
      .then((r) => r.data.data),

  updateDayNarrative: (reportId: number, date: string, narrative: string) =>
    apiClient
      .put<{ data: ReportDayPreview }>(`/reports/${reportId}/days/${date}`, {
        narrative,
      })
      .then((r) => r.data.data),

  updateTaskNarrative: (reportId: number, taskId: number, narrative: string) =>
    apiClient
      .put<{
        data: ReportTaskPreview;
      }>(`/reports/${reportId}/tasks/${taskId}`, { narrative })
      .then((r) => r.data.data),

  regenerateTask: (reportId: number, taskId: number) =>
    apiClient
      .post<{
        data: ReportTaskPreview;
      }>(`/reports/${reportId}/tasks/${taskId}/regenerate`)
      .then((r) => r.data.data),

  regenerateDay: (reportId: number, date: string) =>
    apiClient
      .post<{
        data: ReportDayPreview;
      }>(`/reports/${reportId}/days/${date}/regenerate`)
      .then((r) => r.data.data),

  undoTask: (reportId: number, taskId: number) =>
    apiClient
      .post<{
        data: ReportTaskPreview;
      }>(`/reports/${reportId}/tasks/${taskId}/undo`)
      .then((r) => r.data.data),

  undoDay: (reportId: number, date: string) =>
    apiClient
      .post<{
        data: ReportDayPreview;
      }>(`/reports/${reportId}/days/${date}/undo`)
      .then((r) => r.data.data),

  export: (id: number) =>
    apiClient.get<Blob>(`/reports/${id}/export`, { responseType: "blob" }),

  exportPrompt: (id: number) =>
    apiClient.get(`/reports/${id}/export-prompt`, { responseType: "blob" }),
};
