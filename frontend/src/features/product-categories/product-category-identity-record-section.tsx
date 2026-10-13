import { useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FolderTree } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { ProductCategoryBusinessFunctionField } from '@/features/product-categories/product-category-business-function-field'
import {
  ProductCategoryDescriptionField,
  ProductCategoryNameField,
  ProductCategoryParentField,
} from '@/features/product-categories/product-category-fields'
import type { ProductCategoryRecordSectionProps } from '@/features/product-categories/product-category-record-props'
import { RuleValue } from '@/features/product-categories/product-category-rule-values'
import { useProductCategoryParentPicker } from '@/features/product-categories/use-product-category-parent-picker'

/**
 * "Dettagli": name, parent, description and the business function, one row each
 * (spec 0229 D-2). The business function is read-only while an ancestor owns it
 * (spec 0023): the row then only names the category it comes from.
 */
export function ProductCategoryIdentityRecordSection({ values, form, inline, mode, canEdit }: ProductCategoryRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const parentId = useWatch({ control, name: 'parent_id' })
  const parents = useProductCategoryParentPicker(mode.type === 'edit' ? mode.category.id : undefined)
  const businessFunction = values.effective_business_function

  return (
    <RecordSection title={t('productCategories.form.sections.identity.title')} icon={<FolderTree />}>
      <RecordFieldList>
        <RecordInlineField
          field="name"
          label={t('productCategories.form.name')}
          inline={inline}
          canEdit={canEdit}
          editor={<ProductCategoryNameField control={control} />}
        >
          {values.name.trim() !== '' ? <span className="break-words">{values.name}</span> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="parent_id"
          label={t('productCategories.form.parent')}
          inline={inline}
          canEdit={canEdit}
          editor={<ProductCategoryParentField control={control} parents={parents} />}
        >
          {values.parent?.name ?? t('productCategories.badges.root')}
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('productCategories.form.description')}
          inline={inline}
          canEdit={canEdit}
          editor={<ProductCategoryDescriptionField control={control} />}
        >
          {values.description ? <span className="break-words whitespace-pre-wrap">{values.description}</span> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="business_function_id"
          label={t('productCategories.form.businessFunction')}
          inline={inline}
          canEdit={canEdit && !businessFunction?.inherited}
          editor={<ProductCategoryBusinessFunctionField control={control} mode={mode} parentId={parentId} />}
        >
          {businessFunction ? (
            <RuleValue
              value={businessFunction.name}
              inheritedFrom={businessFunction.inherited ? businessFunction.source_category?.name : undefined}
              inheritedLabel={t('productCategories.detail.businessFunctionInherited', {
                category: businessFunction.source_category?.name ?? '',
              })}
            />
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
