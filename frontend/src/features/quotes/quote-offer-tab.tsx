import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useWatch, type Control } from 'react-hook-form'
import { useConfirm } from '@/components/confirm-dialog-context'
import { MetaField } from '@/features/authorization/MetaField'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { fetchOpportunity, opportunityDetailQueryKey } from '@/features/opportunities/api'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { resolveManagementMode } from '@/features/product-lines/category-tree-scope'
import { allocatedCostNetByOfferLineKey } from '@/features/quotes/commission-calculator'
import { QuoteLineEditor } from '@/features/quotes/line-editor/quote-line-editor'
import type { QuoteCatalogProduct, QuoteCatalogScope } from '@/features/quotes/line-editor/types'
import { QuoteLineAdditionalDescription } from '@/features/quotes/quote-line-additional-description'
import { QuoteLineCommissionsAction } from '@/features/quotes/quote-line-commissions-action'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import { MAX_LINES_PER_TAB, type QuoteFormValues, type QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteCommissionContext, QuoteLine } from '@/features/quotes/types'
import { useQuoteLineCommissions } from '@/features/quotes/use-quote-line-commissions'

interface QuoteOfferTabProps {
  control: Control<QuoteFormValues>
  errors?: (QuoteLineRowErrors | undefined)[]
  /** The persisted `offer_lines` (edit mode only, `[]` on create), for label hydration. */
  knownLines: QuoteLine[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /** Spec 0099: feeds the live per-typology summary's bucket cache on pick (revenue rows only, D-6). */
  rememberProductTypology?: (productId: number, typologyId: number) => void
  /** Spec 0144: feeds the shared product name cache the Cost tab's association select and the live per-product margin block read. */
  rememberProductName?: (productId: number, name: string) => void
  quoteId?: number
}

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []
const NO_CATEGORY_IDS: number[] = []

/**
 * Offer (revenue) lines tab (spec 0065 AC-072, catalogue editor since spec
 * 0269): the catalogue defaults to the SELECTED opportunity's own
 * product-line categories, refetched whenever `opportunity_id` changes. The
 * "Tutto il catalogo" chip, confirmed via `useConfirm()`, drops that scope
 * (D-2) — a cross-category quote line still widens the opportunity's coverage
 * server-side (OpportunityProductLineCoverage).
 */
export function QuoteOfferTab({ control, quoteId, ...props }: QuoteOfferTabProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [unlocked, setUnlocked] = useState(false)

  const opportunityId = useWatch({ control, name: 'opportunity_id' })
  const commercialId = useWatch({ control, name: 'commercial_id' })
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const supervisorId = useWatch({ control, name: 'supervisor_id' })
  // Spec 0145 (D-1): the sibling Cost tab's live rows, resolved to a
  // base-net-per-row map for the commissions dialog.
  const costLines = useWatch({ control, name: 'cost_lines' })
  const allocatedCostNetMap = useMemo(() => allocatedCostNetByOfferLineKey(costLines), [costLines])
  const commissionContext = useMemo<QuoteCommissionContext>(
    () => ({ quoteId, commercialId, reporterId, supervisorId }),
    [quoteId, commercialId, reporterId, supervisorId],
  )

  const opportunityQuery = useQuery({
    queryKey: opportunityId !== null ? opportunityDetailQueryKey(opportunityId) : ['opportunities', 'detail', null],
    queryFn: () => fetchOpportunity(opportunityId as number),
    enabled: opportunityId !== null,
    staleTime: 5 * 60 * 1000,
  })

  const scopedCategoryIds = useMemo(
    () =>
      opportunityQuery.data
        ? [...new Set(opportunityQuery.data.product_lines.map((line) => line.product_category.id))]
        : NO_CATEGORY_IDS,
    [opportunityQuery.data],
  )

  // Spec 0077: an opportunity managed on a `single` product category carries
  // one offer row, resolved over ALL the covered categories against the
  // cached tree, where the mode lives. The whole-catalogue switch widens the
  // catalogue, it never lifts the cap, which the server enforces either way.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE
  const singleCategoryMode = resolveManagementMode(categoryTree, scopedCategoryIds) === 'single'

  const requestUnlock = async () => {
    const confirmed = await confirm({
      tone: 'warning',
      title: t('quotes.form.offerTab.unlockDialog.title'),
      description: t('quotes.form.offerTab.unlockDialog.description'),
      confirmLabel: t('quotes.form.offerTab.unlockDialog.confirm'),
      cancelLabel: t('common.cancel'),
    })
    if (confirmed) {
      setUnlocked(true)
    }
  }

  const catalogScope: QuoteCatalogScope = {
    kind: 'categories',
    categoryIds: scopedCategoryIds,
    fullCatalog: { active: unlocked, onActivate: () => void requestUnlock(), onRelease: () => setUnlocked(false) },
  }

  return (
    // The editor's rows pane already titles the collection: the field label
    // stays for assistive tech only.
    <MetaFieldRowContext.Provider value="offer_lines">
      <MetaField control={control} name="offer_lines" metaKey="offer_lines" label={t('quotes.form.offerTab.fieldLabel')}>
        {({ field, disabled }) => (
          <OfferLinesBody
            {...props}
            value={field.value}
            onChange={field.onChange}
            disabled={disabled}
            catalogScope={catalogScope}
            lockedHint={t(opportunityId === null ? 'quotes.form.offerTab.hintNoOpportunity' : 'quotes.form.offerTab.hintNoCategories')}
            categoryTree={categoryTree}
            singleCategoryMode={singleCategoryMode}
            commissionContext={commissionContext}
            allocatedCostNetMap={allocatedCostNetMap}
          />
        )}
      </MetaField>
    </MetaFieldRowContext.Provider>
  )
}

interface OfferLinesBodyProps extends Omit<QuoteOfferTabProps, 'control' | 'quoteId'> {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  disabled: boolean
  catalogScope: QuoteCatalogScope
  lockedHint: string
  categoryTree: ProductCategoryTreeNode[]
  singleCategoryMode: boolean
  commissionContext: QuoteCommissionContext
  allocatedCostNetMap: Map<string, number>
}

/** The field's body: a component of its own because the commission hook needs the field's value. */
function OfferLinesBody({
  value,
  onChange,
  disabled,
  errors,
  knownLines,
  vatRatePercentFor,
  rememberVatRatePercent,
  rememberProductTypology,
  rememberProductName,
  catalogScope,
  lockedHint,
  categoryTree,
  singleCategoryMode,
  commissionContext,
  allocatedCostNetMap,
}: OfferLinesBodyProps) {
  const commissions = useQuoteLineCommissions({ value, onChange, commissionContext })
  const { field: fieldPermission } = useResourcePermissions()
  const commissionsPermission = fieldPermission('commissions')
  const canEditCommissions = commissionsPermission.editable && !commissionsPermission.disabled

  const patchRow = (index: number, patch: Partial<QuoteLineFormValues>) =>
    onChange(value.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))

  // Spec 0099/0144: the live summary buckets and the margin block name every picked product.
  const rememberPicked = (items: QuoteCatalogProduct[]) =>
    items.forEach((item) => {
      if (item.meta.product_typology) {
        rememberProductTypology?.(item.id, item.meta.product_typology.id)
      }
      rememberProductName?.(item.id, item.label)
    })

  return (
    <QuoteLineEditor
      value={value}
      onChange={onChange}
      variant="revenue"
      disabled={disabled}
      catalogScope={catalogScope}
      lockedHint={lockedHint}
      categoryTree={categoryTree}
      maxRows={singleCategoryMode ? 1 : MAX_LINES_PER_TAB}
      singleCategoryMode={singleCategoryMode}
      knownLines={knownLines}
      errors={errors}
      vatRatePercentFor={vatRatePercentFor}
      rememberVatRatePercent={rememberVatRatePercent}
      commissions={commissions}
      onProductsPicked={rememberPicked}
      renderRowSlots={(row, index, position, product) => ({
        actions: commissionsPermission.visible ? (
          <QuoteLineCommissionsAction
            position={position}
            row={row}
            productName={product?.name}
            commissionContext={commissionContext}
            allocatedCostNet={row.client_key ? (allocatedCostNetMap.get(row.client_key) ?? 0) : 0}
            disabled={disabled || !canEditCommissions}
            onSave={(next) => patchRow(index, { commissions: next })}
          />
        ) : undefined,
        footer: (
          <QuoteLineAdditionalDescription
            lineNumber={position}
            value={row.additional_description ?? null}
            disabled={disabled}
            onChange={(additionalDescription) => patchRow(index, { additional_description: additionalDescription })}
          />
        ),
      })}
    />
  )
}
