import type { ProductCategoryDetail, ProductCategoryTreeNode } from '@/features/product-categories/types'
import { indexCategoryTree, resolveInheritedBusinessFunction } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedContractGenerationFlag } from '@/features/product-categories/contract-generation-inheritance'
import { resolveInheritedManagementMode } from '@/features/product-categories/management-mode-inheritance'
import { resolveInheritedMultiQuoteWorkOrderFlag } from '@/features/product-categories/multi-quote-work-order-inheritance'
import { resolveInheritedQuoteFlag } from '@/features/product-categories/requires-quote-inheritance'
import { resolveInheritedReportable } from '@/features/product-categories/reportable-inheritance'
import { resolveInheritedSimplifiedOfferLine } from '@/features/product-categories/simplified-offer-line-inheritance'
import { resolveInheritedSingleQuoteFlag } from '@/features/product-categories/single-quote-inheritance'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * What the record's rows and identity band show: the persisted category on the
 * detail (it satisfies this shape as is), the draft with its inheritance
 * resolved off the cached tree on the create form (spec 0229 D-4).
 */
export type ProductCategoryRecordValues = Pick<
  ProductCategoryDetail,
  | 'name'
  | 'parent'
  | 'description'
  | 'is_selectable'
  | 'is_active'
  | 'effective_business_function'
  | 'requires_quote'
  | 'requires_quote_source_category'
  | 'management_mode'
  | 'management_mode_source_category'
  | 'single_quote_per_opportunity'
  | 'single_quote_per_opportunity_source_category'
  | 'generates_contract'
  | 'generates_contract_source_category'
  | 'multi_quote_work_order'
  | 'multi_quote_work_order_source_category'
  | 'simplified_offer_line'
  | 'simplified_offer_line_source_category'
  | 'is_reportable'
  | 'effective_is_reportable'
  | 'is_reportable_source_category'
  | 'report_columns'
  | 'effective_report_columns'
  | 'report_columns_source_category'
>

/** The draft fields the record's rows read, as `useWatch` hands them back: every key may still be undefined on the first render. */
export type ProductCategoryDraft = Partial<
  Pick<
    ProductCategoryFormValues,
    | 'name'
    | 'parent_id'
    | 'description'
    | 'business_function_id'
    | 'requires_quote'
    | 'management_mode'
    | 'single_quote_per_opportunity'
    | 'generates_contract'
    | 'multi_quote_work_order'
    | 'simplified_offer_line'
    | 'simplified_offer_line_override'
    | 'is_selectable'
    | 'is_active'
    | 'is_reportable'
    | 'report_columns'
  >
>

/** The business function the draft ends up under: the inherited one wins over the own pick, as the picker enforces. */
export function draftBusinessFunctionId(draft: ProductCategoryDraft, tree: ProductCategoryTreeNode[]): number | null {
  const inherited = resolveInheritedBusinessFunction(indexCategoryTree(tree), draft.parent_id ?? null)
  return inherited?.businessFunctionId ?? draft.business_function_id ?? null
}

/**
 * Resolves the draft into the record shape. A root owns the root-owned rules
 * (its own draft value, no source); under a parent they mirror the branch
 * root, exactly as the server resolves them. Report columns have no tree data
 * to inherit from before the first save, so only the own selection shows.
 */
export function buildDraftRecordValues(
  draft: ProductCategoryDraft,
  tree: ProductCategoryTreeNode[],
  businessFunctionName: (id: number) => string,
): ProductCategoryRecordValues {
  const nodesById = indexCategoryTree(tree)
  const parentId = draft.parent_id ?? null
  const parentNode = parentId !== null ? nodesById.get(parentId) : undefined

  const quote = resolveInheritedQuoteFlag(nodesById, parentId)
  const mode = resolveInheritedManagementMode(nodesById, parentId)
  const single = resolveInheritedSingleQuoteFlag(nodesById, parentId)
  const contract = resolveInheritedContractGenerationFlag(nodesById, parentId)
  const multiQuote = resolveInheritedMultiQuoteWorkOrderFlag(nodesById, parentId)
  const offerLine = resolveInheritedSimplifiedOfferLine(nodesById, parentId)
  const reportable = resolveInheritedReportable(nodesById, parentId)
  // A child shows its own override, else what it inherits; a root declares the value itself (spec 0188).
  const ownOfferLine = draft.simplified_offer_line ?? false
  const forcedOfferLine = parentId === null ? null : (draft.simplified_offer_line_override ?? null)
  const simplifiedOfferLine = forcedOfferLine ?? offerLine?.value ?? ownOfferLine
  const simplifiedOfferLineSource = forcedOfferLine === null ? (offerLine?.sourceCategory ?? null) : null
  const isReportable = draft.is_reportable ?? null
  const effectiveReportable = isReportable ?? reportable?.value ?? false
  const reportColumns = draft.report_columns ?? null
  const ownBusinessFunctionId = draft.business_function_id ?? null
  const inheritedBusinessFunction = resolveInheritedBusinessFunction(nodesById, parentId)

  let effectiveBusinessFunction: ProductCategoryRecordValues['effective_business_function'] = null
  if (inheritedBusinessFunction !== null) {
    effectiveBusinessFunction = {
      id: inheritedBusinessFunction.businessFunctionId,
      name: businessFunctionName(inheritedBusinessFunction.businessFunctionId),
      inherited: true,
      source_category: inheritedBusinessFunction.sourceCategory,
    }
  } else if (ownBusinessFunctionId !== null) {
    effectiveBusinessFunction = {
      id: ownBusinessFunctionId,
      name: businessFunctionName(ownBusinessFunctionId),
      inherited: false,
      source_category: null,
    }
  }

  return {
    name: draft.name ?? '',
    parent: parentNode ? { id: parentNode.id, name: parentNode.name } : null,
    description: draft.description ?? null,
    is_selectable: draft.is_selectable ?? true,
    is_active: draft.is_active ?? true,
    effective_business_function: effectiveBusinessFunction,
    requires_quote: quote ? quote.requiresQuote : (draft.requires_quote ?? false),
    requires_quote_source_category: quote?.sourceCategory ?? null,
    management_mode: mode ? mode.managementMode : (draft.management_mode ?? 'multiple'),
    management_mode_source_category: mode?.sourceCategory ?? null,
    single_quote_per_opportunity: single
      ? single.singleQuotePerOpportunity
      : (draft.single_quote_per_opportunity ?? true),
    single_quote_per_opportunity_source_category: single?.sourceCategory ?? null,
    generates_contract: contract ? contract.generatesContract : (draft.generates_contract ?? true),
    generates_contract_source_category: contract?.sourceCategory ?? null,
    multi_quote_work_order: multiQuote ? multiQuote.multiQuoteWorkOrder : (draft.multi_quote_work_order ?? false),
    multi_quote_work_order_source_category: multiQuote?.sourceCategory ?? null,
    simplified_offer_line: simplifiedOfferLine,
    simplified_offer_line_source_category: simplifiedOfferLineSource,
    is_reportable: isReportable,
    effective_is_reportable: effectiveReportable,
    is_reportable_source_category: reportable?.sourceCategory ?? null,
    report_columns: reportColumns,
    effective_report_columns: reportColumns ?? [],
    report_columns_source_category: null,
  }
}
