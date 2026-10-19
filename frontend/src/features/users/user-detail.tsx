import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PersonalDataRecordSections } from '@/features/personal-data/personal-data-record-cards'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUser, userDetailQueryKey } from '@/features/users/api'
import { UserActionsBar } from '@/features/users/user-actions-bar'
import { UserDetailHeader, UserDetailStats } from '@/features/users/user-detail-header'
import { UserDetailSections } from '@/features/users/user-detail-sections'
import { persistedUserValues } from '@/features/users/user-record'
import { useUserInlineEdit } from '@/features/users/use-user-inline-edit'
import type { UserLeaveReason } from '@/features/users/use-user-record-actions'
import type { UserDetailWithPermissions } from '@/features/users/types'
import { formatDateTime } from '@/lib/formatting/date-display'

interface UserDetailProps {
  userId: number
  /** Called after an in-place save, so the host refreshes whatever lists the user (spec 0195 D-7). */
  onChanged?: () => void
  /** Called once the record must be left (deleted, or the session switched to this user): the host closes it. */
  onLeave?: (reason: UserLeaveReason) => void
}

/**
 * Detail of a single user, rendered as an enterprise-CRM record — the same
 * `RecordBody` every detail uses: identity + KPI, the sections, then "how to
 * reach them" (contacts, addresses), the activity in the side column, a
 * metadata footer. It owns its own fetch: every caller across the app (the
 * module, the shared user sheet) opens this card by id.
 *
 * There is no edit page (spec 0222): the fields — the anagraphic card and the
 * custom fields included — edit IN PLACE, one at a time (`RecordInlineField`,
 * driven by `useUserInlineEdit`); contacts, addresses and the profile photo
 * are saved on their own.
 */
export function UserDetailView({ userId, onChanged, onLeave }: UserDetailProps) {
  const { t } = useTranslation()
  const {
    data: user,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(userDetailQueryKey(userId), () => fetchUser(userId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('users.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !user) {
    return <DetailLoading />
  }

  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole record, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={user.permissions}>
      <UserDetailRecord user={user} onChanged={onChanged} onLeave={onLeave} />
    </ResourcePermissionsProvider>
  )
}

function UserDetailRecord({ user, onChanged, onLeave }: Omit<UserDetailProps, 'userId'> & { user: UserDetailWithPermissions }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editor = useUserInlineEdit(user, onChanged)
  const createdAt = formatDateTime(user.created_at)
  const collaborationTabs = user.permissions.actions.view_activity
    ? [activityLogTab('users', user.id, t('activityLog.title'))]
    : []

  // A contact/address persisted on its own: refetch the record and tell the host.
  const handleCardChanged = () => {
    void queryClient.invalidateQueries({ queryKey: userDetailQueryKey(user.id) })
    onChanged?.()
  }

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <UserDetailHeader user={user} />
            <UserActionsBar user={user} onChanged={onChanged} onLeave={onLeave} />
            <UserDetailStats roles={user.roles.length} assignment={persistedUserValues(user).assignment} />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          <UserDetailSections user={user} editor={editor} onChanged={onChanged} />
        </Form>
        <RecordSectionsGrid className="border-t">
          <PersonalDataRecordSections
            card={user.personal_data ?? null}
            contactsTitle={t('users.form.sections.contacts.title')}
            addressesTitle={t('users.form.sections.addresses.title')}
            showWorkFlag
            editing={{ fieldPermission: editor.card.fieldPermission, onChanged: handleCardChanged }}
          />
        </RecordSectionsGrid>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('users.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
