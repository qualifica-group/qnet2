import { Boxes, ClipboardList } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Form, FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useWorkOrderForm } from '@/features/work-orders/use-work-order-form'
import { quoteLineToForSelectItem } from '@/features/work-orders/quote-line-label'
import { WorkOrderClosureSection } from '@/features/work-orders/work-order-closure-section'
import { WorkOrderNotesSection } from '@/features/work-orders/work-order-notes-section'
import { WorkOrderQuoteLinesField } from '@/features/work-orders/work-order-quote-lines-field'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-team-section'
import type { WorkOrderDetail, WorkOrderFormMode, WorkOrderType } from '@/features/work-orders/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'

/** Resource segment of the offers for-select endpoint (`GET /api/quotes/for-select`). */
const QUOTES_FOR_SELECT_RESOURCE = 'quotes'
/** Separator between an offer's code and title, matching the backend's composed for-select label. */
const QUOTE_LABEL_SEPARATOR = ' — '

const WORK_ORDER_TYPES: WorkOrderType[] = ['processing', 'project']

/** Stable module-level references: a fresh `[]` per render would break memo/dep stability. */
const EMPTY_SUPERVISORS: RelationFieldRef[] = []
const EMPTY_PARTICIPANTS: ForSelectItem[] = []

interface WorkOrderFormBodyProps {
  mode: WorkOrderFormMode
  onSuccess: (workOrder: WorkOrderDetail) => void
  onCancel: () => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-1). */
  initialCode?: string
}

/**
 * The work order create/edit form UI. Every field is wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions`. `code`/`quote_id`'s
 * immutability after create (D-1/D-5) is NOT a frontend decision: the
 * backend's field-permission ceiling reports both `editable: true` only when
 * there is no model context (create) — the same mechanism every other field
 * uses. "Chiusura forzata" and "Descrizione e note" live in their own
 * sections (`WorkOrderClosureSection`/`WorkOrderNotesSection`) to keep this
 * file within the engineering size limits. All non-render logic lives in
 * `useWorkOrderForm`.
 */
export function WorkOrderFormBody({ mode, onSuccess, onCancel, initialCode }: WorkOrderFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit, handleQuoteChange, handleForceClosedChange } = useWorkOrderForm({
    mode,
    onSuccess,
    initialCode,
  })

  const quoteId = useWatch({ control: form.control, name: 'quote_id' })

  const selectedQuote =
    mode.type === 'edit' && mode.workOrder.quote
      ? {
          id: mode.workOrder.quote.id,
          name: `${mode.workOrder.quote.code}${QUOTE_LABEL_SEPARATOR}${mode.workOrder.quote.title}`,
        }
      : null

  const selectedQuoteLines =
    mode.type === 'edit' ? mode.workOrder.quote_lines.map(quoteLineToForSelectItem) : undefined

  const selectedSupervisors = mode.type === 'edit' ? mode.workOrder.supervisors : EMPTY_SUPERVISORS
  const selectedParticipants =
    mode.type === 'edit'
      ? mode.workOrder.participants.map((participant) => ({
          id: participant.id,
          label: participant.name,
        }))
      : EMPTY_PARTICIPANTS

  const identityVisible =
    fieldPermission('code').visible ||
    fieldPermission('title').visible ||
    fieldPermission('type').visible ||
    fieldPermission('callback_date').visible
  const offerVisible = fieldPermission('quote_id').visible || fieldPermission('quote_line_ids').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={ClipboardList}
              title={t('workOrders.form.sections.identity.title')}
              description={t('workOrders.form.sections.identity.description')}
            >
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <MetaField
                  control={form.control}
                  name="code"
                  metaKey="code"
                  label={t('workOrders.form.code')}
                  hint={mode.type === 'edit' ? t('workOrders.form.hints.codeLocked') : undefined}
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input
                        autoComplete="off"
                        disabled={disabled}
                        readOnly={readOnly}
                        placeholder={t('workOrders.form.codePlaceholder')}
                        {...field}
                      />
                    </FormControl>
                  )}
                </MetaField>

                <MetaField control={form.control} name="title" metaKey="title" label={t('workOrders.form.title')}>
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                    </FormControl>
                  )}
                </MetaField>

                <MetaField control={form.control} name="type" metaKey="type" label={t('workOrders.form.type')}>
                  {({ field, disabled }) => (
                    <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {WORK_ORDER_TYPES.map((type) => (
                          <SelectItem key={type} value={type}>
                            {t(`workOrders.options.type.${type}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </MetaField>

                <MetaField
                  control={form.control}
                  name="callback_date"
                  metaKey="callback_date"
                  label={t('workOrders.form.callbackDate')}
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input
                        type="date"
                        disabled={disabled}
                        readOnly={readOnly}
                        value={field.value ?? ''}
                        onChange={(event) => field.onChange(event.target.value || null)}
                        onBlur={field.onBlur}
                        name={field.name}
                        ref={field.ref}
                      />
                    </FormControl>
                  )}
                </MetaField>
              </div>
            </FormSection>
          )}

          {offerVisible && (
            <FormSection
              icon={Boxes}
              title={t('workOrders.form.sections.offer.title')}
              description={t('workOrders.form.sections.offer.description')}
            >
              <RelationSelectField
                control={form.control}
                name="quote_id"
                metaKey="quote_id"
                label={t('workOrders.form.quoteId')}
                hint={mode.type === 'edit' ? t('workOrders.form.hints.quoteLocked') : undefined}
                resource={QUOTES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('workOrders.form.quoteSearchPlaceholder')}
                selected={selectedQuote}
                onValueChange={handleQuoteChange}
                placeholder={t('workOrders.form.quotePlaceholder')}
                emptyLabel={t('workOrders.form.quoteEmpty')}
                errorLabel={t('workOrders.form.quoteError')}
                clearLabel={t('workOrders.form.quoteClear')}
                retryLabel={t('common.retry')}
              />

              <MetaField
                control={form.control}
                name="quote_line_ids"
                metaKey="quote_line_ids"
                label={t('workOrders.form.quoteLineIds')}
              >
                {({ field, disabled }) => (
                  <WorkOrderQuoteLinesField
                    value={field.value}
                    onChange={field.onChange}
                    quoteId={quoteId}
                    exceptWorkOrderId={mode.type === 'edit' ? mode.workOrder.id : undefined}
                    selectedItems={selectedQuoteLines}
                    disabled={disabled}
                  />
                )}
              </MetaField>
            </FormSection>
          )}

          <WorkOrderTeamSection
            control={form.control}
            supervisors={selectedSupervisors}
            participants={selectedParticipants}
          />

          <WorkOrderClosureSection control={form.control} onForceClosedChange={handleForceClosedChange} />

          <WorkOrderNotesSection control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('workOrders.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('workOrders.form.saving') : t('workOrders.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
