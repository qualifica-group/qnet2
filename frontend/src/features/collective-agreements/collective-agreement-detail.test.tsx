import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import type { ReactElement, ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import {
  fireEvent,
  render as renderWithoutClient,
  screen,
  waitFor,
} from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AxiosError } from "axios";
import i18n from "@/i18n";
import { formatDateTime } from "@/features/table/cell-renderers";
import { CollectiveAgreementDetailView } from "@/features/collective-agreements/collective-agreement-detail";
import { moduleScreen } from "@/features/collective-agreements/collective-agreement-screens";
import type { CollectiveAgreementDetailWithPermissions } from "@/features/collective-agreements/types";

/** Spec 0272: the detail edits every row in place, each save a PATCH; levels travel as a whole list. */

const updateMock = vi.fn();

vi.mock("@/features/collective-agreements/api", async (importOriginal) => ({
  ...(await importOriginal<
    typeof import("@/features/collective-agreements/api")
  >()),
  updateCollectiveAgreement: (...args: unknown[]) => updateMock(...args),
}));

vi.mock("@/features/modules/use-module-open-mode", () => ({
  useModuleOpenMode: () => "modal",
}));
vi.mock("@/features/auth/use-abilities", () => ({
  useAbilities: () => ({
    can: () => true,
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}));
vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock("@/features/activity-log/activity-log-section", () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}));

function agreement(
  overrides: Partial<CollectiveAgreementDetailWithPermissions> = {},
): CollectiveAgreementDetailWithPermissions {
  return {
    id: 4,
    name: "Commercio",
    code: "COM",
    levels: [
      {
        id: 10,
        code: "A1",
        description: "Entry",
        sort_order: 0,
        contracts_count: 2,
      },
      {
        id: 11,
        code: "A2",
        description: null,
        sort_order: 1,
        contracts_count: 0,
      },
    ],
    created_at: "2026-01-01T09:00:00Z",
    updated_at: "2026-02-15T14:30:00Z",
    abilities: { update: true, delete: true },
    permissions: {
      resource: {
        view: true,
        create: true,
        update: true,
        delete: true,
        export: true,
        import: true,
      },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  };
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    ),
  });
}

const pencilOf = (key: string) =>
  screen.queryByRole("button", {
    name: i18n.t("common.inlineEdit.edit", { field: i18n.t(key) }),
  });
const saveRow = () =>
  fireEvent.click(
    screen.getByRole("button", { name: i18n.t("common.inlineEdit.save") }),
  );

beforeAll(async () => {
  await i18n.changeLanguage("en");
});

beforeEach(() => {
  updateMock.mockReset();
});

describe("CollectiveAgreementDetailView - detail fields", () => {
  it("shows name, code, the levels in order and both timestamps", () => {
    render(<CollectiveAgreementDetailView collectiveAgreement={agreement()} />);

    expect(
      screen.getByRole("heading", { name: "Commercio" }),
    ).toBeInTheDocument();
    expect(screen.getByText("COM")).toBeInTheDocument();
    expect(
      screen.getAllByRole("listitem").map((item) => item.textContent),
    ).toEqual(["A1 – Entry", "A2"]);
    expect(
      screen.getByText(formatDateTime("2026-01-01T09:00:00Z")),
    ).toBeInTheDocument();
    expect(
      screen.getByText(formatDateTime("2026-02-15T14:30:00Z")),
    ).toBeInTheDocument();
  });
});

describe("CollectiveAgreementDetailView - in-place editing", () => {
  it("has no Edit button and the registry generates no edit route", () => {
    render(<CollectiveAgreementDetailView collectiveAgreement={agreement()} />);

    expect(
      screen.queryByRole("button", { name: "Edit" }),
    ).not.toBeInTheDocument();
    expect(moduleScreen.generateEditRoute).toBe(false);
    for (const key of ["name", "code", "levels"]) {
      expect(pencilOf(`collectiveAgreements.form.${key}`)).toBeInTheDocument();
    }
  });

  it("Save sends only the changed field", async () => {
    updateMock.mockResolvedValue(agreement({ name: "Terziario" }));
    render(<CollectiveAgreementDetailView collectiveAgreement={agreement()} />);

    fireEvent.click(pencilOf("collectiveAgreements.form.name") as HTMLElement);
    fireEvent.change(screen.getByRole("textbox"), {
      target: { value: "Terziario" },
    });
    saveRow();

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(4, { name: "Terziario" }),
    );
  });

  it("replaces the levels keeping the id of the ones that stay", async () => {
    updateMock.mockResolvedValue(agreement());
    render(<CollectiveAgreementDetailView collectiveAgreement={agreement()} />);

    fireEvent.click(
      pencilOf("collectiveAgreements.form.levels") as HTMLElement,
    );
    fireEvent.click(screen.getByRole("button", { name: "Remove level 2" }));
    fireEvent.click(screen.getByRole("button", { name: "Add level" }));
    fireEvent.change(screen.getByRole("textbox", { name: "Code of level 2" }), {
      target: { value: "B1" },
    });
    saveRow();

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(4, {
        levels: [
          { id: 10, code: "A1", description: "Entry" },
          { code: "B1", description: null },
        ],
      }),
    );
  });

  it('shows the "level in use" message when the server refuses the removal with a 422', async () => {
    updateMock.mockRejectedValue(
      new AxiosError("error", "422", undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: "Validation failed",
          errors: { levels: ["Level in use."] },
        },
      } as never),
    );
    render(<CollectiveAgreementDetailView collectiveAgreement={agreement()} />);

    fireEvent.click(
      pencilOf("collectiveAgreements.form.levels") as HTMLElement,
    );
    fireEvent.click(screen.getByRole("button", { name: "Remove level 1" }));
    saveRow();

    expect(
      await screen.findByText(i18n.t("collectiveAgreements.form.levelsInUse")),
    ).toHaveAttribute("role", "alert");
  });

  it("offers no pencil when update is not allowed", () => {
    const base = agreement();
    render(
      <CollectiveAgreementDetailView
        collectiveAgreement={agreement({
          permissions: {
            ...base.permissions,
            resource: { ...base.permissions.resource, update: false },
          },
        })}
      />,
    );

    expect(pencilOf("collectiveAgreements.form.name")).not.toBeInTheDocument();
    expect(
      pencilOf("collectiveAgreements.form.levels"),
    ).not.toBeInTheDocument();
  });
});
