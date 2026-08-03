/**
 * Ability suffixes rendered inline in every module's action list. Anything
 * else (`export`, `import`, …) collapses into that module's "advanced
 * configuration" disclosure so the common CRUD abilities stay scannable at a
 * glance. A presentation choice (spec 0076 constraints), not domain data —
 * kept out of any component.
 */
export const PRIMARY_ABILITIES: readonly string[] = ['viewAny', 'view', 'create', 'update', 'delete']
