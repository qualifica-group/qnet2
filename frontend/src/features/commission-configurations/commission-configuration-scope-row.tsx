import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { OptionSelect, SecondaryFieldError } from './commission-configuration-controls'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import { useRelationLabels } from './use-relation-labels'
import { COMMISSION_SCOPES, type CommissionConfigurationRefs } from './types'

interface CommissionConfigurationScopeRowProps {
  inline: InlineEdit
  canEdit: boolean
  refs: CommissionConfigurationRefs
}

/**
 * The scope and the categories/products it points at are ONE row: the server
 * accepts a scope only together with the relation it needs (and none of the
 * other), so they save together.
 */
export function CommissionConfigurationScopeRow({ inline, canEdit, refs }: CommissionConfigurationScopeRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CommissionConfigurationFormValues>()
  const scope = useWatch({ control, name: 'application_scope' })
  const relationLabels = useRelationLabels()

  return (
    <RecordInlineField
      field="application_scope"
      label={t('commissionConfigurations.form.application_scope')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <div className="grid gap-3 sm:grid-cols-2">
          <OptionSelect name="application_scope" options={COMMISSION_SCOPES} />
          {scope === 'PRODUCT_CATEGORY' ? (
            <RelationMultiSelectField
              control={control}
              name="product_category_ids"
              metaKey="product_category_ids"
              label={t('commissionConfigurations.form.product_category_ids')}
              resource={PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('commissionConfigurations.form.searchCategory')}
              description={t('commissionConfigurations.form.categoryBranchHint')}
              selected={refs.categories}
              placeholder={relationLabels.placeholder}
              emptyLabel={relationLabels.emptyLabel}
              errorLabel={relationLabels.errorLabel}
              retryLabel={relationLabels.retryLabel}
              removeLabel={t('common.remove')}
            />
          ) : null}
          {scope === 'PRODUCT' ? (
            <RelationMultiSelectField
              control={control}
              name="product_ids"
              metaKey="product_ids"
              label={t('commissionConfigurations.form.product_ids')}
              resource={PRODUCTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('commissionConfigurations.form.searchProduct')}
              selected={refs.products}
              placeholder={relationLabels.placeholder}
              emptyLabel={relationLabels.emptyLabel}
              errorLabel={relationLabels.errorLabel}
              retryLabel={relationLabels.retryLabel}
              removeLabel={t('common.remove')}
            />
          ) : null}
        </div>
      }
    >
      <div className="flex flex-col gap-0.5">
        <span>{t(`commissionConfigurations.options.application_scope.${scope}`)}</span>
        {scope === 'PRODUCT' ? (
          <span className="flex flex-wrap gap-x-1.5 text-muted-foreground">
            {refs.products.length > 0 ? (
              refs.products.map((product, index) => (
                <span key={product.id}>
                  <RecordLink domain="products" id={product.id}>
                    {product.name}
                  </RecordLink>
                  {index < refs.products.length - 1 ? ',' : null}
                </span>
              ))
            ) : (
              <DetailEmpty />
            )}
          </span>
        ) : null}
        {scope === 'PRODUCT_CATEGORY' ? (
          <span className="text-muted-foreground">
            {refs.categories.length > 0 ? refs.categories.map((category) => category.name).join(', ') : <DetailEmpty />}
          </span>
        ) : null}
      </div>
      <SecondaryFieldError name="product_category_ids" />
      <SecondaryFieldError name="product_ids" />
    </RecordInlineField>
  )
}
