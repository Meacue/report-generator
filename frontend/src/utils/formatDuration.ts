/**
 * Formats a duration in seconds as "Xч Ym".
 * Examples:
 *   0       -> "0ч 0м"
 *   330     -> "0ч 5м"
 *   9015    -> "2ч 30м"  (ignores seconds)
 *   1036800 -> "288ч 0м"
 *
 * Returns null if input is null (caller decides what to render).
 */
export function formatDuration(seconds: number | null): string | null {
  if (seconds === null) return null;
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return `${h}ч ${m}м`;
}
