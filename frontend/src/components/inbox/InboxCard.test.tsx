import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { InboxCard } from "./InboxCard";
import type { InboxItem } from "../../types/api";

const baseItem: InboxItem = {
  id: 42,
  branch_name: "feature/test-branch",
  gitlab_repo_id: 1,
  parsed_task_number: null,
  parsed_date: null,
  parsed_info: null,
  confidence_level: null,
  commits_count: 7,
  last_commit: null,
  synced_at: null,
};

describe("InboxCard", () => {
  it("renders branch_name and commits count", () => {
    render(
      <InboxCard
        item={baseItem}
        onAssign={vi.fn()}
        onIgnore={vi.fn()}
        onCreateTask={vi.fn()}
        isLoading={false}
      />,
    );

    expect(screen.getByText("feature/test-branch")).toBeInTheDocument();
    expect(screen.getByText(/7/)).toBeInTheDocument();
  });

  it('calls onIgnore with item.id when "Игнорировать" is clicked', async () => {
    const onIgnore = vi.fn();
    render(
      <InboxCard
        item={baseItem}
        onAssign={vi.fn()}
        onIgnore={onIgnore}
        onCreateTask={vi.fn()}
        isLoading={false}
      />,
    );

    await userEvent.click(screen.getByRole("button", { name: "Игнорировать" }));

    expect(onIgnore).toHaveBeenCalledOnce();
    expect(onIgnore).toHaveBeenCalledWith(42);
  });

  it('calls onAssign with item.id and typed task number when "Привязать" is clicked', async () => {
    const onAssign = vi.fn();
    render(
      <InboxCard
        item={baseItem}
        onAssign={onAssign}
        onIgnore={vi.fn()}
        onCreateTask={vi.fn()}
        isLoading={false}
      />,
    );

    const input = screen.getByPlaceholderText("Номер задачи Bitrix24");
    await userEvent.type(input, "123");
    await userEvent.click(screen.getByRole("button", { name: "Привязать" }));

    expect(onAssign).toHaveBeenCalledOnce();
    expect(onAssign).toHaveBeenCalledWith(42, 123);
  });
});
