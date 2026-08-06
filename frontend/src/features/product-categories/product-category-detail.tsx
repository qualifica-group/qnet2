import { useTranslation } from 'react-i18next'
import { FolderTree, History } from 'lucide-react'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { CategoryAttributesContextSection } from '@/features/product-categories/product-category-detail-attributes'
import { ProductCategoryAttributeLayoutPreview } from '@/features/product-categories/product-category-attribute-layout-preview'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'

interface ProductCategoryDetailViewProps {
  category: ProductCategoryDetailWithPermissions
}

interface ResolvedManagerLabel {
  position: number
  label: string
  inherited: boolean
}

/**
 * Merges own + inherited manager labels for display, own winning per position
 * (spec 0080), skipping positions with neither. Positions are no longer
 * bounded to a fixed 1..4 range (spec 0080 A1): the union of whatever
 * positions either map carries drives the list, sorted ascending.
 */
function resolveManagerLabels(category: ProductCategoryDetailWithPermissions): ResolvedManagerLabel[] {
  const positions = new Set([
    ...Object.keys(category.manager_labels),
    ...Object.keys(category.inherited_manager_labels),
  ])
  return [...positions]
    .map(Number)
    .sort((a, b) => a - b)
    .map((position): ResolvedManagerLabel | null => {
      const key = String(position)
      const own = category.manager_labels[key]
      if (own) {
        return { position, label: own, inherited: false }
      }
      const fromAncestor = category.inherited_manager_labels[key]
      return fromAncestor ? { position, label: fromAncestor, inherited: true } : null
    })
    .filter((entry): entry is ResolvedManagerLabel => entry !== null)
}

/**
 * Read-only detail of a single product category. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down (mirrors `AttributeDetailView`/`ProductDetailView`). Attributes (spec
 * 0061: own assignments + what the category inherits) render as TWO
 * context-scoped sections — "Attributi Prodotto" / "Attributi Opportunità" —
 * the same split `AttributeAssignmentEditor` uses in the form, so a category
 * with attributes assigned to both contexts never shows them merged.
 */
export function ProductCategoryDetailView({ category }: ProductCategoryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(category.created_at)
  const resolvedManagerLabels = resolveManagerLabels(category)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={category.name} icon={<FolderTree />} />}
        title={category.name}
        subtitle={category.parent?.name}
      />

      {category.description && (
        <DetailSection title={t('productCategories.form.description')}>
          <DetailGrid>
            <DetailField label={t('productCategories.form.description')} full>
              {category.description}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      {category.effective_business_function && (
        <DetailSection title={t('productCategories.form.businessFunction')}>
          <DetailGrid>
            <DetailField label={t('productCategories.form.businessFunction')}>
              <div className="flex flex-wrap items-center gap-2">
                <span>{category.effective_business_function.name}</span>
                {category.effective_business_function.inherited &&
                  category.effective_business_function.source_category && (
                    <Badge variant="outline" className="text-xs">
                      {t('productCategories.detail.businessFunctionInherited', {
                        category: category.effective_business_function.source_category.name,
                      })}
                    </Badge>
                  )}
              </div>
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      <DetailSection title={t('productCategories.form.requiresQuote')}>
        <DetailGrid>
          <DetailField label={t('productCategories.form.requiresQuote')}>
            <div className="flex flex-wrap items-center gap-2">
              <span>{category.requires_quote ? t('common.yes') : t('common.no')}</span>
              {category.requires_quote_source_category && (
                <Badge variant="outline" className="text-xs">
                  {t('productCategories.detail.requiresQuoteInherited', {
                    category: category.requires_quote_source_category.name,
                  })}
                </Badge>
              )}
            </div>
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('productCategories.form.managementMode')}>
        <DetailGrid>
          <DetailField label={t('productCategories.form.managementMode')}>
            <div className="flex flex-wrap items-center gap-2">
              <span>
                {category.management_mode === 'single'
                  ? t('productCategories.form.managementModeSingle')
                  : t('productCategories.form.managementModeMultiple')}
              </span>
              {category.management_mode_source_category && (
                <Badge variant="outline" className="text-xs">
                  {t('productCategories.detail.managementModeInherited', {
                    category: category.management_mode_source_category.name,
                  })}
                </Badge>
              )}
            </div>
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('productCategories.form.isSelectable')}>
        <DetailGrid>
          <DetailField label={t('productCategories.form.isSelectable')}>
            <span>{category.is_selectable ? t('common.yes') : t('common.no')}</span>
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {resolvedManagerLabels.length > 0 && (
        <DetailSection title={t('productCategories.form.sections.managerLabels.title')}>
          <DetailGrid>
            {resolvedManagerLabels.map(({ position, label, inherited }) => (
              <DetailField key={position} label={t('productCategories.form.managerLabelLevel', { n: position })}>
                <div className="flex flex-wrap items-center gap-2">
                  <span>{label}</span>
                  {inherited && (
                    <Badge variant="outline" className="text-xs">
                      {t('productCategories.detail.managerLabelInherited')}
                    </Badge>
                  )}
                </div>
              </DetailField>
            ))}
          </DetailGrid>
        </DetailSection>
      )}

      <CategoryAttributesContextSection
        title={t('productCategories.form.sections.productAttributes.title')}
        description={t('productCategories.form.sections.productAttributes.description')}
        own={category.attributes.filter((attribute) => attribute.context === 'product')}
        inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'product')}
      />

      <CategoryAttributesContextSection
        title={t('productCategories.form.sections.opportunityAttributes.title')}
        description={t('productCategories.form.sections.opportunityAttributes.description')}
        own={category.attributes.filter((attribute) => attribute.context === 'opportunity')}
        inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'opportunity')}
      />

      <CategoryAttributesContextSection
        title={t('productCategories.form.sections.quoteAttributes.title')}
        description={t('productCategories.form.sections.quoteAttributes.description')}
        own={category.attributes.filter((attribute) => attribute.context === 'quote')}
        inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'quote')}
      />

      <ProductCategoryAttributeLayoutPreview categoryId={category.id} />

      {category.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="product-categories" id={category.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('productCategories.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
