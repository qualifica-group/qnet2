import { fetchForSelect } from "@/features/for-select/api";
import { useForSelect } from "@/features/for-select/use-for-select";
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from "@/features/for-select/types";

/**
 * Resource segment for the levels for-select endpoint (`GET /api/collective-agreement-levels/for-select`).
 * Labels read "CCNL name – level code".
 */
export const COLLECTIVE_AGREEMENT_LEVELS_FOR_SELECT_RESOURCE =
  "collective-agreement-levels";

/** Fetches a page of level options. */
export function fetchCollectiveAgreementLevelsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(
    COLLECTIVE_AGREEMENT_LEVELS_FOR_SELECT_RESOURCE,
    params,
  );
}

interface UseCollectiveAgreementLevelsForSelectOptions {
  search: string;
  ids?: number[];
  enabled?: boolean;
}

/** Hook feeding a level single-select: debounced search, pagination and `ids[]` hydration. */
export function useCollectiveAgreementLevelsForSelect({
  search,
  ids,
  enabled,
}: UseCollectiveAgreementLevelsForSelectOptions) {
  return useForSelect({
    resource: COLLECTIVE_AGREEMENT_LEVELS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  });
}
