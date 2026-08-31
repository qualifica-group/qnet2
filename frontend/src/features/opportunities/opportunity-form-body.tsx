import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { OpportunityAttributionSection } from '@/features/opportunities/opportunity-attribution-section'
import { OpportunityClientSection } from '@/features/opportunities/opportunity-client-section'
import { OpportunityFormHeader } from '@/features/opportunities/opportunity-form-header'
import { OpportunityFormSummary } from '@/features/opportunities/opportunity-form-summary'
import { OpportunityGeneralNotesSection } from '@/features/opportunities/opportunity-general-notes-section'
import { OpportunityLeadSection } from '@/features/opportunities/opportunity-lead-section'
import { OpportunityPlanningSection } from '@/features/opportunities/opportunity-planning-section'
import { OpportunityProductLinesSection } from '@/features/opportunities/opportunity-product-lines-section'
import { OpportunityTeamSection } from '@/features/opportunities/opportunity-team-section'
import {
  NO_LEAD_SUBMISSION,
  useOpportunityForm,
  useOpportunityFormSubmit,
  type LeadSubmissionState,
} from '@/features/opportunities/use-opportunity-form'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import { useOpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'
import type { OpportunityDetail, OpportunityFormMode, OpportunityProductLine } from '@/features/opportunities/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** Stable empty default: create mode has no persisted reward assignments to hydrate. */
const EMPTY_REWARDS: RewardAssignmentRef[] = []

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the Gestione Richieste screens do: the same id serves the footer
 * actions, so both copies of the button submit this form without either of
 * them nesting the other.
 */
const OPPORTUNITY_FORM_ID = 'opportunity-form'

interface OpportunityFormBodyProps {
  mode: OpportunityFormMode
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * The opportunity create/edit form UI (spec 0040 + amendment rev.1), rebuilt
 * as the TWIN of the Gestione Richieste screens (user directive 2026-08-05:
 * "voglio che siano uguali di posizione e di design"). Not a resemblance: the
 * layout primitives are literally the same objects (`@/components/record-form`
 * — `RECORD_HEADER_CLASS`, `PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/
 * `MAIN_COLUMN_CLASS`, `SummaryRow`, `RecordFormActions`, the general-notes
 * callout chrome), so neither screen can drift apart with a later edit to the
 * other.
 *
 * Same skeleton as the work panel:
 *  - `@container` + `bg-surface`, sticky identity bar carrying the live status
 *    pills and the save/cancel actions, repeated at the foot of the form;
 *  - two columns at `@4xl` — the read-only side column FIRST in the DOM
 *    (narrow containers read it before the long form), reordered to the right;
 *  - side column = the "Note generali" callout on top, then the live summary;
 *  - main column = origin, then the record's headline classification (product
 *    lines, products of interest), the two state dimensions next to the
 *    planning estimates, attribution, team, and the client's data last.
 *
 * Nothing was dropped in the move: the dimensions Gestione Richieste has no
 * equivalent for (the computed status, the G.A. slots and the Supervisore, the
 * planning estimates, the Lead link) keep their own cards inside that
 * skeleton. All non-render logic still lives in `useOpportunityForm`/
 * `useOpportunityFormSubmit` and `useOpportunityLeadSelection`; BR-2 field
 * locking applies uniformly whether the lead came from the `?lead_id=N`
 * deep-link or from picking one in the select.
 */
export function OpportunityFormBody({ mode, onSuccess, onCancel }: OpportunityFormBodyProps) {
  const { t } = useTranslation()

  const { form } = useOpportunityForm({ mode })

  // `useOpportunityLeadSelection` needs `form.setValue`, so it can only run
  // AFTER `useOpportunityForm` — and `leadSubmission` (below) can only be
  // computed after `leadSelection` exists. This ordering, not a ref, is what
  // keeps `useOpportunityFormSubmit`'s `onSubmit` un-stale (`react-hooks/refs`
  // disallows writing to a ref during render; this needs no ref at all).
  const initialLead = mode.type === 'create' ? mode.fromLead : undefined
  const leadSelection = useOpportunityLeadSelection(
    initialLead
      ? {
          leadId: initialLead.leadId,
          lockedFields: initialLead.lockedFields,
          registry: initialLead.references.registry,
        }
      : null,
    form.setValue,
    form.getValues,
  )

  const leadIsBlocked = leadSelection.state.existingOpportunityId !== null
  const leadSubmission: LeadSubmissionState =
    mode.type === 'create'
      ? {
          blocked: leadIsBlocked,
          fromLead:
            leadSelection.state.leadId !== null && !leadIsBlocked
              ? { leadId: leadSelection.state.leadId, lockedFields: leadSelection.state.lockedFields }
              : null,
        }
      : NO_LEAD_SUBMISSION

  const { serverError, blockingOpportunity, onSubmit } = useOpportunityFormSubmit({
    form,
    mode,
    leadSubmission,
    onSuccess,
  })
  const selectedItems = useOpportunitySelectedItems(mode, leadSelection.state)

  // Amendment rev.3: rows whose label is already known without a fetch — the
  // loaded instance (edit) or the from-lead prefill (deep-link create) at
  // mount, plus whatever the in-form Lead picker resolves afterwards.
  const mountProductLines: OpportunityProductLine[] =
    mode.type === 'edit' ? mode.opportunity.product_lines : (mode.fromLead?.productLines ?? [])
  const knownProductLines = [...mountProductLines, ...leadSelection.state.derivedProductLines]

  // Products of interest exist only on a loaded opportunity: a lead has none,
  // so create mode starts with nothing to hydrate.
  const knownProductsOfInterest = mode.type === 'edit' ? (mode.opportunity.products_of_interest ?? []) : []

  // BR-2: the fields derived from a linked Lead are immutable — both when
  // editing an opportunity that already has one, and while creating one
  // (deep-link or in-form select, unified by `useOpportunityLeadSelection`).
  const lockedFields = new Set(
    mode.type === 'edit' ? mode.opportunity.locked_fields : leadSelection.state.lockedFields,
  )

  const { isSubmitting } = form.formState

  const initialRewards = mode.type === 'edit' ? (mode.opportunity.rewards ?? EMPTY_REWARDS) : EMPTY_REWARDS

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      {/* The provider wraps BOTH columns (it renders no DOM of its own): the
          side column's "Note generali" is a form field like any other, it just
          reads better next to the summary than buried in the long column. */}
      <Form {...form}>
        <OpportunityFormHeader
          control={form.control}
          isEdit={mode.type === 'edit'}
          status={mode.type === 'edit' ? mode.opportunity.status : null}
          formId={OPPORTUNITY_FORM_ID}
          isSubmitting={isSubmitting}
          isSubmitDisabled={leadIsBlocked}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads it before the form,
              reordered to the right on two columns — the panel's own rule. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <OpportunityGeneralNotesSection control={form.control} />
            <OpportunityFormSummary control={form.control} selectedItems={selectedItems} />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={OPPORTUNITY_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <OpportunityLeadSection
                mode={mode}
                leadSelection={leadSelection.state}
                onSelect={leadSelection.selectLead}
              />

              <OpportunityProductLinesSection
                control={form.control}
                knownProductLines={knownProductLines}
                knownProductsOfInterest={knownProductsOfInterest}
              />

              {/* Spec 0083: l'Opportunita' non ha piu' uno stato di lavorazione
                  proprio (lo stato vive sull'Offerta), quindi la pianificazione
                  prende l'intera riga. Lo stato calcolato non si ripete qui: lo
                  porta la barra di identita'. */}
              <OpportunityPlanningSection control={form.control} />

              <OpportunityAttributionSection
                control={form.control}
                setValue={form.setValue}
                selectedItems={selectedItems}
                lockedFields={lockedFields}
                initialRewards={initialRewards}
              />

              <OpportunityTeamSection
                control={form.control}
                selectedItems={selectedItems}
                // Directive 2026-07-21: supervisor_id is never required — it
                // derives from the linked Lead's Operatore, which may be empty.
                supervisorRequired={false}
              />

              <OpportunityClientSection
                control={form.control}
                setValue={form.setValue}
                selectedItems={selectedItems}
                lockedFields={lockedFields}
                blockingOpportunity={blockingOpportunity}
              />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={OPPORTUNITY_FORM_ID}
                isSubmitting={isSubmitting}
                isSubmitDisabled={leadIsBlocked}
                submitLabel={t('opportunities.form.save')}
                submittingLabel={t('opportunities.form.saving')}
                cancel={{ label: t('opportunities.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
