import apiClient from "./client";
import type { SyncStatus } from "../types/api";

export const syncApi = {
  status: () => apiClient.get<SyncStatus>("/sync/status").then((r) => r.data),

  trigger: () =>
    apiClient
      .post<{ message: string; sync_job_id: number }>("/sync/trigger")
      .then((r) => r.data),

  resync: (dateFrom: string, dateTo: string) =>
    apiClient
      .post<{ message: string }>("/sync/resync", {
        date_from: dateFrom,
        date_to: dateTo,
      })
      .then((r) => r.data),
};
