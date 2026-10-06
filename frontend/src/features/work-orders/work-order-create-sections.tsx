import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Boxes, Loader2, SlidersHorizontal } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { TASK_TEMPLATES_FOR_SELECT_RESOURCE } from '@/features/task-templates/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { WorkOrderAttributesSection } from '@/features/work-orders/work-order-attributes-section'
import { QUOTE_OFFER_LINES_FOR_SELECT_RESOURCE } from '@/features/work-orders/work-order-quote-lines-field'
import { WorkOrderIdentitySection, WorkOrderInternalNotesRow } from '@/features/work-orders/work-order-record-identity'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import {
  QUOTES_FOR_SELECT_RESOURCE,
  WorkOrderQuoteField,
  WorkOrderQuoteLinesFormField,
} from '@/features/work-orders/work-order-relation-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty sets: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []
const NO_SLOTS: (number | null)[] = []

/** The draft's id(s) as the label hooks read them. */
function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

interface DraftProps {
  workOrderForm: WorkOrderFormState
  draft: InlineEdit
}

/**
 * Offerta of the draft: the offer and its lines, the only place they are
 * picked — once created the offer is fixed (D-5) and the detail shows the
 * contract born from it instead.
 */
function WorkOrderOfferSection({ workOrderForm, draft }: DraftProps) {
  const { t } = useTranslation()
  const { form, handleQuoteChange } = workOrderForm
  const [quoteId, quoteLineIds] = useWatch({ control: form.control, name: ['quote_id', 'quote_line_ids'] })
  const lineParams = useMemo(() => ({ quote_id: quoteId ?? 0 }), [quoteId])
  const quoteLabels = useForSelectLabels({ resource: QUOTES_FOR_SELECT_RESOURCE, ids: idsOf(quoteId) })
  const lineLabels = useForSelectLabels({
    resource: QUOTE_OFFER_LINES_FOR_SELECT_RESOURCE,
    ids: quoteLineIds,
    params: lineParams,
    enabled: quoteId !== null,
  })

  return (
    <RecordSection title={t('workOrders.form.sections.offer.title')} icon={<Boxes />}>
      <RecordFieldList>
        <RecordInlineField
          field="quote_id"
          label={t('workOrders.form.quoteId')}
          inline={draft}
          editor={<WorkOrderQuoteField control={form.control} selected={null} onQuoteChange={handleQuoteChange} />}
        >
          {quoteId !== null ? (quoteLabels.get(quoteId)?.label ?? PENDING_LABEL) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="quote_line_ids"
          label={t('workOrders.detail.lines')}
          inline={draft}
          editor={<WorkOrderQuoteLinesFormField control={form.control} />}
        >
          {quoteLineIds.length > 0 ? (
            <ul className="flex flex-col gap-1">
              {quoteLineIds.map((id) => (
                <li key={id} className="min-w-0 font-medium">
                  {lineLabels.get(id)?.label ?? PENDING_LABEL}
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * The create form as a replica of the work order detail (spec 0195 D-8
 * applied to Commesse, user directive 2026-10-06): the detail's sections,
 * rows, labels and order, every row CLOSED until clicked — empty or prefilled
 * — and opening on the same field component the detail edits in place.
 * Nothing is saved per row: the header's Salva validates and creates the
 * whole draft. What only exists once created (client, contract, company and
 * sites, computed completion) is left out; the offer and its lines, fixed
 * after creation, are picked here.
 *
 * The form holds ids only: the closed rows name them through
 * `useForSelectLabels`, the very cache the pickers fill for their own trigger.
 */
export function WorkOrderCreateSections({ workOrderForm, draft }: DraftProps) {
  const { t } = useTranslation()
  const { form, attributeContext, attributesLoading } = workOrderForm
  const values = useWatch({ control: form.control })
  const supervisorIds = values.supervisor_ids ?? NO_IDS
  const slots = values.participant_slots ?? NO_SLOTS
  const peopleIds = useMemo(
    () => Array.from(new Set([...supervisorIds, ...slots.filter((id): id is number => id != null)])),
    [supervisorIds, slots],
  )
  const people = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: peopleIds })
  const templateId = values.task_template_id ?? null
  const templateLabels = useForSelectLabels({ resource: TASK_TEMPLATES_FOR_SELECT_RESOURCE, ids: idsOf(templateId) })
  const nameOf = (id: number) => people.get(id)?.label ?? PENDING_LABEL

  return (
    <RecordSectionsGrid>
      <WorkOrderInternalNotesRow
        notes={values.internal_notes ?? null}
        form={workOrderForm}
        inline={draft}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <WorkOrderIdentitySection
        values={{
          title: values.title ?? '',
          code: values.code ?? '',
          type: values.type ?? 'processing',
          start_date: values.start_date || null,
          callback_date: values.callback_date ?? null,
          description: values.description ?? null,
          task_template:
            templateId !== null
              ? { id: templateId, name: templateLabels.get(templateId)?.label ?? PENDING_LABEL }
              : null,
          force_close_reason: null,
        }}
        form={workOrderForm}
        inline={draft}
      />

      <WorkOrderTeamSection
        supervisors={supervisorIds.map((id) => ({ id, name: nameOf(id) }))}
        participants={slots.flatMap((id, index) => (id != null ? [{ id, name: nameOf(id), position: index + 1 }] : []))}
        form={workOrderForm}
        inline={draft}
      />

      <WorkOrderOfferSection workOrderForm={workOrderForm} draft={draft} />

      {attributesLoading ? (
        <RecordSection
          title={t('workOrders.detail.additionalInformation')}
          icon={<SlidersHorizontal />}
          className={FULL_WIDTH_SECTION_CLASS}
        >
          <p className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            {t('common.loading')}
          </p>
        </RecordSection>
      ) : (
        <WorkOrderAttributesSection
          attributes={attributeContext.applicable_attributes}
          layout={attributeContext.attribute_layout}
          values={values.attribute_values ?? {}}
          inline={draft}
          control={form.control}
          className={FULL_WIDTH_SECTION_CLASS}
        />
      )}
    </RecordSectionsGrid>
  )
}
