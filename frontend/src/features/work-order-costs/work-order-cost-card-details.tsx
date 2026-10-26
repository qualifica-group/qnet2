import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { Input } from '@/components/ui/input'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { QuoteLineAdditionalDescription } from '@/features/quotes/quote-line-additional-description'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { errorAria } from '@/features/work-order-costs/cost-row-aria'
import { CostRowError, type CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import {
  DOCUMENT_REFERENCE_MAX_LENGTH,
  type WorkOrderCostRowValues,
} from '@/features/work-order-costs/work-order-costs-schema'

/** Filters the supplier picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }
/** The "+" quick-create opens the registry form with the supplier flag already on. */
const SUPPLIER_PRESETS = { is_supplier: true } as const

interface WorkOrderCostCardDetailsProps {
  /** 1-based row number: the row's name in every label. */
  n: number
  row: WorkOrderCostRowValues
  disabled: boolean
  error?: CostRowErrors
  /** `useId` of the card: field and error ids hang from it. */
  rowId: string
  onChangeField: (patch: Partial<WorkOrderCostRowValues>) => void
}

/**
 * Level 3 of a real-cost card (spec 0270 D-3): what is specific to a cost
 * actually incurred — date, cost type, supplier, document reference and the
 * additional description — moved unchanged from the former grid row's second
 * line.
 */
export function WorkOrderCostCardDetails({ n, row, disabled, error, rowId, onChangeField }: WorkOrderCostCardDetailsProps) {
  const { t } = useTranslation()
  const costTypeCreate = useQuickCreateAction(COST_TYPES_FOR_SELECT_RESOURCE)
  const supplierCreate = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE, SUPPLIER_PRESETS)
  const errorId = (field: string) => `${rowId}-${field}-error`

  const costTypeItem: (ForSelectItem & { meta: { color: string | null } }) | null =
    row.cost_type_id !== null && row.display.cost_type_name
      ? { id: row.cost_type_id, label: row.display.cost_type_name, meta: { color: row.display.cost_type_color } }
      : null
  const supplierItem: ForSelectItem | null =
    row.supplier_id !== null && row.display.supplier_name ? { id: row.supplier_id, label: row.display.supplier_name } : null

  return (
    <div className="grid grid-cols-1 items-start gap-2 @lg:grid-cols-2 @2xl:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
      <div className="flex flex-col gap-1">
        <Input
          type="date"
          aria-label={t('workOrders.costs.editor.incurredOn', { n })}
          disabled={disabled}
          value={row.incurred_on}
          onChange={(event) => onChangeField({ incurred_on: event.target.value })}
          className="h-8 text-sm"
          {...errorAria(errorId('incurredOn'), error?.incurred_on)}
        />
        <CostRowError id={errorId('incurredOn')} error={error?.incurred_on} />
      </div>

      <div className="flex flex-col gap-1">
        <AsyncPaginatedSelect
          resource={COST_TYPES_FOR_SELECT_RESOURCE}
          value={row.cost_type_id}
          onChange={() => {}}
          onItemChange={(item) => {
            const color = (item as (ForSelectItem & { meta?: { color?: string | null } }) | null)?.meta?.color
            onChangeField({
              cost_type_id: item?.id ?? null,
              display: { ...row.display, cost_type_name: item?.label ?? null, cost_type_color: color ?? null },
            })
          }}
          selectedItem={costTypeItem}
          renderItem={costTypeOptionRenderer}
          action={costTypeCreate.renderAction(
            (ref) =>
              onChangeField({
                cost_type_id: ref.id,
                display: { ...row.display, cost_type_name: ref.name, cost_type_color: null },
              }),
            disabled,
          )}
          disabled={disabled}
          className="min-h-8 py-1"
          {...errorAria(errorId('costType'), error?.cost_type_id)}
          labels={{
            placeholder: t('workOrders.costs.editor.costTypePlaceholder'),
            searchPlaceholder: t('workOrders.costs.editor.costTypeSearch'),
            empty: t('workOrders.costs.editor.costTypeEmpty'),
            error: t('workOrders.costs.editor.costTypeLoadError'),
            clearLabel: t('common.clear'),
            triggerLabel: t('workOrders.costs.editor.costType', { n }),
            retry: t('common.retry'),
          }}
        />
        <CostRowError id={errorId('costType')} error={error?.cost_type_id} />
      </div>

      <div className="flex flex-col gap-1">
        <AsyncPaginatedSelect
          resource={REGISTRIES_FOR_SELECT_RESOURCE}
          params={SUPPLIER_PARAMS}
          value={row.supplier_id}
          onChange={() => {}}
          onItemChange={(item) =>
            onChangeField({ supplier_id: item?.id ?? null, display: { ...row.display, supplier_name: item?.label ?? null } })
          }
          selectedItem={supplierItem}
          action={supplierCreate.renderAction(
            (ref) => onChangeField({ supplier_id: ref.id, display: { ...row.display, supplier_name: ref.name } }),
            disabled,
          )}
          disabled={disabled}
          className="min-h-8 py-1"
          {...errorAria(errorId('supplier'), error?.supplier_id)}
          labels={{
            placeholder: t('workOrders.costs.editor.supplierPlaceholder'),
            searchPlaceholder: t('workOrders.costs.editor.supplierSearch'),
            empty: t('workOrders.costs.editor.supplierEmpty'),
            error: t('workOrders.costs.editor.supplierLoadError'),
            clearLabel: t('common.clear'),
            triggerLabel: t('workOrders.costs.editor.supplier', { n }),
            retry: t('common.retry'),
          }}
        />
        <CostRowError id={errorId('supplier')} error={error?.supplier_id} />
      </div>

      <div className="flex flex-col gap-1">
        <Input
          aria-label={t('workOrders.costs.editor.documentReference', { n })}
          placeholder={t('workOrders.costs.editor.documentReferencePlaceholder')}
          maxLength={DOCUMENT_REFERENCE_MAX_LENGTH}
          disabled={disabled}
          value={row.document_reference ?? ''}
          onChange={(event) => onChangeField({ document_reference: event.target.value === '' ? null : event.target.value })}
          className="h-8 text-sm"
          {...errorAria(errorId('documentReference'), error?.document_reference)}
        />
        <CostRowError id={errorId('documentReference')} error={error?.document_reference} />
      </div>

      <div className="flex flex-col gap-1 @lg:col-span-full">
        <QuoteLineAdditionalDescription
          lineNumber={n}
          value={row.additional_description}
          disabled={disabled}
          onChange={(additionalDescription) => onChangeField({ additional_description: additionalDescription })}
        />
        <CostRowError id={errorId('additionalDescription')} error={error?.additional_description} />
      </div>
    </div>
  )
}
