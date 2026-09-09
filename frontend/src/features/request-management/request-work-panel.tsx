import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { ArrowRightLeft, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Form } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { ResourcePermissionsProvider, useResourcePermissions } from '@/features/authorization/permissions'
import { RecordFieldChangeRequests } from '@/features/field-change-requests/record-field-change-requests'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'
import { AssignOperatorsDialog } from '@/features/leads/assign-operators-dialog'
import { QuoteDynamicFieldsSection } from '@/features/quotes/quote-dynamic-fields-section'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import { QuoteWorkflowStatusField } from '@/features/quotes/quote-workflow-status-field'
import { fetchRequestWorkPanel } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { RequestAttributionSection } from '@/features/request-management/request-attribution-section'
import { RequestTeamSection } from '@/features/request-management/request-team-section'
import { RequestCallbackSection } from '@/features/request-management/request-callback-section'
import { RequestClientSection } from '@/features/request-management/request-client-section'
import { RequestGeneralNotesField } from '@/features/request-management/request-general-notes-field'
import { RequestOfferLinesSection } from '@/features/request-management/request-offer-lines-section'
import { RequestProductLinesSection } from '@/features/request-management/request-product-lines-section'
import { RequestWorkCollaboration } from '@/features/request-management/request-work-collaboration'
import { RequestWorkHeader } from '@/features/request-management/request-work-header'
import { RequestWorkSummary } from '@/features/request-management/request-work-summary'
import { useRequestSiteOperatorLink } from '@/features/request-management/use-request-site-operator-link'
import { useRequestTransfer } from '@/features/request-management/use-request-transfer'
import { useRequestWorkForm } from '@/features/request-management/use-request-work-form'
import type { RequestManagerRef, RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/** Hoisted: `panel.managers ?? []` inline would hand the team editor a new array reference on every render. */
const EMPTY_MANAGERS: RequestManagerRef[] = []

/**
 * DOM id bridging the sticky submit button to the RHF `<form>` (spec 0052
 * F1b): `<NotesSection>` owns its own native `<form>` (composer) and cannot
 * sit inside this one — nested `<form>` elements are invalid HTML and make
 * submit/Enter-key behaviour browser-dependent. The button below stays
 * `type="submit"` via the HTML `form` attribute instead of DOM nesting, and so
 * does its copy in the footer actions.
 */
const REQUEST_WORK_FORM_ID = 'request-work-form'

/**
 * The one protected field of this module (spec 0078, `config/field-change-
 * requests.php`) that is ALSO a control of this form. Approving a request on
 * it writes the new value server-side, so the control must follow it: the
 * refetch alone is not enough — when it settles inside the same React batch
 * the body never remounts, the form keeps its pre-approval value, and
 * `buildRequestWorkPayload` (which diffs the form against the refreshed
 * panel) sends that value back on the next save, undoing the approval.
 */
const SOURCE_FIELD = 'source_id'

/** Props shape matches the module registry's `ModuleDetailScreenProps` (spec 0042), so this mounts as-is as the module's `DetailScreen`. */
interface RequestWorkPanelScreenProps {
  id: number
}

/** Loading placeholder mirroring the panel's real layout: identity bar + two-column body. */
export function RequestWorkPanelSkeleton() {
  return (
    <div className="@container flex flex-1 flex-col bg-surface" aria-hidden="true">
      <div className="flex items-center gap-3 border-b bg-card px-4 py-3">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="h-5 w-24" />
        <Skeleton className="ml-auto h-8 w-20" />
      </div>
      <div className={PANEL_GRID_CLASS}>
        <div className={MAIN_COLUMN_CLASS}>
          {[0, 1, 2].map((section) => (
            <div key={section} className="rounded-xl border bg-card p-4 shadow-sm">
              <Skeleton className="h-3.5 w-40" />
              <Skeleton className="mt-4 h-9 w-full" />
            </div>
          ))}
        </div>
        <div className={SIDE_COLUMN_CLASS}>
          <div className="rounded-xl border bg-card p-4 shadow-sm">
            <Skeleton className="h-3.5 w-32" />
            <Skeleton className="mt-4 h-24 w-full" />
          </div>
        </div>
      </div>
    </div>
  )
}

/**
 * Content-only work-panel screen (spec 0049 AC-061), the module's
 * `DetailScreen` (spec 0042 registry): fetches the panel fresh on every mount
 * (`useEntityDetail`, same fresh-on-open contract as every other entity
 * card/edit form), then renders the read-only context, the contact
 * verification blocks and the dynamic Attribute fields, wrapped in the
 * actor's `ResourcePermissions` so every field's gating comes from the same
 * server-derived source as everywhere else.
 */
export function RequestWorkPanelScreen({ id }: RequestWorkPanelScreenProps) {
  const { t } = useTranslation()
  const { data: panel, isLoading, isError, refetch } = useEntityDetail(
    requestManagementKeys.panel(id),
    () => fetchRequestWorkPanel(id),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('requestManagement.workPanel.loadError', { defaultValue: 'Could not load the record.' })}
        </p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !panel) {
    return <RequestWorkPanelSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={panel.permissions}>
      <RequestWorkPanelBody panel={panel} />
    </ResourcePermissionsProvider>
  )
}

interface RequestWorkPanelBodyProps {
  panel: RequestWorkPanelWithPermissions
}

function RequestWorkPanelBody({ panel }: RequestWorkPanelBodyProps) {
  const { t } = useTranslation()
  const { canAction, canResource } = useResourcePermissions()
  const canUpdate = canResource('update')
  const canViewActivity = canAction('view_activity')
  const { form, onSubmit, submitError, isSubmitting, vatRatePercentFor, rememberVatRatePercent } =
    useRequestWorkForm(panel)
  const queryClient = useQueryClient()
  const transfer = useRequestTransfer(panel)
  // Spec 0097 rev-2 D-7/AC-011: the Sede (Attribuzione) and the operator slot
  // (Team) are two sections apart now, so their reciprocal link is cabled here
  // — where the form they both write actually lives — and each section gets
  // its own half.
  const siteLink = useRequestSiteOperatorLink(form)
  // Watched here so `QuoteWorkflowStatusField` stays presentational: it is
  // what decides whether the transition note is demanded.
  const selectedStatusId = useWatch({ control: form.control, name: 'quote_workflow_status_id' })

  // An approved change request writes the protected field server-side (spec
  // 0078, D-7), so the panel it was decided from is stale the moment it
  // resolves: refetch it, and realign the control the value landed on (see
  // SOURCE_FIELD).
  const handleChangeRequestHandled = (request: FieldChangeRequestResource) => {
    void queryClient.invalidateQueries({ queryKey: requestManagementKeys.panel(panel.id) })

    if (
      request.status === 'approved' &&
      request.field === SOURCE_FIELD &&
      typeof request.requested_value === 'number'
    ) {
      form.setValue(SOURCE_FIELD, request.requested_value)
    }
  }

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <RequestWorkHeader
        panel={panel}
        canUpdate={canUpdate}
        formId={REQUEST_WORK_FORM_ID}
        isSubmitting={isSubmitting}
        isDirty={form.formState.isDirty}
        submitError={submitError}
        canTransfer={transfer.canTransfer}
        onTransfer={transfer.open}
      />

      {/* The RHF provider wraps BOTH columns: the "Note generali" field lives
          in the side column (direttiva utente 2026-09-09) while every other
          control sits in the native <form> below, and they are one form. */}
      <Form {...form}>
        <div className={PANEL_GRID_CLASS}>
          {/* Read-only commercial context: first in the DOM so a narrow container
              reads it before the form, reordered to the right on two columns. */}
          <aside className={SIDE_COLUMN_CLASS}>
            {/* Directive 2026-07-27: the "Note generali" lead the side column —
                operators read them before anything else. EDITABLE since the
                direttiva utente 2026-09-09, so the block is part of the panel's
                form even though it sits in the read-only column: the note is
                written from where it is read, and a request carrying none opens
                on an empty field instead of nothing at all. */}
            <RequestGeneralNotesField control={form.control} name="general_notes" />
            <RequestWorkSummary panel={panel} />

            {/* Field-change-request proposals on this record (spec 0078
                AC-048), generic and domain-agnostic (`RecordFieldChangeRequests`
                only knows `(resource, subjectId)`) — the panel is the only
                thing that knows it is request-management's own record. `id`,
                NOT `opportunity_id` (spec 0086 D-10, revised): unlike
                documents/notes/activity, field change requests are keyed
                through this module's own TableDefinition, whose subject is the
                Quote — `source_id` is exposed on it as a read-through virtual
                attribute. */}
            <FormSection
              icon={ListChecks}
              title={t('fieldChangeRequests.section.title', { defaultValue: 'Change requests' })}
              description={t('fieldChangeRequests.section.description', {
                defaultValue: 'Proposals awaiting approval on this record.',
              })}
              className="min-w-0"
            >
              <RecordFieldChangeRequests
                resource={REQUEST_MANAGEMENT_DOMAIN}
                subjectId={panel.id}
                onHandled={handleChangeRequestHandled}
              />
            </FormSection>
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML submit
                boundary, it must not become an extra flex box in the stack below. */}
            {/* Section order = the operator's working order (user directive
                2026-08-03): what the request is about comes FIRST — the product
                classification is the record's headline information — then the
                working state and the next callback, then the client's data. */}
            <form id={REQUEST_WORK_FORM_ID} onSubmit={onSubmit} className="contents" noValidate>
              {/* Funzione aziendale + categoria prodotto (user directive
                  2026-07-31), right before the working state it precedes. */}
              <RequestProductLinesSection control={form.control} productLines={panel.product_lines} />

              {/* "Linee dell'offerta" (user directive 2026-08-07): right
                  after the classification that scopes its product picker.
                  Literally the Offerte form's row editor, minus the
                  provvigioni block this channel does not own. */}
              <RequestOfferLinesSection
                control={form.control}
                knownLines={panel.offer_lines}
                errors={
                  // Same cast `QuoteFormBody` applies to its own tab: RHF types
                  // an array field's errors as one node, the row editor reads
                  // them per index.
                  form.formState.errors.offer_lines as unknown as (QuoteLineRowErrors | undefined)[] | undefined
                }
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
              />

              {/* "Stato di lavorazione" (user directive 2026-08-07): the
                  Offerta's own operational status, right after the
                  classification that resolves its workflow and before the
                  callback. Literally the Offerte form's component — the set
                  and the mandatory-note rule come from the server either way
                  (spec 0083). */}
              <QuoteWorkflowStatusField
                control={form.control}
                statuses={panel.quote_workflow_statuses}
                originalStatusId={panel.quote_workflow_status_id}
                selectedStatusId={selectedStatusId}
              />

              <RequestCallbackSection control={form.control} />

              {/* Provenance and ownership of the request (user directive
                  2026-07-22), right after the two levers acted on at every
                  touch and before the request's own content. `requestId`:
                  the Fonte picker's field-change-request interception keys
                  on the same quote id as the section above (spec 0086
                  D-10). */}
              <RequestAttributionSection
                form={form}
                requestId={panel.id}
                source={panel.source}
                reporter={panel.reporter}
                operationalSite={toRelationFieldRef(panel.operational_site)}
                rewards={panel.rewards ?? []}
                autoFilledSite={siteLink.autoFilledSite}
                onSiteItemChange={siteLink.onSiteItemChange}
              />

              {/* Right after the Sede that scopes its operator slot (spec
                  0097 rev-2 D-7): the Offerta's Supervisore and its whole
                  team, in the Offerte form's own editor. */}
              <RequestTeamSection
                control={form.control}
                managers={panel.managers ?? EMPTY_MANAGERS}
                supervisor={panel.supervisor}
                managerLabels={panel.manager_labels}
                siteId={siteLink.siteId}
                slotParamsFor={siteLink.slotParamsFor}
                onSlotItemChange={siteLink.onSlotItemChange}
              />

              <RequestClientSection control={form.control} />

              {/* "Informazioni aggiuntive" (user directive 2026-08-07): the
                  Offerte form's own section, fed the set the server resolved
                  for this request. Already resolved on load, so it never
                  loads on its own here. */}
              <QuoteDynamicFieldsSection
                control={form.control}
                attributes={panel.applicable_attributes}
                layout={panel.attribute_layout}
                isLoading={false}
              />

              {/* The same save the identity bar carries, repeated where the
                  editable form ends (user directive 2026-08-03): the panel is
                  long, and the collaboration block below persists on its own.
                  No cancel here — this edits a persisted record. */}
              {canUpdate && (
                <RecordFormActions
                  formId={REQUEST_WORK_FORM_ID}
                  isSubmitting={isSubmitting}
                  submitLabel={t('requestManagement.workPanel.save')}
                  submittingLabel={t('requestManagement.workPanel.saving')}
                  isSubmitDisabled={!form.formState.isDirty}
                  leadingActions={
                    transfer.canTransfer ? (
                      <Button type="button" variant="outline" onClick={transfer.open}>
                        <ArrowRightLeft className="size-4" aria-hidden="true" />
                        {t('actions.transferContact')}
                      </Button>
                    ) : undefined
                  }
                />
              )}
            </form>

          {/* Notes/documents/history: own authorization (spec 0052 D-6), shown to
              any actor who can read the record. The notes composer has its own
              native `<form>`, so it cannot nest inside the one above (see
              REQUEST_WORK_FORM_ID). */}
          <RequestWorkCollaboration panel={panel} canViewActivity={canViewActivity} />
          </div>
        </div>
      </Form>

      {/* Same dialog the table's row/bulk "Trasferisci contatto" drives
          (spec 0079), locked to this one record: a row transfer is just a
          one-element selection. */}
      <AssignOperatorsDialog
        open={transfer.isOpen}
        onOpenChange={transfer.onOpenChange}
        selectionCount={1}
        defaultSite={transfer.defaultSite}
        lockedMode="single"
        copy={transfer.copy}
        {...transfer.competence}
        onAssign={transfer.handleTransfer}
      />
    </div>
  )
}
