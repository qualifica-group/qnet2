/**
 * Team view card of the segnatempo dashboard (spec 0122 D-10/AC-037/AC-038),
 * q-net parity (`work-activities-team-pulse.tsx`): search + filters toolbar,
 * expand/collapse-all bar, scrollable manager/subordinate tree. Mounted by
 * MT-F7 (dashboard assembly) once `meta.team.can_view` (list query) is true.
 */

import { useCallback, useMemo, useState } from 'react'
import { ChevronsDown, ChevronsUp } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  buildTeamTree,
  collectExpandableKeys,
  collectBusinessFunctionOptions,
  collectOperationalSiteOptions,
  collectRoleOptions,
  filterMembersKeepingAncestorsAndDescendants,
  filterTeamMembers,
} from '@/features/time-entries/team/team-tree'
import { TeamPulseSkeleton } from '@/features/time-entries/team/team-pulse-skeleton'
import { TeamPulseToolbar } from '@/features/time-entries/team/team-pulse-toolbar'
import { TeamTreeBranch } from '@/features/time-entries/team/team-tree-branch'
import { useTeamFilters } from '@/features/time-entries/team/use-team-filters'
import { useTimeEntriesTeam } from '@/features/time-entries/team/use-time-entries-team'
import type { TeamPulseMember, TeamStatsParams } from '@/features/time-entries/types'

const EMPTY_COLLAPSED_KEYS: ReadonlySet<string> = new Set()
const EMPTY_MEMBERS: TeamPulseMember[] = []

interface TimeEntriesTeamPulseProps {
  periodParams: TeamStatsParams
  currentUserId: number
  enabled: boolean
  onSelectMember: (member: TeamPulseMember) => void
}

export function TimeEntriesTeamPulse({
  periodParams,
  currentUserId,
  enabled,
  onSelectMember,
}: TimeEntriesTeamPulseProps) {
  const { t } = useTranslation()
  const query = useTimeEntriesTeam(periodParams, enabled)
  const teamFilters = useTeamFilters()

  const members = query.data?.items ?? EMPTY_MEMBERS
  const isFullList = query.data?.is_full_list ?? false
  const title = isFullList ? t('timeEntries.view.fullStructure') : t('timeEntries.view.team')

  const roleOptions = useMemo(() => collectRoleOptions(members), [members])
  const businessFunctionOptions = useMemo(() => collectBusinessFunctionOptions(members), [members])
  const operationalSiteOptions = useMemo(() => collectOperationalSiteOptions(members), [members])

  const filteredMembers = useMemo(
    () => filterTeamMembers(members, teamFilters.filters),
    [members, teamFilters.filters],
  )
  const tree = useMemo(
    () =>
      buildTeamTree(filterMembersKeepingAncestorsAndDescendants(filteredMembers, teamFilters.debouncedSearch)),
    [filteredMembers, teamFilters.debouncedSearch],
  )

  // Collapsed nodes tracked by key; an empty set means fully expanded (default).
  const [collapsedKeys, setCollapsedKeys] = useState<Set<string>>(() => new Set())
  const expandableKeys = useMemo(() => collectExpandableKeys(tree), [tree])

  const toggleNode = useCallback((key: string) => {
    setCollapsedKeys((current) => {
      const next = new Set(current)
      if (next.has(key)) {
        next.delete(key)
      } else {
        next.add(key)
      }
      return next
    })
  }, [])

  const expandAll = useCallback(() => setCollapsedKeys(new Set()), [])
  const collapseAll = useCallback(() => setCollapsedKeys(new Set(expandableKeys)), [expandableKeys])

  // Force the tree open while searching so matching descendants stay visible.
  const effectiveCollapsedKeys = teamFilters.debouncedSearch ? EMPTY_COLLAPSED_KEYS : collapsedKeys
  const allCollapsed = expandableKeys.length > 0 && expandableKeys.every((key) => collapsedKeys.has(key))

  if (query.isLoading) {
    return <TeamPulseSkeleton />
  }

  if (query.isError) {
    return (
      <Card className="border-destructive/40 shadow-none">
        <CardContent className="p-4 text-sm text-destructive">{t('timeEntries.team.loadError')}</CardContent>
      </Card>
    )
  }

  const toolbar = (
    <TeamPulseToolbar
      title={title}
      membersCount={members.length}
      searchInput={teamFilters.searchInput}
      onSearchInputChange={teamFilters.setSearchInput}
      roleOptions={roleOptions}
      businessFunctionOptions={businessFunctionOptions}
      operationalSiteOptions={operationalSiteOptions}
      filters={teamFilters.filters}
      onRolesChange={teamFilters.setRoles}
      onBusinessFunctionsChange={teamFilters.setBusinessFunctions}
      onOperationalSitesChange={teamFilters.setOperationalSites}
      onActivityChange={teamFilters.setActivity}
      onCoverageRangeChange={teamFilters.setCoverageRange}
      onReset={teamFilters.resetFilters}
    />
  )

  if (tree.length === 0) {
    return (
      <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
        {toolbar}
        <div className="p-6 text-center text-sm text-muted-foreground">
          {teamFilters.debouncedSearch ? t('timeEntries.team.noMatch') : t('timeEntries.team.noMembers')}
        </div>
      </Card>
    )
  }

  return (
    <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
      {toolbar}
      {expandableKeys.length > 0 ? (
        <div className="flex items-center justify-end border-b border-border/60 px-4 py-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="gap-1.5 text-muted-foreground hover:text-foreground"
            onClick={allCollapsed ? expandAll : collapseAll}
            disabled={Boolean(teamFilters.debouncedSearch)}
          >
            {allCollapsed ? (
              <>
                <ChevronsDown className="size-4" aria-hidden="true" />
                {t('timeEntries.dayCard.expand')}
              </>
            ) : (
              <>
                <ChevronsUp className="size-4" aria-hidden="true" />
                {t('timeEntries.dayCard.collapse')}
              </>
            )}
          </Button>
        </div>
      ) : null}
      <div className="max-h-[480px] overflow-y-auto">
        <ul className="relative divide-y divide-border/40">
          <TeamTreeBranch
            nodes={tree}
            depth={0}
            parentTrails={[]}
            currentUserId={currentUserId}
            onSelectMember={onSelectMember}
            collapsedKeys={effectiveCollapsedKeys}
            onToggleNode={toggleNode}
          />
        </ul>
      </div>
    </Card>
  )
}
