import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CircleAlert, Contact, Loader2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Form } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { cn } from '@/lib/utils'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OpportunityRegistryField } from '@/features/opportunities/opportunity-registry-field'
import { OpportunityClassificationSection } from '@/features/opportunities/opportunity-classification-section'
import { OpportunityProductLinesSection } from '@/features/opportunities/opportunity-product-lines-section'
import { OpportunityTeamSection } from '@/features/opportunities/opportunity-team-section'
import { OpportunityPlanningSection } from '@/features/opportunities/opportunity-planning-section'
import { OpportunityFromLeadBanner } from '@/features/opportunities/opportunity-from-lead-banner'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import { OpportunityLeadField } from '@/features/opportunities/opportunity-lead-field'
import { OpportunityReporterField } from '@/features/opportunities/opportunity-reporter-field'
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

interface OpportunityFormBodyProps {
  mode: OpportunityFormMode
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/** Motion-safe staggered entrance shared by every top-level section. */
const SECTION_REVEAL_CLASS =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300'

/** Staggers a section's entrance by 50ms per index via an arbitrary Tailwind property. */
function sectionRevealClassName(index: number): string {
  return cn(SECTION_REVEAL_CLASS, `[animation-delay:${index * 50}ms]`)
}

/**
 * The opportunity create/edit form UI (spec 0040 + amendment rev.1): an
 * optional in-form "Lead" picker in create (A-1) or its read-only equivalent
 * in edit (D-2), identity (the required anagrafica and its 3 BR-4 scoped
 * relations — spec 0057 D-5: the name is no longer an input, it is derived
 * server-side as `OPP_{id}`), classification (source), team (supervisor +
 * managers) and planning (dates/value/probability) — all wrapped in
 * `MetaField`. All non-render logic lives in
 * `useOpportunityForm`/`useOpportunityFormSubmit` and
 * `useOpportunityLeadSelection`. BR-2 field locking (from a linked Lead)
 * applies uniformly whether the lead came from the `?lead_id=N` deep-link or
 * from picking one in the select (`useOpportunityLeadSelection` unifies
 * both).
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

  const { serverError, onSubmit } = useOpportunityFormSubmit({ form, mode, leadSubmission, onSuccess })
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

  const { errors, isSubmitting } = form.formState
  const [planningOpen, setPlanningOpen] = useState(false)
  const planningHasError = Boolean(
    errors.start_date || errors.expected_close_date || errors.estimated_value || errors.success_probability,
  )

  const registryId = useWatch({ control: form.control, name: 'registry_id' })
  const registryChosen = registryId !== null

  // Spec 0047 (D1): the Regione is inherited from the Lead only as an initial
  // value and stays freely editable at any time (backend keeps state_id out of
  // the locked fields). The working-state select is limited to the resolved set
  // exposed on the loaded instance; `null` in create mode (set not yet known).
  const workflowStatuses = mode.type === 'edit' ? (mode.opportunity.workflow_statuses ?? []) : null

  // A-4: recap of the chosen person's primary contacts, under each of the 3
  // selects. commercial/reporter (A-3) are the whole platform list, independent
  // of the anagrafica; only the referent stays anagrafica-scoped (BR-4).
  const referentId = useWatch({ control: form.control, name: 'referent_id' })
  const commercialId = useWatch({ control: form.control, name: 'commercial_id' })
  const reporterId = useWatch({ control: form.control, name: 'reporter_id' })
  const rewards = useWatch({ control: form.control, name: 'rewards' })
  const initialRewards = mode.type === 'edit' ? (mode.opportunity.rewards ?? EMPTY_REWARDS) : EMPTY_REWARDS

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={Contact}
            title={t('opportunities.form.sections.identity.title')}
            description={t('opportunities.form.sections.identity.description')}
            className={sectionRevealClassName(0)}
          >
            {mode.type === 'create' ? (
              <OpportunityLeadField state={leadSelection.state} onSelect={leadSelection.selectLead} />
            ) : mode.opportunity.lead ? (
              <div className="grid gap-2">
                <Label>{t('opportunities.form.lead')}</Label>
                <Input value={mode.opportunity.lead.label} disabled readOnly />
              </div>
            ) : null}

            {mode.type === 'create' && leadSelection.state.leadId !== null && !leadIsBlocked ? (
              <OpportunityFromLeadBanner registryName={leadSelection.state.registry?.name ?? null} />
            ) : null}

            <OpportunityRegistryField
              control={form.control}
              setValue={form.setValue}
              selected={selectedItems.registry}
              forceDisabled={lockedFields.has('registry_id')}
            />

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="flex flex-col gap-1.5">
                <RelationSelectField
                  control={form.control}
                  name="referent_id"
                  metaKey="referent_id"
                  label={t('opportunities.form.referent')}
                  resource={REFERENTS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('opportunities.form.referentSearch')}
                  selected={selectedItems.referent}
                  params={registryId !== null ? { registry_id: registryId } : undefined}
                  forceDisabled={!registryChosen || lockedFields.has('referent_id')}
                  placeholder={t('opportunities.form.selectPlaceholder')}
                  emptyLabel={t('opportunities.form.selectEmpty')}
                  errorLabel={t('opportunities.form.selectError')}
                  clearLabel={t('common.clear')}
                  retryLabel={t('common.retry')}
                />
                <OpportunityContactRecap referentId={referentId} />
              </div>

              <div className="flex flex-col gap-1.5">
                <RelationSelectField
                  control={form.control}
                  name="commercial_id"
                  metaKey="commercial_id"
                  label={t('opportunities.form.commercial')}
                  resource={REFERENTS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('opportunities.form.commercialSearch')}
                  selected={selectedItems.commercial}
                  placeholder={t('opportunities.form.selectPlaceholder')}
                  emptyLabel={t('opportunities.form.selectEmpty')}
                  errorLabel={t('opportunities.form.selectError')}
                  clearLabel={t('common.clear')}
                  retryLabel={t('common.retry')}
                />
                <OpportunityContactRecap referentId={commercialId} />
              </div>

              <OpportunityReporterField
                control={form.control}
                selected={selectedItems.reporter}
                reporterId={reporterId}
                rewards={rewards}
                onRewardsChange={(next) => form.setValue('rewards', next, { shouldDirty: true })}
                initialRewards={initialRewards}
              />
            </div>
          </FormSection>

          <OpportunityClassificationSection
            control={form.control}
            selectedItems={selectedItems}
            lockedFields={lockedFields}
            workflowStatuses={workflowStatuses}
            className={sectionRevealClassName(1)}
          />

          <OpportunityProductLinesSection
            control={form.control}
            knownProductLines={knownProductLines}
            knownProductsOfInterest={knownProductsOfInterest}
            className={sectionRevealClassName(2)}
          />

          <OpportunityTeamSection
            control={form.control}
            selectedItems={selectedItems}
            // Directive 2026-07-21: supervisor_id is never required — it
            // derives from the linked Lead's Operatore, which may be empty.
            supervisorRequired={false}
            className={sectionRevealClassName(3)}
          />

          <OpportunityPlanningSection
            control={form.control}
            collapsible
            open={planningOpen || planningHasError}
            onOpenChange={setPlanningOpen}
            className={sectionRevealClassName(4)}
          />

          {serverError && (
            <div
              role="alert"
              className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
            >
              <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
              {serverError}
            </div>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
              {t('opportunities.form.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting || leadIsBlocked}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('opportunities.form.saving') : t('opportunities.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
