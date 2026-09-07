import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Users } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  ManagerLabelEditor,
  ManagerLabelsInheritanceToggle,
} from '@/features/product-categories/manager-label-editor'
import { useEffectiveManagerLabels } from '@/features/product-categories/use-effective-manager-labels'
import type { ManagerLabels } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Hoisted so an opted-out (or root) manager-labels barrier feeds a stable, empty reference. */
const EMPTY_MANAGER_LABELS: ManagerLabels = {}

interface ProductCategoryManagerLabelsSectionProps {
  control: Control<ProductCategoryFormValues>
  /** Current watched `parent_id`: the inherited denominations come from the SELECTED parent. */
  parentId: number | null
}

/**
 * The category's G.A. denominations (spec 0080): its own overrides plus what
 * the ancestry provides. Same immediate-barrier behavior as the attribute
 * contexts — turning the inheritance switch off drops the inherited preview
 * before the save round-trips.
 */
export function ProductCategoryManagerLabelsSection({
  control,
  parentId,
}: ProductCategoryManagerLabelsSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const inheritsManagerLabels = useWatch({ control, name: 'inherits_manager_labels' })
  const inheritedQuery = useEffectiveManagerLabels(parentId)

  const inherited = inheritsManagerLabels
    ? (inheritedQuery.data ?? EMPTY_MANAGER_LABELS)
    : EMPTY_MANAGER_LABELS

  if (!fieldPermission('manager_labels').visible) {
    return null
  }

  return (
    <FormSection
      icon={Users}
      title={t('productCategories.form.sections.managerLabels.title')}
      description={t('productCategories.form.sections.managerLabels.description')}
    >
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
    </FormSection>
  )
}
