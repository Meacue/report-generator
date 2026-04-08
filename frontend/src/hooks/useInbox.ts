import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { inboxApi, type InboxParams } from "../api/inbox";

export function useInbox(params: InboxParams = {}) {
  return useQuery({
    queryKey: ["inbox", params],
    queryFn: () => inboxApi.list(params),
  });
}

export function useAssignBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ branchId, taskId }: { branchId: number; taskId: number }) =>
      inboxApi.assign(branchId, taskId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["inbox"] });
    },
  });
}

export function useBulkAssign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (assignments: Array<{ branch_id: number; task_id: number }>) =>
      inboxApi.bulkAssign(assignments),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["inbox"] });
    },
  });
}

export function useIgnoreBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (branchId: number) => inboxApi.ignore(branchId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["inbox"] });
    },
  });
}

export function useCreateInboxTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ branchId, title }: { branchId: number; title: string }) =>
      inboxApi.createTask(branchId, title),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["inbox"] });
    },
  });
}
