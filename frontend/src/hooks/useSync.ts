import { useQuery, useMutation } from "@tanstack/react-query";
import { syncApi } from "../api/sync";

export function useSyncStatus() {
  return useQuery({
    queryKey: ["sync-status"],
    queryFn: syncApi.status,
    refetchInterval: (query) =>
      query.state.data?.status === "in_progress" ? 3000 : false,
  });
}

export function useTriggerSync() {
  return useMutation({
    mutationFn: syncApi.trigger,
  });
}
