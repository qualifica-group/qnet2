import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import { memberPeople, type OrgTreeNode, type PersonNode } from '@/features/org-chart/tree-nodes'

interface OrgMembersSheetProps {
  /** Function or "all functions" card whose people are listed; `null` = closed. */
  node: OrgTreeNode | null
  onClose: () => void
  renderRowActions?: (node: OrgTreeNode) => ReactNode
}

interface MemberSectionProps {
  title: string
  people: PersonNode[]
  renderRowActions?: (node: OrgTreeNode) => ReactNode
  /** Responsible shown without a member row (the function manager is not among its people). */
  extraName?: string
}

function MemberSection({ title, people, renderRowActions, extraName }: MemberSectionProps) {
  if (people.length === 0 && !extraName) return null
  return (
    <section className="flex flex-col gap-1">
      <h3 className="text-xs font-semibold text-muted-foreground">{title}</h3>
      <ul className="flex flex-col">
        {extraName ? (
          <li className="flex items-center gap-2 rounded-md px-1.5 py-1.5">
            <UserAvatar name={extraName} />
            <span className="truncate text-sm font-medium">{extraName}</span>
          </li>
        ) : null}
        {people.map((member) => (
          <li key={member.key} className="flex items-center gap-2 rounded-md px-1.5 py-1.5 animate-in fade-in slide-in-from-bottom-1 duration-200 motion-reduce:animate-none hover:bg-muted/40">
            <UserAvatar name={member.person.name} src={member.person.avatar_url} />
            <div className="flex min-w-0 flex-1 flex-col">
              <UserProfileHoverCard user={member.person} triggerClassName="min-w-0 text-sm font-medium hover:underline">
                <span className="truncate">{member.person.name}</span>
              </UserProfileHoverCard>
              {member.person.job_roles.length > 0 ? (
                <span className="truncate text-xs text-muted-foreground">{member.person.job_roles.join(' · ')}</span>
              ) : null}
            </div>
            {renderRowActions ? <div className="shrink-0">{renderRowActions(member)}</div> : null}
          </li>
        ))}
      </ul>
    </section>
  )
}

/**
 * Side panel of a function card (spec 0274 D-2): its responsible people first,
 * then everyone else, each with the move menu (person between functions).
 */
export function OrgMembersSheet({ node, onClose, renderRowActions }: OrgMembersSheetProps) {
  const { t } = useTranslation()
  const members = node ? memberPeople(node) : []
  const responsible = members.filter((member) => member.isResponsible)
  const others = members.filter((member) => !member.isResponsible)
  const manager = node?.kind === 'function' ? node.businessFunction.manager : null
  const managerOutside = manager !== null && !members.some((member) => member.id === manager.id)
  const title = node?.kind === 'function' ? node.businessFunction.name : t('orgChart.unassigned')

  return (
    <Sheet open={node !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" storageKey="sheet-width:org-chart-members">
        <SheetHeader>
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>{t('orgChart.peopleCount', { count: members.length })}</SheetDescription>
        </SheetHeader>
        <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
          {members.length === 0 && !managerOutside ? (
            <p className="text-sm text-muted-foreground">{t('orgChart.members.empty')}</p>
          ) : null}
          <MemberSection
            title={t('orgChart.members.responsible')}
            people={responsible}
            renderRowActions={renderRowActions}
            extraName={managerOutside ? manager.name : undefined}
          />
          <MemberSection title={t('orgChart.members.people')} people={others} renderRowActions={renderRowActions} />
        </div>
      </SheetContent>
    </Sheet>
  )
}
