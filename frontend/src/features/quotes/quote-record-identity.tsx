import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { WorkflowStatusBadge } from '@/features/quote-workflows/workflow-status-badge'
import {
  QuoteCodeField,
  QuoteInternalNotesField,
  QuoteOpportunityField,
  QuoteTitleField,
} from '@/features/quotes/quote-identity-fields'
import { QuoteWorkflowStatusControls } from '@/features/quotes/quote-workflow-status-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteWorkflowStatusRef } from '@/features/quotes/types'

/** What the identity rows show: the persisted record on the detail, the draft on create. */
export interface QuoteIdentityValues {
  code: string
  /** Blank only on a create draft: the server derives the automatic title (spec 0171 rev.2). */
  title: string
  /** `null` on create: the backend assigns the `open` row of the set it resolves (AC-020). */
  status: QuoteWorkflowStatusRef | null
  opportunity: RelationFieldRef | null
}

/** The status set of a persisted quote: absent on create, where no transition happens. */
interface StatusContext {
  statuses: QuoteWorkflowStatusRef[]
  originalStatusId: number
}

/** The create flow's hooks on the Opportunita' row (role inheritance, preset lock). */
interface OpportunityContext {
  onItemChange: (item: ForSelectItem | null) => void
  forceDisabled: boolean
  /** Spec 0199: narrows the picker to one anagrafica's opportunities (create from its Offerte tab). */
  registryId?: number | null
}

/** Detail: the parent record is fixed (AC-025), its row only reads. */
const NO_OPPORTUNITY_CONTEXT: OpportunityContext = { onItemChange: () => undefined, forceDisabled: true }

interface StatusEditorProps extends StatusContext {
  control: Control<QuoteFormValues>
}

/** The status select plus, when the target demands it, the transition note (AC-023). */
function StatusEditor({ control, statuses, originalStatusId }: StatusEditorProps) {
  const selectedStatusId = useWatch({ control, name: 'quote_workflow_status_id' })
  return (
    <QuoteWorkflowStatusControls
      control={control}
      statuses={statuses}
      originalStatusId={originalStatusId}
      selectedStatusId={selectedStatusId}
    />
  )
}

interface QuoteIdentitySectionProps {
  values: QuoteIdentityValues
  control: Control<QuoteFormValues>
  inline: InlineEdit
  status?: StatusContext
  opportunity?: OpportunityContext
}

/**
 * "Dati offerta" of the quote record: every row edits in place on its own
 * control (spec 0197). `code` and the Opportunita' are written at creation
 * only: their field permission turns them read-only on the detail (AC-025/
 * AC-069), so those rows show no pencil there. The status is a row of its own
 * — its value the same pill the header carries — only once a set is resolved.
 */
export function QuoteIdentitySection({
  values,
  control,
  inline,
  status,
  opportunity = NO_OPPORTUNITY_CONTEXT,
}: QuoteIdentitySectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.form.sections.identity.title')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordInlineField
          field="code"
          label={t('quotes.form.code')}
          inline={inline}
          editor={<QuoteCodeField control={control} />}
        >
          {values.code.trim() !== '' ? values.code : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="title"
          label={t('quotes.form.title')}
          inline={inline}
          editor={<QuoteTitleField control={control} />}
        >
          {values.title.trim() !== '' ? (
            values.title
          ) : (
            <span className="text-muted-foreground">{t('quotes.form.titlePlaceholder')}</span>
          )}
        </RecordInlineField>
        {status && values.status ? (
          <RecordInlineField
            field="quote_workflow_status_id"
            label={t('quotes.form.workflowStatus')}
            inline={inline}
            editor={<StatusEditor control={control} {...status} />}
          >
            <WorkflowStatusBadge name={values.status.name} color={values.status.color} />
          </RecordInlineField>
        ) : null}
        <RecordInlineField
          field="opportunity_id"
          label={t('quotes.detail.opportunity')}
          inline={inline}
          editor={<QuoteOpportunityField control={control} selected={values.opportunity} {...opportunity} />}
          canEdit={!opportunity.forceDisabled}
        >
          {values.opportunity ? (
            // The parent record is reachable from here: going back up to the
            // Opportunita' is the most frequent move from an Offerta.
            <RecordLink domain="opportunities" id={values.opportunity.id} className="font-medium text-primary">
              {values.opportunity.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface QuoteInternalNotesRowProps {
  notes: string | null
  control: Control<QuoteFormValues>
  inline: InlineEdit
  className?: string
}

/**
 * "Note interne" across the record's full width, in the amber callout every
 * record shows its notes in (`GeneralNotesCallout`, the field's own look in
 * the form since the user directive 2026-08-06); a click on it (or its pencil)
 * opens the textarea. With no note, an editable record shows a plain empty
 * row to add one, a read-only one shows nothing at all.
 */
export function QuoteInternalNotesRow({ notes, control, inline, className }: QuoteInternalNotesRowProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const label = t('quotes.form.internalNotes')
  const hasNotes = notes !== null && notes.trim() !== ''
  const isEditing = inline.editingField === 'internal_notes'

  if (!hasNotes && !isEditing && !fieldPermission('internal_notes').editable) {
    return null
  }

  return (
    // A list of its own: the row form needs a `<dl>` around it, as in every section.
    <RecordFieldList className={className}>
      <RecordInlineField
        field="internal_notes"
        label={label}
        inline={inline}
        // Open, always a labelled row: a draft emptied while typing must not
        // swap the frame (and remount the textarea) under the cursor.
        layout={hasNotes && !isEditing ? 'block' : 'row'}
        editor={<QuoteInternalNotesField control={control} />}
      >
        {hasNotes ? <GeneralNotesCallout title={label} notes={notes} /> : <DetailEmpty />}
      </RecordInlineField>
    </RecordFieldList>
  )
}
