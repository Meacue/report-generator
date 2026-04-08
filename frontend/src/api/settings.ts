import apiClient from "./client";
import type { Settings, SettingsInput } from "../types/api";

export const settingsApi = {
  get: () => apiClient.get<Settings>("/settings").then((r) => r.data),

  update: (data: SettingsInput) =>
    apiClient.put<{ message: string }>("/settings", data).then((r) => r.data),
};
