import apiClient from "./client";
import type { ProjectMapping } from "../types/api";

export const mappingsApi = {
  list: () =>
    apiClient
      .get<{ data: ProjectMapping[] }>("/projects/mappings")
      .then((r) => r.data.data),

  create: (data: Omit<ProjectMapping, "id" | "created_at">) =>
    apiClient
      .post<{ id: number; message: string }>("/projects/mappings", data)
      .then((r) => r.data),

  update: (
    id: number,
    data: Partial<Omit<ProjectMapping, "id" | "created_at">>,
  ) => apiClient.put(`/projects/mappings/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    apiClient.delete(`/projects/mappings/${id}`).then((r) => r.data),
};
