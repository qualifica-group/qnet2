/** Centralized query keys for the `rewarded-referents` feature (mirrors `request-management/query-keys.ts`). */
export const rewardedReferentsKeys = {
  /** Query key of one Referent's reward list (the master/detail lazy load). */
  rewards: (referentId: number) => ['rewarded-referents', 'rewards', referentId] as const,
}
