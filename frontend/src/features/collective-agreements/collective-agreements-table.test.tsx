import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { forwardRef, useImperativeHandle, type ReactNode } from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AxiosError } from "axios";
import i18n from "@/i18n";
import CollectiveAgreementsPage from "@/pages/collective-agreements-page";
import { CollectiveAgreementsTable } from "@/features/collective-agreements/collective-agreements-table";
import type { TableActionDefinition, TableRow } from "@/features/table/types";

/**
 * The generic `<TableView>` and the app chrome are stubbed so the suite
 * stays on what this adapter owns: `<Can>` gating, mounting
 * `<TableView domain="collective-agreements">` and the delete flow, including the 409
 * "in use" guard distinct from 403/generic.
 */
const canMock = vi.fn<(permission: string) => boolean>();

vi.mock("@/features/auth/use-abilities", () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}));

vi.mock("@/features/modules/use-module-open-mode", () => ({
  useModuleOpenMode: () => "modal",
}));

vi.mock("@/components/page-header", () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}));

const deleteCollectiveAgreementMock = vi.fn();
vi.mock("@/features/collective-agreements/api", async (importOriginal) => ({
  ...(await importOriginal<
    typeof import("@/features/collective-agreements/api")
  >()),
  deleteCollectiveAgreement: (...args: unknown[]) =>
    deleteCollectiveAgreementMock(...args),
}));

const toastSuccessMock = vi.fn();
const toastErrorMock = vi.fn();
vi.mock("sonner", () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}));

const DELETE_ACTION: TableActionDefinition = {
  key: "delete",
  label: "actions.delete",
  icon: "trash",
  type: "danger",
  confirm: true,
};

const ROW: TableRow = { id: 1, actions: ["delete"], name: "Fitti" };

vi.mock("@/features/table/table-view", () => ({
  TableView: forwardRef<
    { refresh: () => void },
    {
      domain: string;
      onAction?: (action: TableActionDefinition, row: TableRow) => void;
    }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }));
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    );
  }),
}));

function renderWith(node: ReactNode) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  );
}

function axiosError(status: number) {
  return new AxiosError("error", String(status), undefined, undefined, {
    status,
    data: { success: false, message: "server message" },
  } as never);
}

beforeAll(async () => {
  await i18n.changeLanguage("en");
});

beforeEach(() => {
  canMock.mockReset();
  canMock.mockReturnValue(true);
  deleteCollectiveAgreementMock.mockReset();
  toastSuccessMock.mockReset();
  toastErrorMock.mockReset();
});

describe("CollectiveAgreementsPage - permission gating", () => {
  it("shows the forbidden fallback and does not mount the table without view", () => {
    canMock.mockReturnValue(false);

    renderWith(<CollectiveAgreementsPage />);

    expect(
      screen.getByText(
        "You don't have permission to view collective agreements.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("region", { name: "table-collective-agreements" }),
    ).not.toBeInTheDocument();
  });

  it('mounts <TableView domain="collective-agreements"> with view', () => {
    canMock.mockImplementation(
      (permission) => permission === "collective-agreements.view",
    );

    renderWith(<CollectiveAgreementsPage />);

    expect(
      screen.getByRole("region", { name: "table-collective-agreements" }),
    ).toBeInTheDocument();
  });
});

describe("CollectiveAgreementsTable - new button", () => {
  it('shows "New collective agreement" with create and hides it without', () => {
    canMock.mockImplementation(
      (permission) => permission === "collective-agreements.create",
    );
    const { unmount } = renderWith(<CollectiveAgreementsTable />);
    expect(
      screen.getByRole("button", { name: "New collective agreement" }),
    ).toBeInTheDocument();
    unmount();

    canMock.mockReturnValue(false);
    renderWith(<CollectiveAgreementsTable />);
    expect(
      screen.queryByRole("button", { name: "New collective agreement" }),
    ).not.toBeInTheDocument();
  });
});

describe("CollectiveAgreementsTable - delete", () => {
  it("shows the success toast on a successful delete", async () => {
    deleteCollectiveAgreementMock.mockResolvedValue(undefined);

    renderWith(<CollectiveAgreementsTable />);
    screen.getByRole("button", { name: "delete row" }).click();

    await waitFor(() =>
      expect(deleteCollectiveAgreementMock).toHaveBeenCalledWith(1),
    );
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith(
        "Collective agreement deleted successfully.",
      ),
    );
  });

  it("shows a forbidden toast on a 403", async () => {
    deleteCollectiveAgreementMock.mockRejectedValue(axiosError(403));

    renderWith(<CollectiveAgreementsTable />);
    screen.getByRole("button", { name: "delete row" }).click();

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        "You cannot delete this collective agreement.",
      ),
    );
  });

  it('shows a distinct "in use" toast on a 409', async () => {
    deleteCollectiveAgreementMock.mockRejectedValue(axiosError(409));

    renderWith(<CollectiveAgreementsTable />);
    screen.getByRole("button", { name: "delete row" }).click();

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        "Cannot delete: a level of this agreement is used by an employment condition.",
      ),
    );
  });

  it("falls back to the generic error message on any other failure", async () => {
    deleteCollectiveAgreementMock.mockRejectedValue(new Error("network down"));

    renderWith(<CollectiveAgreementsTable />);
    screen.getByRole("button", { name: "delete row" }).click();

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        "Unable to delete the collective agreement. Please try again.",
      ),
    );
  });
});
