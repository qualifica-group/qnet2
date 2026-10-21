import { fetchForSelect } from "@/features/for-select/api";
import { useForSelect } from "@/features/for-select/use-for-select";
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from "@/features/for-select/types";

/**
 * Resource segment for the contract types for-select endpoint
 * (`GET /api/contract-types/for-select`): active types only, `ids[]` also
 * hydrates deactivated ones.
 */
export const CONTRACT_TYPES_FOR_SELECT_RESOURCE = "contract-types";

/** Fetches a page of contract type options. */
export function fetchContractTypesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(CONTRACT_TYPES_FOR_SELECT_RESOURCE, params);
}

interface UseContractTypesForSelectOptions {
  search: string;
  ids?: number[];
  enabled?: boolean;
}

/** Hook feeding a contract type single-select: debounced search, pagination and `ids[]` hydration. */
export function useContractTypesForSelect({
  search,
  ids,
  enabled,
}: UseContractTypesForSelectOptions) {
  return useForSelect({
    resource: CONTRACT_TYPES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  });
}
