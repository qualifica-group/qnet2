import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { AttributeSectionEditDialog } from '@/features/attributes/attribute-section-edit-dialog'
import type { AttributeLayoutFormShape, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** No attribute is read-only in the popup: hoisted for a stable default reference. */
const NO_READ_ONLY_CODES: ReadonlySet<string> = new Set()

interface UseAttributeSectionEditArgs<TFieldValues extends AttributeLayoutFormShape> {
  /** Off on a create draft, whose rows are all filled before the single save. */
  enabled: boolean
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  readOnlyCodes?: ReadonlySet<string>
  control: Control<TFieldValues>
  inline: InlineEdit
}

/**
 * The `AttributeLayoutView` section action of an Offerta/Commessa detail: the
 * whole-section edit, offered only when the operator may edit the record's
 * flexible fields (the single `attribute_values` field permission, as their
 * rows) and the section has at least one field to change.
 */
export function useAttributeSectionEditAction<TFieldValues extends AttributeLayoutFormShape>({
  enabled,
  attributesByCode,
  readOnlyCodes = NO_READ_ONLY_CODES,
  control,
  inline,
}: UseAttributeSectionEditArgs<TFieldValues>): ((section: LayoutSection) => ReactNode) | undefined {
  const permission = useResourcePermissions().field('attribute_values')

  if (!enabled || !permission.editable || permission.disabled) {
    return undefined
  }

  return (section) => {
    const editable = section.rows.some((row) =>
      row.items.some((item) => attributesByCode.has(item.attribute_code) && !readOnlyCodes.has(item.attribute_code)),
    )
    return editable ? (
      <AttributeSectionEditDialog
        section={section}
        attributesByCode={attributesByCode}
        readOnlyCodes={readOnlyCodes}
        control={control}
        inline={inline}
      />
    ) : null
  }
}
