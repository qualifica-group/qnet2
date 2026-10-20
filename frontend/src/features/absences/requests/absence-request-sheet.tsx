import { useTranslation } from 'react-i18next'
import { CalendarOff } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { AbsenceRequestFields } from '@/features/absences/requests/absence-request-fields'
import { useAbsenceRequestForm } from '@/features/absences/requests/use-absence-request-form'
import { useAbsenceTypeOptions } from '@/features/absences/requests/use-absence-type-options'
import type { AbsenceRequest } from '@/features/absences/types'

const ATTACHMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png'
const ATTACHMENT_INPUT_ID = 'absence-request-attachment'
const FORM_ID = 'absence-request-form'

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

  const title = t(request ? 'absences.requests.form.editTitle' : 'absences.requests.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={(event) => void send(event)}
        title={title}
        subtitle={t('absences.requests.form.description')}
        media={<DetailMonogram name={title} icon={<CalendarOff />} />}
        isSubmitting={isSubmitting}
        serverError={null}
        onCancel={onClose}
        labels={{
          save: t('absences.requests.form.send'),
          saving: t('absences.requests.form.send'),
          cancel: t('absences.requests.form.cancel'),
        }}
        extraActions={
          <Button type="button" variant="secondary" disabled={isSubmitting} onClick={() => void saveDraft()}>
            {t('absences.requests.form.saveDraft')}
          </Button>
        }
      >
        <AbsenceRequestFields
          types={types.data ?? []}
          typesError={types.isError}
          typesEmpty={types.isSuccess && types.data.length === 0}
          attachment={
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
          }
        />
      </RecordCreateLayout>
    </Form>
  )
}

interface AbsenceRequestSheetProps extends Omit<AbsenceRequestFormProps, 'onClose'> {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Right-hand Sheet creating a request or editing a draft (spec 0278 AC-018), on the record create kit (spec 0287). */
export function AbsenceRequestSheet({ open, onOpenChange, request, refreshGrid, onSaved }: AbsenceRequestSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:absence-requests-form">
        <SheetToolbar closeLabel={t('absences.requests.form.cancel')} />
        <SheetHeader className="sr-only">
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
