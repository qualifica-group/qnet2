import type { FormEventHandler, ReactNode } from 'react'
import { Loader2, TriangleAlert } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { Button } from '@/components/ui/button'

interface RecordCreateLayoutLabels {
  save: string
  saving: string
  cancel: string
}

interface RecordCreateLayoutProps {
  /** id of the native `<form>`: the header's and the footer's Save attach to it. */
  formId: string
  onSubmit: FormEventHandler<HTMLFormElement>
  /** The identity band: the name being typed, or the module's "new record" title while empty. */
  title: string
  subtitle?: string
  media: ReactNode
  isSubmitting: boolean
  /** A refused create no field message can carry. */
  serverError: string | null
  onCancel: () => void
  labels: RecordCreateLayoutLabels
  /** The record card's body: the same sections as the detail, rows closed. */
  children: ReactNode
  /** Side column (e.g. a live preview); absent = full-width form. */
  side?: ReactNode
}

/**
 * The create form of a record as a replica of its detail (spec 0195 D-8, the
 * kit of the "no edit pages" plan): the record card with its identity band
 * (live title, Annulla/Salva), the caller's sections, and the same actions
 * repeated where the form ends. Render it inside the record's `<Form>`
 * provider; the caller owns the leave guard (`useFormLeaveGuard`).
 */
export function RecordCreateLayout({
  formId,
  onSubmit,
  title,
  subtitle,
  media,
  isSubmitting,
  serverError,
  onCancel,
  labels,
  children,
  side,
}: RecordCreateLayoutProps) {
  return (
    // `display: contents`: the native `<form>` only scopes the HTML submit
    // boundary, it must not become an extra box around the canvas.
    <form id={formId} onSubmit={onSubmit} className="contents" noValidate>
      <RecordCanvas>
        <RecordFormBody side={side}>
          <RecordCard>
            <RecordCardHeader
              media={media}
              title={title}
              subtitle={subtitle}
              actions={
                <>
                  <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                    {labels.cancel}
                  </Button>
                  <Button type="submit" form={formId} disabled={isSubmitting}>
                    {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                    {isSubmitting ? labels.saving : labels.save}
                  </Button>
                </>
              }
            />
            {serverError ? (
              <div
                role="alert"
                className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
              >
                <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                {serverError}
              </div>
            ) : null}
            {children}
          </RecordCard>

          <RecordFormActions
            formId={formId}
            isSubmitting={isSubmitting}
            submitLabel={labels.save}
            submittingLabel={labels.saving}
            cancel={{ label: labels.cancel, onCancel }}
          />
        </RecordFormBody>
      </RecordCanvas>
    </form>
  )
}
