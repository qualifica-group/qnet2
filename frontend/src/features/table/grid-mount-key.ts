/**
 * Key of the mounted `DataTable`: it changes on a layout/filter reset (`layoutVersion`) and when the set of categories
 * whose `attr.*` columns the config carries changes (spec 0374 D-5), because AG Grid builds its columns once at mount.
 * A filter change that keeps the same list leaves the key alone, so the grid is not rebuilt for it.
 */
export function gridMountKey(layoutVersion: number, attributeCategoryIds: readonly number[] | undefined): string {
  return `${layoutVersion}:${(attributeCategoryIds ?? []).join('-')}`
}
