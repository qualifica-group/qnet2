import { useTranslation } from 'react-i18next'
import { Briefcase, EyeOff, FolderTree, History, ListChecks, Users } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
  RecordStat,
  RecordStatStrip,
} from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { CategoryAttributesContextSection } from '@/features/product-categories/product-category-detail-attributes'
import { ProductCategoryDetailRules } from '@/features/product-categories/product-category-detail-rules'
import { ProductCategoryAttributeLayoutPreview } from '@/features/product-categories/product-category-attribute-layout-preview'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'

/** Three KPI tiles, not the strip's default four: a category is read on exactly these. */
const STAT_STRIP_CLASS = '@2xl:grid-cols-3'

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
 * Read-only detail of a single product category, rendered as the same
 * enterprise-CRM record as Opportunita' and Prodotti
 * (`@/components/detail/record-panel` + the shared `record-layout` grid):
 * identity band, a KPI strip answering what governs the category, the
 * spec-sheet sections (anagrafica, the behavioural rules, the G.A.
 * denominations), then the per-context attribute cards and the layout
 * preview; the activity log takes the side column when the actor may read it,
 * and the record keeps the full width when they may not.
 *
 * Purely presentational: the caller (the generic detail page, or the module
 * Sheet) fetches the fresh detail and passes it down. Attributes (spec 0061:
 * own assignments + what the category inherits) stay split into three
 * context-scoped cards — "Attributi Prodotto" / "Attributi Offerta" /
 * "Attributi Commessa" — the same split `AttributeAssignmentEditor` uses in
 * the form, so a category with attributes in several contexts never shows
 * them merged.
 */
export function ProductCategoryDetailView({ category }: ProductCategoryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(category.created_at)
  const canViewActivity = category.permissions.actions.view_activity === true
  const resolvedManagerLabels = resolveManagerLabels(category)
  const effectiveBusinessFunction = category.effective_business_function
  const inheritedAttributesCount = category.inherited_attributes.length

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <RecordCardHeader
              media={
                <DetailMonogram
                  name={category.name}
                  icon={<FolderTree />}
                  className="size-10 text-base [&>svg]:size-5"
                />
              }
              title={category.name}
              subtitle={category.parent?.name}
              badges={
                <>
                  {category.parent === null ? (
                    <Badge variant="outline">{t('productCategories.badges.root')}</Badge>
                  ) : null}
                  {/* A pure container is not otherwise visible without
                      scrolling down to the rules, and it changes where the
                      category may be used at all. */}
                  {!category.is_selectable ? (
                    <Badge variant="outline">
                      <EyeOff aria-hidden="true" />
                      {t('productCategories.badges.notSelectable')}
                    </Badge>
                  ) : null}
                </>
              }
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
                value={resolvedManagerLabels.length}
              />
            </RecordStatStrip>

            <RecordSectionsGrid>
              <RecordSection
                title={t('productCategories.form.sections.identity.title')}
                icon={<FolderTree />}
              >
                <RecordFieldList>
                  <RecordField label={t('productCategories.form.parent')}>
                    {category.parent?.name ?? t('productCategories.badges.root')}
                  </RecordField>
                  <RecordField label={t('productCategories.form.description')}>
                    {category.description ?? <DetailEmpty />}
                  </RecordField>
                </RecordFieldList>
              </RecordSection>

              {resolvedManagerLabels.length > 0 && (
                <RecordSection
                  title={t('productCategories.form.sections.managerLabels.title')}
                  icon={<Users />}
                >
                  <RecordFieldList>
                    {resolvedManagerLabels.map(({ position, label, inherited }) => (
                      <RecordField
                        key={position}
                        label={t('productCategories.form.managerLabelLevel', { n: position })}
                      >
                        <div className="flex flex-wrap items-center gap-2">
                          <span>{label}</span>
                          {inherited && (
                            <Badge variant="outline" className="text-xs">
                              {t('productCategories.detail.managerLabelInherited')}
                            </Badge>
                          )}
                        </div>
                      </RecordField>
                    ))}
                  </RecordFieldList>
                </RecordSection>
              )}

              <ProductCategoryDetailRules category={category} />
            </RecordSectionsGrid>
          </RecordCard>

          <CategoryAttributesContextSection
            title={t('productCategories.form.sections.productAttributes.title')}
            description={t('productCategories.form.sections.productAttributes.description')}
            own={category.attributes.filter((attribute) => attribute.context === 'product')}
            inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'product')}
          />

          <CategoryAttributesContextSection
            title={t('productCategories.form.sections.quoteAttributes.title')}
            description={t('productCategories.form.sections.quoteAttributes.description')}
            own={category.attributes.filter((attribute) => attribute.context === 'quote')}
            inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'quote')}
          />

          <CategoryAttributesContextSection
            title={t('productCategories.form.sections.workOrderAttributes.title')}
            description={t('productCategories.form.sections.workOrderAttributes.description')}
            own={category.attributes.filter((attribute) => attribute.context === 'work_order')}
            inherited={category.inherited_attributes.filter((attribute) => attribute.context === 'work_order')}
          />

          <ProductCategoryAttributeLayoutPreview categoryId={category.id} />
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard>
              <div className="flex items-center gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase [&>svg]:size-3.5">
                  <History aria-hidden="true" />
                  {t('activityLog.title')}
                </h2>
              </div>
              <div className="min-w-0 p-4">
                <ActivityLogSection resource="product-categories" id={category.id} />
              </div>
            </RecordCard>
          </div>
        ) : null}
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('productCategories.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
