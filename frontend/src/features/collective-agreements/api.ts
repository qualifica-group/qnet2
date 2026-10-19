import { apiClient } from "@/api/client";
import type { ApiResponse, ApiResponseWithPermissions } from "@/api/types";
import type { ResourcePermissions } from "@/features/authorization/types";
import {
  COLLECTIVE_AGREEMENTS_DOMAIN,
  type CollectiveAgreementDetail,
  type CollectiveAgreementDetailWithPermissions,
  type CollectiveAgreementPayload,
  type UpdateCollectiveAgreementPayload,
} from "@/features/collective-agreements/types";

const ENDPOINT = `/${COLLECTIVE_AGREEMENTS_DOMAIN}`;

/** Centralized query keys of the collective-agreements feature. */
export const collectiveAgreementKeys = {
  all: [COLLECTIVE_AGREEMENTS_DOMAIN] as const,
  detail: (id: number) => [COLLECTIVE_AGREEMENTS_DOMAIN, "detail", id] as const,
};

/** Permissions derived from the instance abilities, used when the envelope carries none. */
function permissionsFromAbilities(
  abilities: CollectiveAgreementDetail["abilities"],
): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: false,
      update: abilities.update,
      delete: abilities.delete,
      export: false,
      import: false,
    },
    fields: {},
    actions: {},
  };
}

/** Fetches a collective agreement together with the actor's authorization metadata for it. */
export async function fetchCollectiveAgreement(
  id: number,
): Promise<CollectiveAgreementDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<
      CollectiveAgreementDetail,
      ResourcePermissions | undefined
    >
  >(`${ENDPOINT}/${id}`);
  return {
    ...data.data,
    permissions:
      data.permissions ?? permissionsFromAbilities(data.data.abilities),
  };
}

export async function createCollectiveAgreement(
  payload: CollectiveAgreementPayload,
): Promise<CollectiveAgreementDetail> {
  const { data } = await apiClient.post<ApiResponse<CollectiveAgreementDetail>>(
    ENDPOINT,
    payload,
  );
  return data.data;
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateCollectiveAgreement(
  id: number,
  payload: UpdateCollectiveAgreementPayload,
): Promise<CollectiveAgreementDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<
      CollectiveAgreementDetail,
      ResourcePermissions | undefined
    >
  >(`${ENDPOINT}/${id}`, payload);
  return {
    ...data.data,
    permissions:
      data.permissions ?? permissionsFromAbilities(data.data.abilities),
  };
}

/** 409 when the collective agreement still has a level in use: the caller maps it. */
export async function deleteCollectiveAgreement(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`);
}
