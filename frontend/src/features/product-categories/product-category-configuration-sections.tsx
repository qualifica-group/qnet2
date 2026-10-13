import type { ReactNode } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ListChecks, Users } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProductCategoryAttributesEditor } from '@/features/product-categories/product-category-attributes-editor'
import { ProductCategoryManagerLabelsEditor } from '@/features/product-categories/product-category-manager-labels-editor'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ConfigurationSectionProps {
  form: UseFormReturn<ProductCategoryFormValues>
  inline: InlineEdit
  mode: ProductCategoryFormMode
  canEdit: boolean
  /**
   * The persisted value the detail's block row shows until the pencil opens the
   * editor. Absent on the create form, where the editor stays open: it is an
   * editor rather than a field (spec 0229 D-5).
   */
  overview?: ReactNode
}

interface BlockSectionProps extends ConfigurationSectionProps {
  title: string
  icon: ReactNode
  field: string
  editor: ReactNode
}

/** A full-width section holding ONE editor: a block row on the detail, the open editor on the create form. */
function BlockSection({ title, icon, field, inline, canEdit, overview, editor }: BlockSectionProps) {
  return (
    <RecordSection title={title} icon={icon} full>
      {overview === undefined ? (
        // The section title already names the editor: its field label stays for assistive tech only.
        <MetaFieldRowContext.Provider value={field}>{editor}</MetaFieldRowContext.Provider>
      ) : (
        <RecordInlineField field={field} label={title} layout="block" inline={inline} canEdit={canEdit} editor={editor}>
          {overview}
        </RecordInlineField>
      )}
    </RecordSection>
  )
}

/** "Attributi": the own assignments of the three usage contexts and their inheritance barriers, as one block row. */
export function ProductCategoryAttributesRecordSection(props: ConfigurationSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { control } = props.form
  const parentId = useWatch({ control, name: 'parent_id' })

  if (!fieldPermission('attributes').visible) {
    return null
  }

  return (
    <BlockSection
      {...props}
      title={t('productCategories.form.sections.attributes.title')}
      icon={<ListChecks />}
      field="attributes"
      editor={<ProductCategoryAttributesEditor control={control} mode={props.mode} parentId={parentId} />}
    />
  )
}

/** "Denominazioni G.A.": the per-level manager labels and their inheritance barrier, as one block row. */
export function ProductCategoryManagerLabelsRecordSection(props: ConfigurationSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { control } = props.form
  const parentId = useWatch({ control, name: 'parent_id' })

  if (!fieldPermission('manager_labels').visible) {
    return null
  }

  return (
    <BlockSection
      {...props}
      title={t('productCategories.form.sections.managerLabels.title')}
      icon={<Users />}
      field="manager_labels"
      editor={<ProductCategoryManagerLabelsEditor control={control} parentId={parentId} />}
    />
  )
}
