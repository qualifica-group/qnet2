import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import type { ReactNode } from "react";
import {
  fireEvent,
  render,
  screen,
  waitFor,
  within,
} from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AxiosError } from "axios";
import i18n from "@/i18n";
import { CollectiveAgreementForm } from "@/features/collective-agreements/collective-agreement-form";
import type { CollectiveAgreementDetail } from "@/features/collective-agreements/types";
import type { ResourcePermissions } from "@/features/authorization/types";

/** Spec 0272: the create form is a replica of the detail with closed rows; Save creates everything at once. */

const createMock = vi.fn();

vi.mock("@/features/collective-agreements/api", async (importOriginal) => ({
  ...(await importOriginal<
    typeof import("@/features/collective-agreements/api")
  >()),
  createCollectiveAgreement: (...args: unknown[]) => createMock(...args),
}));

vi.mock("sonner", () => ({ toast: { success: vi.fn() } }));

const PERMISSIONS: ResourcePermissions = {
  resource: {
    view: true,
    create: true,
    update: true,
    delete: true,
    export: true,
    import: true,
  },
  fields: {},
  actions: {},
};

vi.mock("@/features/authorization/use-resource-meta", () => ({
  useResourceMeta: () => ({
    data: { permissions: PERMISSIONS },
    isError: false,
    refetch: vi.fn(),
  }),
}));

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

const CREATED: CollectiveAgreementDetail = {
  id: 9,
  name: "Commercio",
  code: null,
  levels: [],
  created_at: "2026-10-16T08:00:00Z",
  updated_at: "2026-10-16T08:00:00Z",
  abilities: { update: true, delete: true },
};

function openRow(key: string) {
  fireEvent.click(
    screen.getByRole("button", {
      name: i18n.t("common.inlineEdit.edit", { field: i18n.t(key) }),
    }),
  );
}

function typeName(value: string) {
  openRow("collectiveAgreements.form.name");
  fireEvent.change(screen.getByRole("textbox"), { target: { value } });
}

const save = () =>
  fireEvent.click(
    screen.getAllByRole("button", {
      name: i18n.t("collectiveAgreements.form.save"),
    })[0],
  );
const addLevel = () =>
  fireEvent.click(screen.getByRole("button", { name: "Add level" }));
const levelCode = (n: number) =>
  screen.getByRole("textbox", { name: `Code of level ${n}` });

function rejection(status: number, errors: Record<string, string[]>) {
  return new AxiosError("error", String(status), undefined, undefined, {
    status,
    data: { success: false, message: "Validation failed", errors },
  } as never);
}

beforeAll(async () => {
  await i18n.changeLanguage("en");
});

beforeEach(() => {
  createMock.mockReset();
});

describe("CollectiveAgreementForm - create (spec 0272)", () => {
  it("lists the name, code and levels rows", () => {
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    for (const key of ["name", "code", "levels"]) {
      expect(
        screen.getByRole("button", {
          name: i18n.t("common.inlineEdit.edit", {
            field: i18n.t(`collectiveAgreements.form.${key}`),
          }),
        }),
      ).toBeInTheDocument();
    }
  });

  it("does not call the API when the name is empty", async () => {
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    save();

    expect(await screen.findByText("The name is required.")).toHaveAttribute(
      "role",
      "alert",
    );
    expect(createMock).not.toHaveBeenCalled();
  });

  it("adds, reorders and removes levels, then submits them in order", async () => {
    createMock.mockResolvedValue(CREATED);
    const onSuccess = vi.fn();
    render(
      <CollectiveAgreementForm onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    );

    typeName("Commercio");
    openRow("collectiveAgreements.form.levels");
    addLevel();
    fireEvent.change(levelCode(1), { target: { value: "A1" } });
    addLevel();
    fireEvent.change(levelCode(2), { target: { value: "A2" } });
    fireEvent.change(
      screen.getByRole("textbox", { name: "Description of level 2" }),
      { target: { value: "Second" } },
    );
    addLevel();
    fireEvent.change(levelCode(3), { target: { value: "tmp" } });
    fireEvent.click(screen.getByRole("button", { name: "Remove level 3" }));
    fireEvent.click(screen.getByRole("button", { name: "Move level 2 up" }));
    save();

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1));
    expect(createMock).toHaveBeenCalledWith({
      name: "Commercio",
      code: null,
      levels: [
        { code: "A2", description: "Second" },
        { code: "A1", description: null },
      ],
    });
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED));
  });

  it("requires the code of each level", async () => {
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    typeName("Commercio");
    openRow("collectiveAgreements.form.levels");
    addLevel();
    save();

    expect(
      await screen.findByText("The level code is required."),
    ).toBeInTheDocument();
    expect(createMock).not.toHaveBeenCalled();
  });

  it("disables moving the first level up and the last level down", () => {
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    openRow("collectiveAgreements.form.levels");
    addLevel();
    addLevel();

    expect(
      screen.getByRole("button", { name: "Move level 1 up" }),
    ).toBeDisabled();
    expect(
      screen.getByRole("button", { name: "Move level 2 down" }),
    ).toBeDisabled();
    expect(
      within(screen.getByRole("list")).getAllByRole("listitem"),
    ).toHaveLength(2);
  });

  it("maps a server 422 on the name onto the form", async () => {
    createMock.mockRejectedValue(
      rejection(422, { name: ["Name already taken."] }),
    );
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    typeName("Commercio");
    save();

    expect(await screen.findByText("Name already taken.")).toBeInTheDocument();
  });

  it("maps a server 422 on a duplicated level code onto that level row", async () => {
    createMock.mockRejectedValue(
      rejection(422, { "levels.1.code": ["Level code already used."] }),
    );
    render(<CollectiveAgreementForm onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    });

    typeName("Commercio");
    openRow("collectiveAgreements.form.levels");
    addLevel();
    fireEvent.change(levelCode(1), { target: { value: "A1" } });
    addLevel();
    fireEvent.change(levelCode(2), { target: { value: "A1" } });
    save();

    expect(
      await screen.findByText("Level code already used."),
    ).toBeInTheDocument();
  });
});
