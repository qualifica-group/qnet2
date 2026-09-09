import { useTranslation } from 'react-i18next'
import {
  Boxes,
  Briefcase,
  Building2,
  CalendarClock,
  ChevronRight,
  Coffee,
  Globe,
  History,
  MapPin,
  Shield,
  Timer,
  UserCog,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { UserAvatar } from '@/components/user-avatar'
import {
  DetailEmpty,
  DetailError,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailLoading,
  DetailMeta,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { useEnumOptions } from '@/features/config/use-config'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUser } from '@/features/users/api'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

interface UserDetailProps {
  userId: number
}

/**
 * Read-only detail of a single user, fetched fresh from the (re-authorized)
 * detail endpoint. Composed from the shared detail kit; rendered inside a Sheet.
 */
export function UserDetailView({ userId }: UserDetailProps) {
  const { t } = useTranslation()
  const localeOptions = useEnumOptions('locale')
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
  const localeLabel =
    localeOptions.find((option) => option.value === user.locale)?.label ?? user.locale
  const employment = user.employment

  return (
    <DetailPanel>
      <DetailHero
        media={<UserAvatar name={user.name} src={user.avatar_url} size="xl" />}
        title={user.name}
        subtitle={user.email}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('users.form.locale')} icon={<Globe />}>
            {localeLabel}
          </DetailField>
          <DetailField label={t('users.form.roles')} icon={<Shield />} full>
            {user.roles.length > 0 ? (
              <div className="flex flex-wrap gap-1">
                {user.roles.map((role) => (
                  <Badge key={role.id} variant="secondary">
                    {role.name}
                  </Badge>
                ))}
              </div>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {employment ? (
        <DetailSection title={t('users.detail.employment.title')} icon={<Briefcase />}>
          <DetailGrid>
            <DetailField label={t('users.detail.employment.isManager')} icon={<UserCog />}>
              {employment.is_manager ? t('common.yes') : t('common.no')}
            </DetailField>
            <DetailField label={t('users.detail.employment.productLines')} icon={<Boxes />} full>
              {employment.product_lines && employment.product_lines.length > 0 ? (
                <ul className="flex flex-col gap-1">
                  {employment.product_lines.map((line) => (
                    <li key={line.id} className="flex min-w-0 items-center gap-1.5 text-xs">
                      <Badge variant="secondary" className="max-w-full truncate">
                        {line.business_function.name}
                      </Badge>
                      <ChevronRight aria-hidden="true" className="size-3 shrink-0 text-muted-foreground" />
                      <span className="truncate">{line.product_category.name}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <DetailEmpty />
              )}
            </DetailField>
            <DetailField label={t('users.detail.employment.reportsTo')} icon={<UserCog />}>
              {employment.reports_to?.label ?? <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.company')} icon={<Building2 />}>
              {employment.company?.label ?? <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.primaryOperationalSite')} icon={<MapPin />}>
              {employment.primary_operational_site?.label ?? <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.remoteOperationalSites')} icon={<MapPin />} full>
              {employment.remote_operational_sites && employment.remote_operational_sites.length > 0 ? (
                <div className="flex flex-wrap gap-1">
                  {employment.remote_operational_sites.map((site) => (
                    <Badge key={site.id} variant="secondary" className="max-w-full truncate">
                      {site.label}
                    </Badge>
                  ))}
                </div>
              ) : (
                <DetailEmpty />
              )}
            </DetailField>
            <DetailField label={t('users.detail.employment.relationshipType')}>
              {employment.relationship_type ? (
                t(`enums.relationship_type.${employment.relationship_type}`)
              ) : (
                <DetailEmpty />
              )}
            </DetailField>
            <DetailField label={t('users.detail.employment.qualificationType')}>
              {employment.qualification_type ? (
                t(`enums.qualification_type.${employment.qualification_type}`)
              ) : (
                <DetailEmpty />
              )}
            </DetailField>
            <DetailField label={t('users.detail.employment.hiredAt')} icon={<CalendarClock />}>
              {formatDate(employment.hired_at) || <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.terminatedAt')} icon={<CalendarClock />}>
              {formatDate(employment.terminated_at) || <DetailEmpty />}
            </DetailField>
            <DetailField
              label={t('users.detail.employment.standardDailyMinutes')}
              icon={<Timer />}
            >
              {formatMinutes(employment.standard_daily_minutes) ?? <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.breakDailyMinutes')} icon={<Coffee />}>
              {formatMinutes(employment.break_daily_minutes) ?? <DetailEmpty />}
            </DetailField>
            <DetailField label={t('users.detail.employment.jobDescription')} full>
              {employment.job_description || <DetailEmpty />}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      ) : null}

      {user.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="users" id={userId} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('users.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}

/** Formats a total-minutes duration as `H:MM`, or `null` when unset (spec 0015). */
function formatMinutes(value: number | null): string | null {
  if (value === null) {
    return null
  }
  const hours = Math.floor(value / 60)
  const minutes = value % 60
  return `${hours}:${String(minutes).padStart(2, '0')}`
}
