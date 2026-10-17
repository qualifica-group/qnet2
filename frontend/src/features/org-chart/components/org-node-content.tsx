import { Crown, Network, Users } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import { UserAvatar } from '@/components/user-avatar'
import { RecordLink } from '@/components/detail/record-link'
import { Badge } from '@/components/ui/badge'
import type { FunctionNode, OrgTreeNode, PersonNode } from '@/features/org-chart/tree-nodes'

const BADGE_CLASS = 'px-1.5 py-0 text-[11px]'

function PeopleCount({ count }: { count: number }) {
  const { t } = useTranslation()
  return (
    <span
      className="inline-flex items-center gap-1 text-xs text-muted-foreground"
      title={t('orgChart.peopleCount', { count })}
    >
      <Users aria-hidden="true" className="size-3.5" />
      <span aria-label={t('orgChart.peopleCount', { count })}>{count}</span>
    </span>
  )
}

function FunctionContent({ node }: { node: FunctionNode }) {
  const { t } = useTranslation()
  const { businessFunction } = node
  return (
    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
      <Network aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
      <span className="min-w-0 text-sm font-medium">
        <RecordLink domain="business-functions" id={node.id}>
          {businessFunction.name}
        </RecordLink>
      </span>
      {businessFunction.type ? (
        <Badge variant="outline" className={BADGE_CLASS}>
          {t(`orgChart.type.${businessFunction.type}`)}
        </Badge>
      ) : null}
      {businessFunction.manager ? (
        <span className="text-xs text-muted-foreground">
          {t('orgChart.responsible')}: {businessFunction.manager.name}
        </span>
      ) : null}
      <PeopleCount count={businessFunction.people.length} />
    </div>
  )
}

function PersonContent({ node }: { node: PersonNode }) {
  const { t } = useTranslation()
  const { person } = node
  return (
    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
      <UserAvatar name={person.name} src={person.avatar_url} size="sm" className="shrink-0" />
      <UserProfileHoverCard user={person} triggerClassName="min-w-0 text-sm font-medium hover:underline">
        <span className="truncate">{person.name}</span>
      </UserProfileHoverCard>
      {node.isResponsible ? (
        <Crown aria-label={t('orgChart.responsible')} role="img" className="size-3.5 shrink-0 text-amber-500" />
      ) : null}
      {person.job_roles.map((jobRole) => (
        <Badge key={jobRole} variant="secondary" className={BADGE_CLASS}>
          {jobRole}
        </Badge>
      ))}
      {node.functionNames.length > 0 ? (
        <span className="min-w-0 truncate text-xs text-muted-foreground">{node.functionNames.join(' · ')}</span>
      ) : null}
    </div>
  )
}

/** Visible content of one tree row, by node kind. */
export function OrgNodeContent({ node }: { node: OrgTreeNode }) {
  const { t } = useTranslation()
  if (node.kind === 'function') return <FunctionContent node={node} />
  if (node.kind === 'person') return <PersonContent node={node} />
  return (
    <div className="flex items-center gap-2">
      <Users aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
      <span className="text-sm font-medium">{t('orgChart.allFunctions')}</span>
      <PeopleCount count={node.peopleCount} />
    </div>
  )
}
