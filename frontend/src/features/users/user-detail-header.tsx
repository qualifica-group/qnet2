import { useTranslation } from 'react-i18next'
import { Boxes, MapPin, Pencil, Shield, Target, UserCog } from 'lucide-react'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import type { AssignmentSummary } from '@/features/users/user-assignment'
import type { UserDetail } from '@/features/users/types'

/**
 * Identity band and KPI strip of the user record card. Kept in one file: both
 * pieces read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

interface UserDetailHeaderProps {
  user: UserDetail
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Identity band: avatar, name, email subtitle, account/role pills, edit action. */
export function UserDetailHeader({ user, onEdit }: UserDetailHeaderProps) {
  const { t } = useTranslation()
  const isManager = user.employment?.is_manager ?? false

  return (
    <RecordCardHeader
      media={<UserAvatar name={user.name} src={user.avatar_url} size="lg" />}
      title={user.name}
      subtitle={user.email}
      badges={
        <>
          <Badge variant={user.is_active ? 'secondary' : 'outline'}>
            {t(user.is_active ? 'users.form.is_active' : 'users.form.header.inactive')}
          </Badge>
          {isManager ? (
            <Badge variant="outline" className="gap-1.5">
              <UserCog aria-hidden="true" />
              {t('users.detail.employment.isManager')}
            </Badge>
          ) : null}
          {user.roles.map((role) => (
            <Badge key={role.id} variant="outline" className="gap-1.5">
              <Shield aria-hidden="true" />
              {role.name}
            </Badge>
          ))}
        </>
      }
      actions={
        onEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

interface UserDetailStatsProps {
  user: UserDetail
  assignment: AssignmentSummary
}

/**
 * KPI strip: the three numbers that decide whether records can reach this
 * person (competence rows, Sedi, roles) and the verdict they add up to. The
 * verdict's `hint` names the FIRST blocker rather than all of them — the full
 * reasoning belongs to the band inside the assignment section, this row only
 * has to say why at a glance.
 */
export function UserDetailStats({ user, assignment }: UserDetailStatsProps) {
  const { t } = useTranslation()
  const [firstBlocker] = assignment.blockers

  return (
    <RecordStatStrip>
      <RecordStat
        icon={<Boxes />}
        label={t('users.assignment.stats.competence')}
        value={assignment.competenceCount}
      />
      <RecordStat
        icon={<MapPin />}
        label={t('users.assignment.stats.sites')}
        value={assignment.siteCount}
        hint={t('users.assignment.stats.sitesBreakdown', {
          physical: assignment.physicalSiteCount,
          remote: assignment.remoteSiteCount,
        })}
      />
      <RecordStat icon={<Shield />} label={t('users.form.roles')} value={user.roles.length} />
      <RecordStat
        icon={<Target />}
        label={t('users.assignment.stats.matching')}
        value={t(assignment.assignable ? 'users.assignment.assignable' : 'users.assignment.notAssignable')}
        hint={firstBlocker ? t(`users.assignment.stats.blockers.${firstBlocker}`) : undefined}
      />
    </RecordStatStrip>
  )
}
