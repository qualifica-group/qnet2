import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { rootCategoryIdFor } from '@/features/product-lines/category-tree-scope'
import type { ProductLineRow } from '@/features/product-lines/types'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import {
  REWARD_TYPES_FOR_SELECT_RESOURCE,
  type RewardTypeForSelectItem,
} from '@/features/reward-types/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useOpportunityManagerLabels } from '@/features/opportunities/use-opportunity-manager-labels'
import type { OpportunityRecordValues } from '@/features/opportunities/opportunity-record'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty sets: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []
const NO_SLOTS: (number | null)[] = []
const NO_TREE: ProductCategoryTreeNode[] = []

/** The draft's id(s) as the label hooks read them. */
function idsOf(...ids: (number | null | undefined)[]): number[] {
  const present = ids.filter((id): id is number => id != null)
  return present.length > 0 ? present : NO_IDS
}

/** Depth-first lookup by id in the cached category tree. */
function findCategory(nodes: ProductCategoryTreeNode[], id: number): ProductCategoryTreeNode | null {
  for (const node of nodes) {
    if (node.id === id) {
      return node
    }
    const found = findCategory(node.children, id)
    if (found) {
      return found
    }
  }
  return null
}

/** "Root > Category" of a draft classification row, as the detail's read-only list names it. */
function categoryPathLabel(tree: ProductCategoryTreeNode[], categoryId: number): string {
  const category = findCategory(tree, categoryId)
  if (!category) {
    return PENDING_LABEL
  }
  const rootId = rootCategoryIdFor(tree, categoryId)
  const root = rootId !== null && rootId !== categoryId ? findCategory(tree, rootId) : null
  return root ? `${root.name} > ${category.name}` : category.name
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined): RelationFieldRef | null {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the record's closed rows read it (spec 0198): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger — and the
 * classification rows through the cached category tree. The anagrafica a
 * picked Lead hands down is already named by that pick.
 */
export function useOpportunityDraftValues(
  control: Control<OpportunityFormValues>,
  leadRegistry: RelationFieldRef | null,
): { values: OpportunityRecordValues; productLineLabels: string[] } {
  const draft = useWatch({ control })
  const slots = draft.manager_slots ?? NO_SLOTS
  const productLines = useMemo<ProductLineRow[]>(
    () =>
      (draft.product_lines ?? []).map((row) => ({
        root_category_id: row?.root_category_id ?? null,
        product_category_id: row?.product_category_id ?? null,
      })),
    [draft.product_lines],
  )
  const categoryIds = useMemo(
    () => productLines.map((row) => row.product_category_id).filter((id): id is number => id !== null),
    [productLines],
  )
  const productIds = draft.products_of_interest ?? NO_IDS
  const rewardTypeIds = useMemo(() => (draft.rewards ?? []).map((reward) => reward?.reward_type_id ?? 0), [draft.rewards])
  const userIds = useMemo(
    () => Array.from(new Set(idsOf(draft.supervisor_id, ...slots))),
    [draft.supervisor_id, slots],
  )
  const productParams = useMemo(() => ({ category_ids: categoryIds }), [categoryIds])

  const registries = useForSelectLabels({ resource: REGISTRIES_FOR_SELECT_RESOURCE, ids: idsOf(draft.registry_id) })
  const referents = useForSelectLabels({
    resource: REFERENTS_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.referent_id, draft.commercial_id, draft.reporter_id),
  })
  const sources = useForSelectLabels({ resource: SOURCES_FOR_SELECT_RESOURCE, ids: idsOf(draft.source_id) })
  const users = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: userIds })
  const products = useForSelectLabels({ resource: PRODUCTS_FOR_SELECT_RESOURCE, ids: productIds, params: productParams })
  const rewardTypes = useForSelectLabels({ resource: REWARD_TYPES_FOR_SELECT_RESOURCE, ids: rewardTypeIds })
  const managerLabels = useOpportunityManagerLabels(productLines)
  const tree = useProductCategoryTree().data ?? NO_TREE

  const registryId = draft.registry_id ?? null
  const values: OpportunityRecordValues = {
    name: draft.name ?? '',
    start_date: draft.start_date ?? null,
    expected_close_date: draft.expected_close_date ?? null,
    estimated_value: draft.estimated_value ?? null,
    success_probability: draft.success_probability ?? null,
    general_notes: draft.general_notes ?? null,
    registry: leadRegistry?.id === registryId && registryId !== null ? leadRegistry : refOf(registries, registryId),
    referent: refOf(referents, draft.referent_id),
    commercial: refOf(referents, draft.commercial_id),
    reporter: refOf(referents, draft.reporter_id),
    lead: null,
    source: refOf(sources, draft.source_id),
    supervisor: refOf(users, draft.supervisor_id),
    managers: slots.flatMap((id, index) =>
      id != null ? [{ id, name: users.get(id)?.label ?? PENDING_LABEL, position: index + 1 }] : [],
    ),
    manager_labels: managerLabels,
    products_of_interest: productIds.map((id) => {
      const item = products.get(id)
      const categoryId = item ? productCategoryIdOf(item) : null
      return {
        id,
        name: item?.label ?? PENDING_LABEL,
        product_category: categoryId !== null && item?.subtitle ? { id: categoryId, name: item.subtitle } : null,
      }
    }),
    // Not assigned yet: the type is all a draft reward has. Its chip (and the
    // reporter editor reopening on it) only needs the type's name and color.
    rewards: rewardTypeIds.map((typeId) => {
      const item = rewardTypes.get(typeId) as RewardTypeForSelectItem | undefined
      return {
        id: typeId,
        reward_type: { id: typeId, name: item?.label ?? PENDING_LABEL, color: item?.meta.color ?? '' },
        assigned_at: '',
        notes: null,
      }
    }),
  }

  return { values, productLineLabels: categoryIds.map((id) => categoryPathLabel(tree, id)) }
}
