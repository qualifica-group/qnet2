import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import { emptyProductLineRow } from '@/features/product-lines/types'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'
import type { WorkOrderDetail } from '@/features/work-orders/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

/*
 * Default values of the work order form (split out of `use-work-order-form.ts`,
 * file size): the persisted record for the detail's in-place edit, or a fresh
 * draft for the create form.
 */

/** Empty slots rendered on a fresh create, mirroring opportunities/quotes' own default. */
const DEFAULT_PARTICIPANT_SLOTS = 4

/** The own references of a work order that has none (spec 0431): every one born from an offer. */
const NO_REFERENCES = {
  registry_id: null,
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  product_lines: [],
  product_ids: [],
} satisfies Pick<
  WorkOrderFormValues,
  'registry_id' | 'company_id' | 'company_site_id' | 'operational_site_id' | 'product_lines' | 'product_ids'
>

/** Default values hydrated from the persisted work order (the in-place detail). */
export function editDefaults(workOrder: WorkOrderDetail): WorkOrderFormValues {
  return {
    code: workOrder.code,
    quote_id: workOrder.quote?.id ?? null,
    title: workOrder.title,
    type: workOrder.type,
    start_date: workOrder.start_date,
    supervisor_ids: workOrder.supervisors.map((supervisor) => supervisor.id),
    // Gaps in `position` are meaningful: rebuild the sparse array rather
    // than compacting the persisted participants into a dense list.
    participant_slots: padManagerSlots(managerSlotsFromRefs(workOrder.participants), DEFAULT_PARTICIPANT_SLOTS),
    callback_date: workOrder.callback_date,
    description: workOrder.description,
    internal_notes: workOrder.internal_notes,
    quote_line_ids: workOrder.quote_lines.map((line) => line.id),
    // Spec 0124 D-9: never resubmitted (`buildUpdatePayload` never reads it,
    // D-5), kept only so the shared shape stays one.
    task_template_id: workOrder.task_template?.id ?? null,
    work_order_workflow_status_id: workOrder.work_order_workflow_status_id,
    // An empty PHP map serializes as a JSON ARRAY (`toAttributeValuesMap`),
    // and every applicable code needs its key for the Zod object: seeded
    // here, not by the effect below, so the `values` re-sync after a save
    // never hands the schema an unseeded map.
    attribute_values: seedAttributeValues(
      workOrder.applicable_attributes,
      toAttributeValuesMap(workOrder.attribute_values),
    ),
    // Spec 0431: hydrated only on a work order born without an offer, the one
    // kind that owns them (on any other they are the offer's, never sent).
    ...(workOrder.is_standalone
      ? {
          registry_id: workOrder.registry?.id ?? null,
          company_id: workOrder.company?.id ?? null,
          company_site_id: workOrder.company_site?.id ?? null,
          operational_site_id: workOrder.operational_site?.id ?? null,
          // `root_category_id` starts null: the shared row editor resolves it from the cached tree.
          product_lines: (workOrder.product_lines ?? []).map((line) => ({
            root_category_id: null,
            product_category_id: line.product_category.id,
          })),
          product_ids: (workOrder.products ?? []).map((product) => product.id),
        }
      : NO_REFERENCES),
  }
}

/**
 * Default values of a brand-new work order, its `code` prefilled with the
 * suggestion (D-1). Born without an offer (spec 0431) it is a project one
 * with a first empty business-function row.
 */
export function createDefaults(initialCode: string | undefined, standalone: boolean): WorkOrderFormValues {
  return {
    code: initialCode ?? '',
    quote_id: null,
    title: '',
    type: standalone ? 'project' : 'processing',
    start_date: '',
    supervisor_ids: [],
    participant_slots: Array.from({ length: DEFAULT_PARTICIPANT_SLOTS }, () => null),
    callback_date: null,
    description: null,
    internal_notes: null,
    quote_line_ids: [],
    task_template_id: null,
    work_order_workflow_status_id: null,
    attribute_values: {},
    ...NO_REFERENCES,
    product_lines: standalone ? [emptyProductLineRow()] : [],
  }
}
