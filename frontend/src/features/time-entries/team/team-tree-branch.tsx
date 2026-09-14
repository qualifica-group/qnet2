/**
 * Recursive branch of the team tree (spec 0122 D-10): renders one level of
 * `TeamTreeNode`s and, for each, its children inside an animated collapsible
 * region. q-net parity (`team-pulse/team-tree-branch.tsx`).
 */

import { cn } from '@/lib/utils'
import { TeamMemberRow } from '@/features/time-entries/team/team-member-row'
import type { TeamTreeNode } from '@/features/time-entries/team/team-tree'
import type { TeamPulseMember } from '@/features/time-entries/types'

interface TeamTreeBranchProps {
  nodes: TeamTreeNode[]
  depth: number
  parentTrails: boolean[]
  currentUserId: number
  onSelectMember: (member: TeamPulseMember) => void
  collapsedKeys: ReadonlySet<string>
  onToggleNode: (key: string) => void
}

export function TeamTreeBranch({
  nodes,
  depth,
  parentTrails,
  currentUserId,
  onSelectMember,
  collapsedKeys,
  onToggleNode,
}: TeamTreeBranchProps) {
  return (
    <>
      {nodes.map((node, index) => {
        const isLastChild = index === nodes.length - 1
        const isCurrentUser = node.member.user.id === currentUserId
        const isClickable = !isCurrentUser
        const nextTrails = depth === 0 ? [] : [...parentTrails, !isLastChild]
        const hasChildren = node.children.length > 0
        const isExpanded = !collapsedKeys.has(node.key)

        return (
          <li key={node.key} className="relative">
            <TeamMemberRow
              node={node}
              depth={depth}
              isClickable={isClickable}
              isCurrentUser={isCurrentUser}
              onSelect={() => onSelectMember(node.member)}
              isLastChild={isLastChild}
              parentTrails={parentTrails}
              hasChildren={hasChildren}
              isExpanded={isExpanded}
              onToggle={() => onToggleNode(node.key)}
            />
            {hasChildren ? (
              <div
                className={cn(
                  'grid transition-[grid-template-rows] duration-300 ease-in-out',
                  isExpanded ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                )}
              >
                <ul className="relative min-h-0 overflow-hidden">
                  <TeamTreeBranch
                    nodes={node.children}
                    depth={depth + 1}
                    parentTrails={nextTrails}
                    currentUserId={currentUserId}
                    onSelectMember={onSelectMember}
                    collapsedKeys={collapsedKeys}
                    onToggleNode={onToggleNode}
                  />
                </ul>
              </div>
            ) : null}
          </li>
        )
      })}
    </>
  )
}
