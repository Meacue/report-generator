import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { reportsApi } from "../api/reports";
import type { GenerateReportParams, ReportListParams } from "../api/reports";

export function useReportsList(params: ReportListParams = {}) {
  return useQuery({
    queryKey: ["reports", params],
    queryFn: () => reportsApi.list(params),
  });
}

export function useGenerateReport() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params: GenerateReportParams) => reportsApi.generate(params),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["reports"] });
    },
  });
}

export function useReportPreview(id: number | null) {
  return useQuery({
    queryKey: ["report-preview", id],
    queryFn: () => reportsApi.getPreview(id!),
    enabled: id !== null,
  });
}

export function useUpdateDayNarrative(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ date, narrative }: { date: string; narrative: string }) =>
      reportsApi.updateDayNarrative(reportId, date, narrative),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useUpdateTaskNarrative(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      taskId,
      narrative,
    }: {
      taskId: number;
      narrative: string;
    }) => reportsApi.updateTaskNarrative(reportId, taskId, narrative),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useRegenerateTask(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (taskId: number) => reportsApi.regenerateTask(reportId, taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useRegenerateDay(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (date: string) => reportsApi.regenerateDay(reportId, date),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useUndoTask(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (taskId: number) => reportsApi.undoTask(reportId, taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useUndoDay(reportId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (date: string) => reportsApi.undoDay(reportId, date),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["report-preview", reportId],
      });
    },
  });
}

export function useExportReport() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => reportsApi.export(id),
    onSuccess: (response, id) => {
      const blob = new Blob([response.data], {
        type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `report-${id}.docx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      queryClient.invalidateQueries({ queryKey: ["reports"] });
    },
  });
}

export function useExportPrompt() {
  return useMutation({
    mutationFn: async (reportId: number) => {
      const response = await reportsApi.exportPrompt(reportId);
      const contentDisposition = response.headers["content-disposition"] as
        | string
        | undefined;
      const filenameMatch = contentDisposition?.match(/filename="(.+?)"/);
      const filename = filenameMatch ? filenameMatch[1] : "report-prompt.txt";

      const url = window.URL.createObjectURL(
        new Blob([response.data as BlobPart]),
      );
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    },
  });
}
