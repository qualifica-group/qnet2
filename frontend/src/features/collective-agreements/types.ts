/**
 * Collective agreements (CCNL) CRUD types. Source of truth: the frozen
 * `data_contract` of spec 0272 (HR employee record). An agreement owns an
 * ordered list of levels; the array sent on save REPLACES them.
 */

import type { ResourcePermissions } from "@/features/authorization/types";

/** Domain key shared by the table, the module registry and the ability checks. */
export const COLLECTIVE_AGREEMENTS_DOMAIN = "collective-agreements";

/** A level as returned by GET/POST/PATCH /collective-agreements. */
export interface CollectiveAgreementLevel {
  id: number;
  code: string;
  description: string | null;
  sort_order: number;
  contracts_count: number;
}

/** Single agreement returned by the endpoint (envelope `data`). */
export interface CollectiveAgreementDetail {
  id: number;
  name: string;
  code: string | null;
  levels: CollectiveAgreementLevel[];
  created_at: string;
  updated_at: string;
  abilities: { update: boolean; delete: boolean };
}

/** A `CollectiveAgreementDetail` carrying the authorization metadata the detail's provider reads. */
export interface CollectiveAgreementDetailWithPermissions extends CollectiveAgreementDetail {
  permissions: ResourcePermissions;
}

/** A level in a request: `id` present keeps the existing level, absent creates one. */
export interface CollectiveAgreementLevelPayload {
  id?: number;
  code: string;
  description: string | null;
}

/** Payload for POST /collective-agreements. */
export interface CollectiveAgreementPayload {
  name: string;
  code: string | null;
  levels: CollectiveAgreementLevelPayload[];
}

/** Payload for PATCH /collective-agreements/{id}: only the fields that changed. */
export type UpdateCollectiveAgreementPayload =
  Partial<CollectiveAgreementPayload>;

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type CollectiveAgreementFormMode =
  | { type: "create" }
  | {
      type: "edit";
      collectiveAgreement: CollectiveAgreementDetailWithPermissions;
    };
