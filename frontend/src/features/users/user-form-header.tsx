import { useTranslation } from 'react-i18next'
import { Loader2, TriangleAlert, UserCog } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { UserDetailStats } from '@/features/users/user-detail-header'
import type { UserRecordValues } from '@/features/users/user-record'

/** The name the server will derive from the card (ADR 0012): the company name, or first and last name. */
function draftName(draft: PersonalDataDraft): string {
  const name = draft.type === 'company' ? draft.company_name : [draft.first_name, draft.last_name].filter(Boolean).join(' ')
  return name?.trim() ?? ''
}

interface UserFormHeaderProps {
  /** The draft as the rows read it. */
  values: UserRecordValues
  /** The buffered card: it names the user being created. */
  profileDraft: PersonalDataDraft
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the user create form, the detail's own
 * (`UserDetailHeader` + `UserDetailStats`, spec 0222): monogram and name
 * follow the card being typed, the pills and the strip follow the draft —
 * the assignment verdict included — and the actions sit where the detail has
 * none. A refused save is reported right under the actions.
 */
export function UserFormHeader({ values, profileDraft, formId, isSubmitting, submitError, onCancel }: UserFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const heading = draftName(profileDraft) || t('users.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<UserCog />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={values.email || t('users.form.createSubtitle')}
        badges={
          <>
            {fieldPermission('is_active').visible ? (
              <Badge variant={values.is_active ? 'secondary' : 'outline'}>
                {t(values.is_active ? 'users.form.is_active' : 'users.form.header.inactive')}
              </Badge>
            ) : null}
          </>
        }
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('users.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('users.form.saving') : t('users.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <UserDetailStats roles={values.roles.length} assignment={values.assignment} />
    </>
  )
}
