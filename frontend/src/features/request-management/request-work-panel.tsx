import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Form } from '@/components/ui/form'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { ResourcePermissionsProvider, useResourcePermissions } from '@/features/authorization/permissions'
import { fetchRequestWorkPanel } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { RequestAttributionSection } from '@/features/request-management/request-attribution-section'
import { RequestCallbackSection } from '@/features/request-management/request-callback-section'
import { RequestClientSection } from '@/features/request-management/request-client-section'
import { RequestDynamicFields } from '@/features/request-management/request-dynamic-fields'
import { RequestGeneralNotesCallout } from '@/features/request-management/request-general-notes-callout'
import { RequestProductLinesSection } from '@/features/request-management/request-product-lines-section'
import { RequestProductsOfInterest } from '@/features/request-management/request-products-of-interest'
import { RequestWorkCollaboration } from '@/features/request-management/request-work-collaboration'
import { RequestWorkHeader } from '@/features/request-management/request-work-header'
import { RequestWorkSummary } from '@/features/request-management/request-work-summary'
import { RequestWorkflowStatusField } from '@/features/request-management/request-workflow-status-field'
import { useRequestWorkForm } from '@/features/request-management/use-request-work-form'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * DOM id bridging the sticky submit button to the RHF `<form>` (spec 0052
 * F1b): `<NotesSection>` owns its own native `<form>` (composer) and cannot
 * sit inside this one — nested `<form>` elements are invalid HTML and make
 * submit/Enter-key behaviour browser-dependent. The button below stays
 * `type="submit"` via the HTML `form` attribute instead of DOM nesting.
 */
const REQUEST_WORK_FORM_ID = 'request-work-form'

/**
 * The panel is mounted both in its dedicated page and in a Sheet, so the
 * two-column split must react to the CONTAINER width, not the viewport:
 * `@container` + `@4xl:` (56rem) instead of `lg:`/`xl:`. Below that width the
 * whole panel collapses to a single column.
 */
export const PANEL_GRID_CLASS = 'grid items-start gap-4 p-4 @4xl:grid-cols-[minmax(0,1fr)_20rem]'

/** Clears the sticky header (`py-3` around a badge row) so the side column never scrolls under it. */
export const SIDE_COLUMN_CLASS = 'flex min-w-0 flex-col gap-4 @4xl:sticky @4xl:top-16 @4xl:order-2'

/** The main column: its own `@container`, so the sections split on ITS width, not the panel's. */
export const MAIN_COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4 @4xl:order-1'

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
 * verification blocks, the dynamic Attribute fields and the working-state
 * control, wrapped in the actor's `ResourcePermissions` so every field's
 * gating comes from the same server-derived source as everywhere else.
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
  const { canAction, canResource } = useResourcePermissions()
  const canUpdate = canResource('update')
  const canViewActivity = canAction('view_activity')
  const { form, onSubmit, submitError, isSubmitting } = useRequestWorkForm(panel)

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <RequestWorkHeader
        panel={panel}
        canUpdate={canUpdate}
        formId={REQUEST_WORK_FORM_ID}
        isSubmitting={isSubmitting}
        isDirty={form.formState.isDirty}
        submitError={submitError}
      />

      <div className={PANEL_GRID_CLASS}>
        {/* Read-only commercial context: first in the DOM so a narrow container
            reads it before the form, reordered to the right on two columns. */}
        <aside className={SIDE_COLUMN_CLASS}>
          {/* Directive 2026-07-27: the "Note generali" lead the side column —
              operators read them before anything else. */}
          <RequestGeneralNotesCallout notes={panel.context.general_notes ?? null} />
          <RequestWorkSummary panel={panel} />
        </aside>

        <div className={MAIN_COLUMN_CLASS}>
          <Form {...form}>
            {/* `display: contents`: this native `<form>` only scopes the HTML submit
                boundary, it must not become an extra flex box in the stack below. */}
            {/* Section order = the operator's working order: the working state and
                the next callback first (the levers acted on at every touch), then
                what the request needs (dynamic fields), then the client's data. */}
            <form id={REQUEST_WORK_FORM_ID} onSubmit={onSubmit} className="contents" noValidate>
              <div className="grid min-w-0 items-start gap-4 @2xl:grid-cols-2">
                <RequestWorkflowStatusField control={form.control} statuses={panel.workflow_statuses} />

                <RequestCallbackSection control={form.control} />
              </div>

              {/* Provenance and ownership of the request (user directive
                  2026-07-22), right after the two levers acted on at every
                  touch and before the request's own content. */}
              <RequestAttributionSection
                form={form}
                source={panel.source}
                reporter={panel.reporter}
                operator={panel.operator}
                operationalSite={toRelationFieldRef(panel.operational_site)}
                rewards={panel.rewards ?? []}
              />

              <RequestDynamicFields
                control={form.control}
                attributes={panel.applicable_attributes}
                layout={panel.attribute_layout}
              />

              {/* Funzione aziendale + categoria prodotto (user directive
                  2026-07-31), right before the picker they scope. */}
              <RequestProductLinesSection control={form.control} productLines={panel.product_lines} />

              {/* Right after the preliminary information: the products of
                  interest are collected in the same phone call (user directive
                  2026-07-22). */}
              <RequestProductsOfInterest
                control={form.control}
                products={panel.products_of_interest}
              />

              <RequestClientSection control={form.control} />
            </form>
          </Form>

          {/* Notes/documents/history: own authorization (spec 0052 D-6), shown to
              any actor who can read the record. The notes composer has its own
              native `<form>`, so it cannot nest inside the one above (see
              REQUEST_WORK_FORM_ID). */}
          <RequestWorkCollaboration panel={panel} canViewActivity={canViewActivity} />
        </div>
      </div>
    </div>
  )
}
