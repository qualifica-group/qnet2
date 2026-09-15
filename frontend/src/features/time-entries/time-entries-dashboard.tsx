/**
 * `/time-entries` dashboard assembly (spec 0122 D-1/D-10/D-13, MT-F7): the
 * standard `PageHeader` (breadcrumb + "Nuovo segnatempo" action, aligned with
 * the other modules), error alert, the "Periodo" card (with the view-switch
 * footer), then either the team tree or [KPI+Polso, day list]. Order and
 * composition mirror q-net's `work-activities-list.tsx` (D-2); state/query
 * wiring lives in `useTimeEntriesDashboardView` (engineering.md §2).
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertCircle, Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { TimeEntryCreateSheet } from '@/features/time-entries/form/time-entry-create-sheet'
import { TimeEntryEditSheet } from '@/features/time-entries/form/time-entry-edit-sheet'
import { TimeEntriesPeriodCard } from '@/features/time-entries/dashboard/time-entries-period-card'
import { TimeEntriesStatsPanels } from '@/features/time-entries/dashboard/time-entries-stats-panels'
import { TimeEntriesViewSwitch } from '@/features/time-entries/dashboard/time-entries-view-switch'
import { TimeEntriesDayList } from '@/features/time-entries/days/time-entries-day-list'
import { TimeEntriesTeamPulse } from '@/features/time-entries/team/time-entries-team-pulse'
import { useTimeEntriesDashboardView } from '@/features/time-entries/page/use-time-entries-dashboard-view'
import type { TeamPulseMember } from '@/features/time-entries/types'

interface CreateSheetState {
  open: boolean
  defaultDate?: string
}

const CREATE_SHEET_CLOSED: CreateSheetState = { open: false }

/** "Ruolo · Mansione" caption under a drilled-into member's name, q-net parity. */
function buildMemberSubtitle(member: TeamPulseMember): string {
  const roleLabel = member.roles.join(', ')
  return [roleLabel, member.job_description].filter((part) => part && part.trim().length > 0).join(' · ')
}

export function TimeEntriesDashboard() {
  const { t } = useTranslation()
  const view = useTimeEntriesDashboardView()
  const [createSheet, setCreateSheet] = useState<CreateSheetState>(CREATE_SHEET_CLOSED)
  const [editEntryId, setEditEntryId] = useState<number | null>(null)

  const meta = view.days.meta
  // The create sheet only needs an explicit `userId` when it differs from the
  // actor: omitting it lets the server default to self (CreateTimeEntryPayload).
  const createUserId =
    view.currentUser && view.effectiveUserId !== view.currentUser.id ? view.effectiveUserId : undefined

  const openCreateSheet = (defaultDate?: string) => setCreateSheet({ open: true, defaultDate })

  return (
    <div className="space-y-4">
      <PageHeader
        actions={
          meta?.can_write ? (
            <Can permission="time-entries.create">
              <Button type="button" onClick={() => openCreateSheet()}>
                <Plus aria-hidden="true" />
                {t('timeEntries.page.newTimeEntry')}
              </Button>
            </Can>
          ) : null
        }
      />

      {view.days.isError ? (
        <div
          role="alert"
          className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-l-2 border-destructive/40 border-l-destructive bg-destructive/10 px-4 py-3 text-sm"
        >
          <span className="flex items-center gap-2 font-medium text-foreground">
            <AlertCircle className="size-4 shrink-0 text-destructive" aria-hidden="true" />
            {t('timeEntries.page.loadError')}
          </span>
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={view.retry}>
            {t('timeEntries.page.retry')}
          </Button>
        </div>
      ) : null}

      <TimeEntriesPeriodCard
        filters={view.filters}
        meta={meta}
        isLoading={view.days.isLoading}
        footerSlot={
          meta?.team.can_view ? (
            <TimeEntriesViewSwitch
              mode={view.viewMode}
              teamCount={meta.team.members_count}
              isFullList={meta.team.is_full_list}
              selectedMember={
                view.viewMode === 'team' && view.selectedMember
                  ? {
                      name: view.selectedMember.user.name,
                      avatarUrl: view.selectedMember.user.avatar_url,
                      subtitle: buildMemberSubtitle(view.selectedMember),
                    }
                  : null
              }
              currentUser={{ name: view.currentUser?.name ?? '', avatarUrl: view.currentUser?.avatarUrl }}
              onSelectPersonal={view.selectPersonalView}
              onSelectTeam={view.selectTeamView}
              onBackToList={view.backToTeamList}
            />
          ) : null
        }
      />

      {view.showTeamPulse ? (
        <div key="team" className="animate-in fade-in-0 slide-in-from-left-2 space-y-3 duration-300">
          <TimeEntriesTeamPulse
            periodParams={view.teamPeriodParams}
            currentUserId={view.currentUser?.id ?? 0}
            enabled={Boolean(meta?.team.can_view)}
            onSelectMember={view.selectMember}
          />
        </div>
      ) : null}

      {view.showEntries ? (
        <div
          key={`entries-${view.viewMode}-${view.selectedMember?.user.id ?? 'self'}`}
          className="animate-in fade-in-0 slide-in-from-right-2 space-y-4 duration-300"
        >
          <TimeEntriesStatsPanels params={view.queryFilterParams} enabled={view.showEntries} />

          <TimeEntriesDayList
            days={view.days.days}
            isLoading={view.days.isLoading}
            isError={view.days.isError}
            hasNextPage={view.days.hasNextPage}
            isFetchingNextPage={view.days.isFetchingNextPage}
            onLoadMore={view.days.fetchNextPage}
            sortBy={view.sortBy}
            sortDirection={view.filters.filters.sortDirection}
            onSortChange={view.filters.setSort}
            canWrite={meta?.can_write ?? false}
            selectedUserId={view.effectiveUserId}
            onEditEntry={setEditEntryId}
            onCreateForDate={openCreateSheet}
          />
        </div>
      ) : null}

      <TimeEntryCreateSheet
        open={createSheet.open}
        onOpenChange={(open) => setCreateSheet((current) => ({ ...current, open }))}
        defaultDate={createSheet.defaultDate}
        userId={createUserId}
      />

      {editEntryId !== null ? (
        <TimeEntryEditSheet
          open={editEntryId !== null}
          onOpenChange={(open) => {
            if (!open) setEditEntryId(null)
          }}
          timeEntryId={editEntryId}
        />
      ) : null}
    </div>
  )
}
