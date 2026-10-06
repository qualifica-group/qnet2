import type { FieldPermission } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/*
 * Ordering and grouping of a resource's custom fields, shared by the form
 * (`CustomFieldsSection`) and the record detail (`CustomFieldRecordSections`),
 * so both show the same blocks in the same order.
 */

/** Sort key: tab, then group, then the admin-defined `sort_order` — `tab` only affects ordering (no tabs UI in this generic engine). */
function sortKey(descriptor: CustomFieldDescriptor): [string, string, number] {
  return [descriptor.tab ?? '', descriptor.group ?? '', descriptor.sort_order ?? 0]
}

/** The visible descriptors, ordered by (tab, group, sort_order). */
export function sortVisibleCustomFields(
  descriptors: CustomFieldDescriptor[],
  fieldPermission: (key: string) => FieldPermission,
): CustomFieldDescriptor[] {
  return [...descriptors]
    .filter((descriptor) => fieldPermission(descriptor.key).visible)
    .sort((a, b) => {
      const [aTab, aGroup, aOrder] = sortKey(a)
      const [bTab, bGroup, bOrder] = sortKey(b)
      return aTab.localeCompare(bTab) || aGroup.localeCompare(bGroup) || aOrder - bOrder
    })
}

/** Groups already-sorted descriptors by their `group` label; `null` = ungrouped. */
export function groupByLabel(
  fields: CustomFieldDescriptor[],
): Map<string | null, CustomFieldDescriptor[]> {
  const groups = new Map<string | null, CustomFieldDescriptor[]>()
  for (const descriptor of fields) {
    const key = descriptor.group ?? null
    const bucket = groups.get(key)
    if (bucket) {
      bucket.push(descriptor)
    } else {
      groups.set(key, [descriptor])
    }
  }
  return groups
}
