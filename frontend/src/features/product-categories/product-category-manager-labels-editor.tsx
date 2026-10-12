import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { MetaField } from '@/features/authorization/MetaField'
import {
  ManagerLabelEditor,
  ManagerLabelsInheritanceToggle,
} from '@/features/product-categories/manager-label-editor'
import { useEffectiveManagerLabels } from '@/features/product-categories/use-effective-manager-labels'
import type { ManagerLabels } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Hoisted so an opted-out (or root) manager-labels barrier feeds a stable, empty reference. */
const EMPTY_MANAGER_LABELS: ManagerLabels = {}

interface ProductCategoryManagerLabelsEditorProps {
  control: Control<ProductCategoryFormValues>
  /** Current watched `parent_id`: the inherited denominations come from the SELECTED parent. */
  parentId: number | null
}

/**
 * The category's G.A. denominations editor (spec 0080): its own overrides plus
 * what the ancestry provides. Same immediate-barrier behavior as the attribute
 * contexts — turning the inheritance switch off drops the inherited preview
 * before the save round-trips. The detail's block row and the create form's
 * open section both render it (spec 0229 D-5).
 */
export function ProductCategoryManagerLabelsEditor({
  control,
  parentId,
}: ProductCategoryManagerLabelsEditorProps) {
  const { t } = useTranslation()
  const inheritsManagerLabels = useWatch({ control, name: 'inherits_manager_labels' })
  const inheritedQuery = useEffectiveManagerLabels(parentId)

  const inherited = inheritsManagerLabels
    ? (inheritedQuery.data ?? EMPTY_MANAGER_LABELS)
    : EMPTY_MANAGER_LABELS

  return (
    <MetaField
      control={control}
      name="manager_labels"
      metaKey="manager_labels"
      label={t('productCategories.form.sections.managerLabels.title')}
    >
      {({ field, disabled }) => (
        <ManagerLabelEditor
          value={field.value}
          onChange={field.onChange}
          inherited={inherited}
          disabled={disabled}
          // A root category has no ancestry to inherit from: no switch to show.
          inheritToggle={
            parentId !== null ? <ManagerLabelsInheritanceToggle control={control} /> : null
          }
        />
      )}
    </MetaField>
  )
}
