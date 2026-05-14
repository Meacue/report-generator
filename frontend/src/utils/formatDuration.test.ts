import { describe, it, expect } from "vitest";
import { formatDuration } from "./formatDuration";

describe("formatDuration", () => {
  it("returns null when input is null", () => {
    expect(formatDuration(null)).toBeNull();
  });

  it('formats 0 seconds as "0ч 0м"', () => {
    expect(formatDuration(0)).toBe("0ч 0м");
  });

  it('formats 330 seconds as "0ч 5м"', () => {
    expect(formatDuration(330)).toBe("0ч 5м");
  });

  it('formats 9015 seconds as "2ч 30м"', () => {
    expect(formatDuration(9015)).toBe("2ч 30м");
  });

  it('formats 1036800 seconds as "288ч 0м"', () => {
    expect(formatDuration(1036800)).toBe("288ч 0м");
  });
});
