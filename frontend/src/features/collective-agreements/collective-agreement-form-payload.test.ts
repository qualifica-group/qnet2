import { describe, expect, it } from "vitest";
import {
  buildCreatePayload,
  buildUpdatePayload,
} from "@/features/collective-agreements/collective-agreement-form-payload";
import type { CollectiveAgreementDetail } from "@/features/collective-agreements/types";

const ORIGINAL: CollectiveAgreementDetail = {
  id: 1,
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
  created_at: "2026-10-16T08:00:00Z",
  updated_at: "2026-10-16T08:00:00Z",
  abilities: { update: true, delete: true },
};

const VALUES = {
  name: "Commercio",
  code: "COM",
  levels: [
    { id: 10, code: "A1", description: "Entry" },
    { id: 11, code: "A2", description: null },
  ],
};

describe("buildCreatePayload (spec 0272)", () => {
  it("trims, nulls blanks and omits the id of new levels", () => {
    expect(
      buildCreatePayload({
        name: " Commercio ",
        code: " ",
        levels: [{ code: " B1 ", description: "" }],
      }),
    ).toEqual({
      name: "Commercio",
      code: null,
      levels: [{ code: "B1", description: null }],
    });
  });
});

describe("buildUpdatePayload (spec 0272)", () => {
  it("is empty when nothing changed", () => {
    expect(buildUpdatePayload(VALUES, ORIGINAL)).toEqual({});
  });

  it("sends the whole level list, keeping existing ids, when a level is added", () => {
    const levels = [...VALUES.levels, { code: "A3", description: "New" }];

    expect(buildUpdatePayload({ ...VALUES, levels }, ORIGINAL)).toEqual({
      levels: [
        { id: 10, code: "A1", description: "Entry" },
        { id: 11, code: "A2", description: null },
        { code: "A3", description: "New" },
      ],
    });
  });

  it("treats a reorder as a change", () => {
    const levels = [VALUES.levels[1], VALUES.levels[0]];

    expect(
      buildUpdatePayload({ ...VALUES, levels }, ORIGINAL).levels?.map(
        (level) => level.id,
      ),
    ).toEqual([11, 10]);
  });

  it("sends only the changed name", () => {
    expect(
      buildUpdatePayload({ ...VALUES, name: "Terziario" }, ORIGINAL),
    ).toEqual({ name: "Terziario" });
  });
});
