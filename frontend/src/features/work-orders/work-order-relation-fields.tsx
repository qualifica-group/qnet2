import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { WorkOrderQuoteLinesField } from '@/features/work-orders/work-order-quote-lines-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

/*
 * The work order's relation fields — offer, its lines, Responsabili and
 * Partecipanti — one component each, shared by the detail's in-place rows
 * and the create draft's rows (spec 0195 D-3 applied to Commesse).
 */

/** Resource segment of the offers for-select endpoint (`GET /api/quotes/for-select`). */
export const QUOTES_FOR_SELECT_RESOURCE = 'quotes'

interface FieldProps {
  control: Control<WorkOrderFormValues>
}

interface QuoteFieldProps extends FieldProps {
  selected: RelationFieldRef | null
  /** AC-072: a new offer drops the lines picked from the old one (`useWorkOrderForm.handleQuoteChange`). */
  onQuoteChange: () => void
  /** Spec 0199: only this anagrafica's offers are offered; `null`/absent = the whole list. */
  registryId?: number | null
}

/** "Offerta collegata": chosen at creation only (D-5), the field permission locks it afterwards. */
export function WorkOrderQuoteField({ control, selected, onQuoteChange, registryId = null }: QuoteFieldProps) {
  const { t } = useTranslation()
  const params = useMemo(() => (registryId !== null ? { registry_id: registryId } : undefined), [registryId])
  return (
    <RelationSelectField
      control={control}
      name="quote_id"
      metaKey="quote_id"
      label={t('workOrders.form.quoteId')}
      resource={QUOTES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.quoteSearchPlaceholder')}
      selected={selected}
      params={params}
      onValueChange={onQuoteChange}
      placeholder={t('workOrders.form.quotePlaceholder')}
      emptyLabel={t('workOrders.form.quoteEmpty')}
      errorLabel={t('workOrders.form.quoteError')}
      clearLabel={t('workOrders.form.quoteClear')}
      retryLabel={t('common.retry')}
    />
  )
}

interface QuoteLinesFieldProps extends FieldProps {
  /** The persisted work order, whose own lines the picker must keep offering (spec 0095 D-7); absent on create. */
  exceptWorkOrderId?: number
  /** `{id, label}` of the already-selected lines, so a badge never falls back to `#id`. */
  selectedItems?: ForSelectItem[]
}

/** "Righe prodotto", scoped to the offer the form currently holds. */
export function WorkOrderQuoteLinesFormField({ control, exceptWorkOrderId, selectedItems }: QuoteLinesFieldProps) {
  const { t } = useTranslation()
  const quoteId = useWatch({ control, name: 'quote_id' })

  return (
    <MetaField control={control} name="quote_line_ids" metaKey="quote_line_ids" label={t('workOrders.form.quoteLineIds')}>
      {({ field, disabled }) => (
        <WorkOrderQuoteLinesField
          value={field.value}
          onChange={field.onChange}
          quoteId={quoteId}
          exceptWorkOrderId={exceptWorkOrderId}
          selectedItems={selectedItems}
          disabled={disabled}
        />
      )}
    </MetaField>
  )
}

interface SupervisorsFieldProps extends FieldProps {
  selected: RelationFieldRef[]
}

/** "Responsabili" (spec 0096): several, at least one. */
export function WorkOrderSupervisorsField({ control, selected }: SupervisorsFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="supervisor_ids"
      metaKey="supervisor_ids"
      label={t('workOrders.form.supervisors')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.supervisorsSearch')}
      selected={selected}
      showAvatar
      placeholder={t('workOrders.form.supervisorsPlaceholder')}
      emptyLabel={t('workOrders.form.supervisorsEmpty')}
      errorLabel={t('workOrders.form.supervisorsError')}
      removeLabel={t('common.remove')}
      retryLabel={t('common.retry')}
    />
  )
}

interface ParticipantsFieldProps extends FieldProps {
  /** `{id, label}` of the filled slots. */
  selected: ForSelectItem[]
}

/**
 * "Partecipanti" (spec 0096): the ordered, gap-aware slots, edited with the
 * SHARED `ManagerSlotsField` Registries, Opportunita' and Offerte use — only
 * the per-row denomination differs ("Partecipante n", spec 0080 `labels`).
 */
export function WorkOrderParticipantsField({ control, selected }: ParticipantsFieldProps) {
  const { t } = useTranslation()

  // Built here rather than hoisted: every label is a translated string.
  const slotLabels = Object.fromEntries(
    Array.from({ length: MAX_MANAGER_SLOTS }, (_, index) => [
      index + 1,
      t('workOrders.form.participantSlotLabel', { n: index + 1 }),
    ]),
  )

  return (
    <MetaField control={control} name="participant_slots" metaKey="participant_slots" label={t('workOrders.form.participants')}>
      {({ field, disabled }) => (
        <ManagerSlotsField
          value={field.value}
          onChange={field.onChange}
          selectedItems={selected}
          disabled={disabled}
          labels={slotLabels}
          // The shared editor defaults to "gestore account" wording, which is
          // wrong here: these slots are Partecipanti (spec 0096).
          strings={{
            search: t('workOrders.form.participantsSearch'),
            empty: t('workOrders.form.participantsEmpty'),
            error: t('workOrders.form.participantsError'),
            addSlot: t('workOrders.form.participantsAddSlot'),
            hint: t('workOrders.form.participantsHint'),
          }}
        />
      )}
    </MetaField>
  )
}
