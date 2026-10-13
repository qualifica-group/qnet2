import { useTranslation } from 'react-i18next'
import { Briefcase, ListChecks, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordMeta,
  RecordSectionsGrid,
  RecordStat,
  RecordStatStrip,
} from '@/components/detail/record-panel'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import { BusinessFunctionResetDialog } from '@/features/product-categories/business-function-reset-dialog'
import { ProductCategoryAttributeLayoutPreview } from '@/features/product-categories/product-category-attribute-layout-preview'
import {
  ProductCategoryAttributesRecordSection,
  ProductCategoryManagerLabelsRecordSection,
} from '@/features/product-categories/product-category-configuration-sections'
import { ProductCategoryAttributesOverview } from '@/features/product-categories/product-category-detail-attributes'
import { ProductCategoryIdentityBand } from '@/features/product-categories/product-category-identity-band'
import { ProductCategoryIdentityRecordSection } from '@/features/product-categories/product-category-identity-record-section'
import { ProductCategoryManagerLabelsOverview } from '@/features/product-categories/product-category-manager-labels-overview'
import { resolveManagerLabels } from '@/features/product-categories/resolve-manager-labels'
import { ProductCategoryRulesRecordSection } from '@/features/product-categories/product-category-rules-record-section'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'
import { useProductCategoryInlineEdit } from '@/features/product-categories/use-product-category-inline-edit'

/** Three KPI tiles, not the strip's default four: a category is read on exactly these. */
const STAT_STRIP_CLASS = '@2xl:grid-cols-3'

interface ProductCategoryDetailViewProps {
  category: ProductCategoryDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the category (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single product category, rendered as the same enterprise-CRM
 * record as Opportunita' and Prodotti (`@/components/detail/record-panel` + the
 * shared `record-layout` grid): identity band, a KPI strip answering what
 * governs the category, the spec-sheet sections (dettagli, G.A. denominations,
 * the behavioural rules, the attributes of the three usage contexts, custom
 * fields), then the layout preview; the activity log takes the side column
 * when the actor may read it.
 *
 * There is no edit page (spec 0229): every row edits IN PLACE, one at a time
 * (`useProductCategoryInlineEdit`), each save a PATCH of what that row changed.
 * Attributes and G.A. denominations are ONE full-width row each, whose pencil
 * opens the very editor the create form shows.
 */
export function ProductCategoryDetailView(props: ProductCategoryDetailViewProps) {
  // The editors read the field permissions while they render, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.category.permissions}>
      <ProductCategoryDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ProductCategoryDetailContent({ category, onChanged }: ProductCategoryDetailViewProps) {
  const { t } = useTranslation()
  const { form, inline, mode, businessFunctionReset } = useProductCategoryInlineEdit(category, onChanged)
  const createdAt = formatDateTime(category.created_at)
  const canEdit = category.permissions.resource.update
  const collaborationTabs = category.permissions.actions.view_activity
    ? [activityLogTab('product-categories', category.id, t('activityLog.title'))]
    : []
  const effectiveBusinessFunction = category.effective_business_function
  const inheritedAttributesCount = category.inherited_attributes.length
  const sectionProps = { values: category, form, inline, mode, canEdit }

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <ProductCategoryIdentityBand
              values={category}
              legacyId={category.old_id}
              subtitle={category.parent?.name}
            />

            <RecordStatStrip className={STAT_STRIP_CLASS}>
              <RecordStat
                label={t('productCategories.form.businessFunction')}
                icon={<Briefcase aria-hidden="true" />}
                value={effectiveBusinessFunction?.name ?? <DetailEmpty />}
                hint={
                  effectiveBusinessFunction?.inherited && effectiveBusinessFunction.source_category
                    ? t('productCategories.detail.businessFunctionInherited', {
                        category: effectiveBusinessFunction.source_category.name,
                      })
                    : undefined
                }
              />
              <RecordStat
                label={t('productCategories.form.attributes')}
                icon={<ListChecks aria-hidden="true" />}
                value={category.attributes.length}
                hint={
                  inheritedAttributesCount > 0
                    ? t('productCategories.detail.inheritedCount', { count: inheritedAttributesCount })
                    : undefined
                }
              />
              <RecordStat
                label={t('productCategories.form.sections.managerLabels.title')}
                icon={<Users aria-hidden="true" />}
                value={resolveManagerLabels(category).length}
              />
            </RecordStatStrip>
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <ProductCategoryIdentityRecordSection {...sectionProps} />
            <ProductCategoryManagerLabelsRecordSection
              {...sectionProps}
              overview={<ProductCategoryManagerLabelsOverview category={category} />}
            />
            <ProductCategoryRulesRecordSection {...sectionProps} />
            <ProductCategoryAttributesRecordSection
              {...sectionProps}
              overview={<ProductCategoryAttributesOverview category={category} />}
            />
            <CustomFieldRecordSections
              resource="product-categories"
              values={category.custom_fields ?? {}}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>

        <ProductCategoryAttributeLayoutPreview categoryId={category.id} />
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('productCategories.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}

      <BusinessFunctionResetDialog
        categories={businessFunctionReset.pendingCategories}
        isConfirming={businessFunctionReset.isConfirming}
        onConfirm={() => void businessFunctionReset.confirm()}
        onCancel={businessFunctionReset.cancel}
      />
    </RecordCanvas>
  )
}
