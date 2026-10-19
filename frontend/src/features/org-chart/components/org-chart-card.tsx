import type { ReactNode } from 'react'
import { Crown, Network, Users } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { RecordLink } from '@/components/detail/record-link'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import { cn } from '@/lib/utils'
import { memberPeople, type FunctionNode, type OrgTreeNode, type PersonNode } from '@/features/org-chart/tree-nodes'

const CHIP_CLASS = 'truncate rounded-sm bg-muted px-1.5 py-px text-[10px] font-medium text-muted-foreground'
const SUBTEXT_CLASS = 'w-full truncate text-center text-xs text-muted-foreground'

export interface OrgChartCardProps {
  node: OrgTreeNode
  /** Id of the card title: labels the surrounding `treeitem`. */
  titleId: string
  highlighted: boolean
  /** Row actions slot ("Sposta in…" menu), rendered in the top-right corner. */
  actions: ReactNode
  onOpenMembers: (node: OrgTreeNode) => void
}

interface CardFrameProps {
  highlighted: boolean
  corner: ReactNode
  topRight: ReactNode
  children: ReactNode
}

function CardFrame({ highlighted, corner, topRight, children }: CardFrameProps) {
  return (
    <div
      data-chart-card=""
      className={cn(
        'flex h-full flex-col items-center gap-1 overflow-hidden rounded-lg border border-border bg-card px-2.5 pt-1.5 pb-2 shadow-sm',
        highlighted && 'border-primary ring-2 ring-primary/40',
      )}
    >
      <div className="flex h-6 w-full shrink-0 items-center justify-between gap-1">
        <div className="flex min-w-0 items-center">{corner}</div>
        <div className="flex shrink-0 items-center gap-0.5">{topRight}</div>
      </div>
      {children}
    </div>
  )
}

interface MembersButtonProps {
  node: OrgTreeNode
  label: string
  onOpenMembers: (node: OrgTreeNode) => void
}

function MembersButton({ node, label, onOpenMembers }: MembersButtonProps) {
  const { t } = useTranslation()
  const count = memberPeople(node).length
  return (
    <Button
      type="button"
      variant="ghost"
      size="xs"
      onClick={() => onOpenMembers(node)}
      aria-label={t('orgChart.members.open', { count, name: label })}
      className="text-muted-foreground"
    >
      <Users aria-hidden="true" />
      {count}
    </Button>
  )
}

function FunctionCard({ node, titleId, highlighted, actions, onOpenMembers }: OrgChartCardProps & { node: FunctionNode }) {
  const { t } = useTranslation()
  const { businessFunction } = node
  const { manager } = businessFunction
  const managerAvatar = manager ? (businessFunction.people.find((person) => person.id === manager.id)?.avatar_url ?? null) : null

  return (
    <CardFrame
      highlighted={highlighted}
      corner={businessFunction.type ? <span className={CHIP_CLASS}>{t(`orgChart.type.${businessFunction.type}`)}</span> : null}
      topRight={
        <>
          <MembersButton node={node} label={businessFunction.name} onOpenMembers={onOpenMembers} />
          {actions}
        </>
      }
    >
      <span id={titleId} data-org-content="" className="line-clamp-1 w-full text-center text-sm font-medium">
        <RecordLink domain="business-functions" id={node.id}>
          {businessFunction.name}
        </RecordLink>
      </span>
      {manager ? (
        <UserAvatar name={manager.name} src={managerAvatar} />
      ) : (
        <span aria-hidden="true" className="flex size-8 items-center justify-center rounded-full border border-dashed border-border">
          <Users className="size-3.5 text-muted-foreground" />
        </span>
      )}
      <span className={SUBTEXT_CLASS}>
        {manager ? `${t('orgChart.responsible')}: ${manager.name}` : t('orgChart.noManager')}
      </span>
    </CardFrame>
  )
}

function PersonCard({ node, titleId, highlighted, actions }: OrgChartCardProps & { node: PersonNode }) {
  const { t } = useTranslation()
  const { person } = node
  return (
    <CardFrame
      highlighted={highlighted}
      corner={
        node.isResponsible ? (
          <Crown aria-label={t('orgChart.responsible')} role="img" className="size-3.5 text-amber-500" />
        ) : null
      }
      topRight={actions}
    >
      <UserAvatar name={person.name} src={person.avatar_url} size="lg" className="-mt-5" />
      <span id={titleId} data-org-content="" className="flex w-full min-w-0 justify-center text-sm font-medium">
        <UserProfileHoverCard user={person} triggerClassName="min-w-0 hover:underline">
          <span className="truncate">{person.name}</span>
        </UserProfileHoverCard>
      </span>
      <span className={SUBTEXT_CLASS}>{person.job_roles.join(' · ') || ' '}</span>
      <span className={cn(SUBTEXT_CLASS, 'flex items-center justify-center gap-1')}>
        {node.functions.map((businessFunction, index) => (
          <span key={businessFunction.id} className="inline-flex min-w-0 items-center gap-0.5">
            {index > 0 ? <span className="text-muted-foreground/60">·</span> : null}
            {businessFunction.is_manager ? (
              <Crown role="img" aria-label={t('orgChart.responsible')} className="size-3 shrink-0 text-amber-500" />
            ) : null}
            <span className="truncate">{businessFunction.name}</span>
          </span>
        ))}
      </span>
    </CardFrame>
  )
}

function UnassignedCard({ node, titleId, highlighted, onOpenMembers }: OrgChartCardProps) {
  const { t } = useTranslation()
  return (
    <CardFrame highlighted={highlighted} corner={null} topRight={<MembersButton node={node} label={t('orgChart.unassigned')} onOpenMembers={onOpenMembers} />}>
      <span aria-hidden="true" className="flex size-8 items-center justify-center rounded-full bg-muted">
        <Network className="size-4 text-muted-foreground" />
      </span>
      <span id={titleId} data-org-content="" className="text-sm font-medium">
        {t('orgChart.unassigned')}
      </span>
      <span className={cn(SUBTEXT_CLASS, 'line-clamp-2 whitespace-normal')}>{t('orgChart.unassignedHint')}</span>
    </CardFrame>
  )
}

/** Content of one chart card (spec 0274 D-2/D-3), by node kind. */
export function OrgChartCard(props: OrgChartCardProps) {
  const { node } = props
  if (node.kind === 'function') return <FunctionCard {...props} node={node} />
  if (node.kind === 'person') return <PersonCard {...props} node={node} />
  return <UnassignedCard {...props} />
}
