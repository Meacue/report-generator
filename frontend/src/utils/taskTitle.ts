export function displayTaskTitle(
  title: string | null,
  bitrix24TaskId: number,
): string {
  if (title === null || title === "")
    return `#${bitrix24TaskId} (без названия)`;
  return title;
}
