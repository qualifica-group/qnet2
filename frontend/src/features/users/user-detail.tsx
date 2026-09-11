import { useTranslation } from 'react-i18next'
import { History } from 'lucide-react'
import {
  RecordCanvas,
  RecordCard,
  RecordMeta,
  RecordSection,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { cn } from '@/lib/utils'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUser } from '@/features/users/api'
import { summarizeAssignment } from '@/features/users/user-assignment'
import { UserDetailHeader, UserDetailStats } from '@/features/users/user-detail-header'
import { UserDetailSections } from '@/features/users/user-detail-sections'
import type { EmploymentDetail } from '@/features/users/types'
import { formatDateTime } from '@/lib/formatting/date-display'

/** Stable empty defaults: a user with no employment profile answers like an empty one. */
const EMPTY_COMPETENCE_ROWS: { business_function_id: number; product_category_id: number }[] = []
const EMPTY_REMOTE_SITE_IDS: number[] = []

interface UserDetailProps {
  userId: number
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single user, rendered as an enterprise-CRM record —
 * the same kit the Opportunità record uses (user directive 2026-09-11): the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. Container-query driven (`RecordCanvas`) so the same tree
 * renders correctly both inside a resizable Sheet and on the full-bleed
 * `/users/:id` page.
 *
 * It owns its own fetch (unlike the opportunity screen, whose view takes
 * already-loaded data): every caller across the app opens this card by id.
 */
export function UserDetailView({ userId, onEdit }: UserDetailProps) {
  const { t } = useTranslation()
  const {
    data: user,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['users', 'detail', userId], () => fetchUser(userId))

  if (isError) {
    return (
      <DetailError
        message={t('users.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !user) {
    return <DetailLoading />
  }

  const createdAt = formatDateTime(user.created_at)
  const canViewActivity = user.permissions.actions.view_activity
  const assignment = summarizeAssignment(assignmentInput(user.employment))

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <UserDetailHeader user={user} onEdit={onEdit} />
            <UserDetailStats user={user} assignment={assignment} />
            <UserDetailSections user={user} assignment={assignment} />
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="users" id={userId} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

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

/**
 * Projects the persisted profile onto the shape `summarizeAssignment` reads.
 * The persisted competence rows are always complete pairs (the server rejects
 * a half-filled one), so their ids are non-null by construction here — unlike
 * the form, where a row exists while it is being typed.
 */
function assignmentInput(employment: EmploymentDetail | null | undefined) {
  return {
    competenceRows:
      employment?.product_lines?.map((line) => ({
        business_function_id: line.business_function.id,
        product_category_id: line.product_category.id,
      })) ?? EMPTY_COMPETENCE_ROWS,
    primarySiteId: employment?.primary_operational_site_id ?? null,
    remoteSiteIds: employment?.remote_operational_site_ids ?? EMPTY_REMOTE_SITE_IDS,
  }
}
