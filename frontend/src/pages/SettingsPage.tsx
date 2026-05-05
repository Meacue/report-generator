import { useRef, useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { settingsApi } from "../api/settings";
import { SettingsForm } from "../components/settings/SettingsForm";
import type { SettingsInput } from "../types/api";

export function SettingsPage() {
  const queryClient = useQueryClient();
  const { data: settings, isLoading } = useQuery({
    queryKey: ["settings"],
    queryFn: settingsApi.get,
  });

  const [saveMessage, setSaveMessage] = useState<string | null>(null);
  const saveMessageTimerRef = useRef<number | null>(null);

  const updateSettings = useMutation({
    mutationFn: (data: SettingsInput) => settingsApi.update(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      setSaveMessage("Настройки сохранены");
      if (saveMessageTimerRef.current) {
        clearTimeout(saveMessageTimerRef.current);
      }
      saveMessageTimerRef.current = window.setTimeout(
        () => setSaveMessage(null),
        3000,
      );
    },
  });

  if (isLoading || !settings) return <div>Загрузка...</div>;

  return (
    <div>
      <h1 style={{ marginTop: 0 }}>Настройки</h1>
      <SettingsForm
        initial={settings}
        onSubmit={(payload) => updateSettings.mutate(payload)}
        isSubmitting={updateSettings.isPending}
        saveMessage={saveMessage}
      />
    </div>
  );
}
