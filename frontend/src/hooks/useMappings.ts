import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { mappingsApi } from "../api/mappings";
import type { ProjectMapping } from "../types/api";

export function useMappings() {
  return useQuery({
    queryKey: ["mappings"],
    queryFn: mappingsApi.list,
  });
}

export function useCreateMapping() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Omit<ProjectMapping, "id" | "created_at">) =>
      mappingsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["mappings"] });
    },
  });
}

export function useDeleteMapping() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => mappingsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["mappings"] });
    },
  });
}
