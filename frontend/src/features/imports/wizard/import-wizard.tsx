import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Columns3, FileCheck2, FileUp, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Stepper, type StepperStep } from '@/components/ui/stepper'
// Side effect: registers the `importWizard` i18next namespace (see the
// module doc comment) before this component's own `t()` calls run.
import '@/features/imports/wizard/i18n'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import { ImportStepReview } from '@/features/imports/wizard/import-step-review'
import { ImportStepSummary } from '@/features/imports/wizard/import-step-summary'
import { ImportStepUpload } from '@/features/imports/wizard/import-step-upload'
import { useImportWizard } from '@/features/imports/wizard/use-import-wizard'
import { StepAlert } from '@/features/imports/wizard/wizard-ui'

/** Project idiom for step entrance (same as the Projects/Campaigns form bodies). */
const STEP_TRANSITION_CLASSES =
  'flex flex-col gap-4 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300'

export interface ImportWizardProps {
  /** Resource key selecting the backend `ImportDefinition`, e.g. `leads`. */
  domain: string
}

/** URL query param the wizard resumes an in-progress run from (AC-025). */
const RUN_ID_PARAM = 'runId'

function parseRunId(value: string | null): number | null {
  if (!value) return null
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

/**
 * Full-page wizard orchestrator (spec 0033): mounts the 4-step stepper and
 * routes to the step matching the run's server-authoritative status (see
 * `useImportWizard`). Column mapping and global configuration share one step
 * (campaign scope must be set before staging). The review/summary steps
 * (`ImportStepReview`/`ImportStepSummary`) own their own content — this
 * component only wires their props.
 */
export function ImportWizard({ domain }: ImportWizardProps) {
  const { t } = useTranslation('importWizard')
  const title = t('page.title')
  const [searchParams, setSearchParams] = useSearchParams()
  const initialRunId = parseRunId(searchParams.get(RUN_ID_PARAM))

  const wizard = useImportWizard({
    domain,
    initialRunId,
    onRunCreated: (runId) => {
      const next = new URLSearchParams(searchParams)
      next.set(RUN_ID_PARAM, String(runId))
      setSearchParams(next, { replace: true })
    },
  })

  const steps: StepperStep[] = useMemo(
    () => [
      { key: 'upload', label: t('stepper.upload'), icon: FileUp },
      { key: 'mapping', label: t('stepper.mapping'), icon: Columns3 },
      { key: 'review', label: t('stepper.review'), icon: ListChecks },
      { key: 'summary', label: t('stepper.summary'), icon: FileCheck2 },
    ],
    [t],
  )

  if (wizard.isRunLoading) {
    return (
      <Card aria-hidden="true">
        <CardHeader className="gap-4 border-b">
          <Skeleton className="h-5 w-40" />
          <Skeleton className="h-7 w-full" />
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <Skeleton className="h-40 w-full" />
        </CardContent>
      </Card>
    )
  }

  if (wizard.isRunError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <StepAlert>{t('page.loadError')}</StepAlert>
        <Button type="button" variant="outline" size="sm" onClick={() => wizard.refetchRun()}>
          {t('config.select.retry')}
        </Button>
      </div>
    )
  }

  return (
    <Card>
      <CardHeader className="gap-4 border-b">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <CardTitle className="text-base">{title}</CardTitle>
          <span className="text-xs tabular-nums text-muted-foreground">
            {t('stepper.progress', { current: wizard.currentStep + 1, total: steps.length })}
          </span>
        </div>
        <Stepper steps={steps} currentStep={wizard.currentStep} onStepClick={wizard.goToStep} />
      </CardHeader>
      <CardContent>
        {/* Keyed on the step: remounting the wrapper replays the entrance animation on every step change. */}
        <div key={wizard.currentStep} className={STEP_TRANSITION_CLASSES}>
        {wizard.currentStep === 0 ? (
        <ImportStepUpload
          run={wizard.run}
          isUploading={wizard.isUploading}
          uploadError={wizard.uploadError}
          onUpload={wizard.upload}
          onContinue={wizard.advanceFromUpload}
          isPollingStalled={wizard.isPollingStalled}
          onRetryPolling={wizard.retryPolling}
        />
      ) : null}

      {wizard.currentStep === 1 ? (
        <ImportStepMapping
          run={wizard.run}
          initialMapping={wizard.mappingValues}
          initialDedupStrategy={wizard.dedupStrategy}
          initialConfig={wizard.configValues}
          onSubmit={wizard.submitMapping}
          isSubmitting={wizard.isConfiguring}
          submitError={wizard.configureError}
        />
      ) : null}

      {wizard.currentStep === 2 ? (
        <ImportStepReview
          domain={domain}
          run={wizard.run}
          onContinue={wizard.advanceFromReview}
          isPollingStalled={wizard.isPollingStalled}
          onRetryPolling={wizard.retryPolling}
        />
      ) : null}

      {wizard.currentStep === 3 ? (
        <ImportStepSummary
          domain={domain}
          run={wizard.run}
          onConfirm={wizard.confirm}
          onBackToReview={() => wizard.goToStep(2)}
          isConfirming={wizard.isConfirming}
          confirmError={wizard.confirmError}
          isPollingStalled={wizard.isPollingStalled}
          onRetryPolling={wizard.retryPolling}
        />
      ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
