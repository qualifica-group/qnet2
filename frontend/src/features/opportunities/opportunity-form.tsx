import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useOpportunityFormMeta } from '@/features/opportunities/use-opportunity-form-meta'
import { OpportunityFormBody } from '@/features/opportunities/opportunity-form-body'
import type { OpportunityDetail, OpportunityFormMode } from '@/features/opportunities/types'

interface OpportunityFormProps {
  mode: OpportunityFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (opportunity: OpportunityDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Loading placeholder mirroring the form's real layout — identity bar plus the
 * two-column body — so the swap to the loaded form does not shift the page.
 * Shared with `OpportunityFormPage`'s edit-fetch state.
 */
export function OpportunityFormSkeleton() {
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
 * Reusable RHF + Zod form used for both creating and editing an opportunity
 * (spec 0040). Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded instance
 * detail, create mode from `GET /meta/opportunities` — then hands off to
 * `OpportunityFormBody`.
 */
export function OpportunityForm(props: OpportunityFormProps) {
  const { t } = useTranslation()
  const meta = useOpportunityFormMeta(props.mode)

  if (meta.status === 'loading') {
    return <OpportunityFormSkeleton />
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <OpportunityFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
