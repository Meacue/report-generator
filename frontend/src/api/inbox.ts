import apiClient from "./client";
import type { InboxItem, PaginatedResponse } from "../types/api";

export interface InboxParams {
  per_page?: number;
  page?: number;
  filter?: "all" | "probable" | "none";
  sort_direction?: "asc" | "desc";
}

export const inboxApi = {
  list: (params: InboxParams = {}) =>
    apiClient
      .get<{
        data: InboxItem[];
        meta: PaginatedResponse<InboxItem>["meta"];
      }>("/inbox", { params })
      .then((r) => r.data),

  assign: (branchId: number, taskId: number) =>
    apiClient
      .post<{ message: string }>("/inbox/assign", {
        branch_id: branchId,
        task_id: taskId,
      })
      .then((r) => r.data),

  bulkAssign: (assignments: Array<{ branch_id: number; task_id: number }>) =>
    apiClient
      .post<{ message: string }>("/inbox/bulk-assign", { assignments })
      .then((r) => r.data),

  ignore: (branchId: number) =>
    apiClient
      .post<{ message: string }>("/inbox/ignore", { branch_id: branchId })
      .then((r) => r.data),

  createTask: (branchId: number, title: string) =>
    apiClient
      .post<{ message: string }>("/inbox/create-task", {
        branch_id: branchId,
        title,
      })
      .then((r) => r.data),
};
