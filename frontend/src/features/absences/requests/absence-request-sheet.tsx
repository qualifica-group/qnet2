import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { AbsenceRequestFields } from '@/features/absences/requests/absence-request-fields'
import { useAbsenceRequestForm } from '@/features/absences/requests/use-absence-request-form'
import { useAbsenceTypeOptions } from '@/features/absences/requests/use-absence-type-options'
import type { AbsenceRequest } from '@/features/absences/types'

const ATTACHMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png'
const ATTACHMENT_INPUT_ID = 'absence-request-attachment'

interface AbsenceRequestFormProps {
  request?: AbsenceRequest
  refreshGrid?: () => void
  onSaved: (saved: AbsenceRequest) => void
  onClose: () => void
}

function AbsenceRequestForm({ request, refreshGrid, onSaved, onClose }: AbsenceRequestFormProps) {
  const { t } = useTranslation()
  const types = useAbsenceTypeOptions()
  const { form, file, setFile, isSubmitting, saveDraft, send } = useAbsenceRequestForm({
    request,
    refreshGrid,
    onSaved,
  })

  return (
    <Form {...form}>
      <form onSubmit={(event) => event.preventDefault()} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-4">
          <AbsenceRequestFields
            types={types.data ?? []}
            typesError={types.isError}
            typesEmpty={types.isSuccess && types.data.length === 0}
          />
          <div className="grid gap-1.5">
            <Label htmlFor={ATTACHMENT_INPUT_ID}>{t('absences.requests.form.attachment')}</Label>
            <Input
              id={ATTACHMENT_INPUT_ID}
              type="file"
              accept={ATTACHMENT_ACCEPT}
              aria-describedby={`${ATTACHMENT_INPUT_ID}-hint`}
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
            <p id={`${ATTACHMENT_INPUT_ID}-hint`} className="text-xs text-muted-foreground">
              {file
                ? t('absences.requests.form.attachmentSelected', { name: file.name })
                : t('absences.requests.form.attachmentHint')}
            </p>
          </div>
        </div>
        <SheetFooter className="border-t bg-surface sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('absences.requests.form.cancel')}
          </Button>
          <Button type="button" variant="secondary" disabled={isSubmitting} onClick={() => void saveDraft()}>
            {t('absences.requests.form.saveDraft')}
          </Button>
          <Button type="button" disabled={isSubmitting} onClick={() => void send()}>
            {t('absences.requests.form.send')}
          </Button>
        </SheetFooter>
      </form>
    </Form>
  )
}

interface AbsenceRequestSheetProps extends Omit<AbsenceRequestFormProps, 'onClose'> {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Right-hand Sheet creating a request or editing a draft (spec 0278 AC-018). */
export function AbsenceRequestSheet({ open, onOpenChange, request, refreshGrid, onSaved }: AbsenceRequestSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:absence-requests-form">
        <SheetHeader>
          <SheetTitle>{t(request ? 'absences.requests.form.editTitle' : 'absences.requests.form.createTitle')}</SheetTitle>
          <SheetDescription>{t('absences.requests.form.description')}</SheetDescription>
        </SheetHeader>
        {/* Keyed by request so each opening starts from fresh form state */}
        <AbsenceRequestForm
          key={request?.id ?? 'new'}
          request={request}
          refreshGrid={refreshGrid}
          onSaved={onSaved}
          onClose={() => onOpenChange(false)}
        />
      </SheetContent>
    </Sheet>
  )
}
