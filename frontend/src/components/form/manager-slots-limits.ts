/**
 * Shared G.A. slot ceiling (spec 0080 amendment A1, decision 2): a fixed
 * safety cap, independent of any Product Category's own configured level
 * count, mirroring the backend's `ValidatesManagerSlots::MAX_MANAGER_SLOTS`
 * (already 12 pre-amendment — not a new number). Kept in its own
 * dependency-free module, not `manager-slots-field.tsx` itself: the two
 * schema files that need only the number (`opportunity-schema.ts`,
 * `registry-schema.ts`) must not pull in the component's own UI dependencies
 * (icons, `Button`, `AsyncPaginatedSelect`) just to read a constant.
 */
export const MAX_MANAGER_SLOTS = 12
