import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface RequestFormActionsProps {
  /** id of the RHF `<form>` the submit attaches to via the HTML `form=` attribute, exactly as the identity bar does. */
  formId: string
  isSubmitting: boolean
  submitLabel: string
  submittingLabel: string
  /** Keeps the submit unavailable while there is nothing to send (the work panel: no pending edit). */
  isSubmitDisabled?: boolean
  /**
   * Omitted where the screen has nothing to cancel to: the work panel edits a
   * persisted record and carries no cancel (user directive 2026-08-03).
   */
  cancel?: { label: string; onCancel: () => void }
}

/**
 * Action bar repeated at the FOOT of the request forms (user directive
 * 2026-08-03): both screens are long enough that the operator finishes typing
 * far from the identity bar, so the same actions close the form where the
 * reading ends. Shared by the create form and the work panel so the two can
 * never offer a different footer.
 *
 * It duplicates the header's actions, it does not replace them: the bar stays
 * sticky and keeps reporting a refused submit, which is why no error is
 * rendered here — the message would land off-screen for whoever pressed this
 * button, while the sticky bar is visible at every scroll position.
 */
export function RequestFormActions({
  formId,
  isSubmitting,
  submitLabel,
  submittingLabel,
  isSubmitDisabled = false,
  cancel,
}: RequestFormActionsProps) {
  return (
    <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-4">
      {cancel && (
        <Button type="button" variant="secondary" onClick={cancel.onCancel} disabled={isSubmitting}>
          {cancel.label}
        </Button>
      )}
      <Button type="submit" form={formId} disabled={isSubmitting || isSubmitDisabled}>
        {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
        {isSubmitting ? submittingLabel : submitLabel}
      </Button>
    </div>
  )
}
