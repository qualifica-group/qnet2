/**
 * "Scegli la prospettiva" footer of the "Periodo" card (spec 0122 D-10,
 * AC-037): Vista personale / Il mio team (or Struttura completa, `viewAll`).
 * Mirrors q-net's footer 1:1 (structure/density, D-2): a picked member shows
 * their avatar/name/subtitle with a "back to list" affordance instead of the
 * generic team icon. Purely presentational — the team tree itself (member
 * search/selection) is owned by a sibling microtask; this component only
 * reflects `mode`/`selectedMember` and fires the three callbacks.
 */

import { useTranslation } from 'react-i18next'
import { ArrowLeft, Users } from 'lucide-react'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'

export interface TimeEntriesViewSwitchCurrentUser {
  name: string
  avatarUrl?: string | null
}

export interface TimeEntriesViewSwitchMember {
  name: string
  avatarUrl?: string | null
  subtitle?: string | null
}

interface TimeEntriesViewSwitchProps {
  mode: 'personal' | 'team'
  teamCount: number
  isFullList: boolean
  selectedMember: TimeEntriesViewSwitchMember | null
  currentUser: TimeEntriesViewSwitchCurrentUser
  onSelectPersonal: () => void
  onSelectTeam: () => void
  onBackToList: () => void
}

export function TimeEntriesViewSwitch({
  mode,
  teamCount,
  isFullList,
  selectedMember,
  currentUser,
  onSelectPersonal,
  onSelectTeam,
  onBackToList,
}: TimeEntriesViewSwitchProps) {
  const { t } = useTranslation()
  const teamLabel = `${isFullList ? t('timeEntries.view.fullStructure') : t('timeEntries.view.team')} (${teamCount})`

  return (
    <div className="space-y-2">
      <p className="text-xs text-muted-foreground">{t('timeEntries.view.chooseHint')}</p>
      <div className="grid gap-2 sm:grid-cols-2">
        <button
          type="button"
          onClick={onSelectPersonal}
          className={cn(
            'flex items-center gap-2.5 rounded-md border bg-card px-3 py-2 text-left transition-all',
            mode === 'personal'
              ? 'border-primary/60 bg-primary/5 ring-2 ring-primary/30'
              : 'border-border hover:border-border hover:bg-muted/30',
          )}
        >
          <UserAvatar name={currentUser.name} src={currentUser.avatarUrl} size="sm" className="shrink-0" />
          <div className="min-w-0 flex-1">
            <p className={cn('text-sm leading-tight font-semibold', mode === 'personal' ? 'text-primary' : 'text-foreground')}>
              {t('timeEntries.view.personal')}
            </p>
            <p className="text-xs leading-tight text-muted-foreground">{t('timeEntries.view.personalHint')}</p>
          </div>
        </button>

        <button
          type="button"
          onClick={onSelectTeam}
          className={cn(
            'flex items-center gap-2.5 rounded-md border bg-card px-3 py-2 text-left transition-all',
            mode === 'team'
              ? 'border-primary/60 bg-primary/5 ring-2 ring-primary/30'
              : 'border-border hover:border-border hover:bg-muted/30',
          )}
        >
          {mode === 'team' && selectedMember ? (
            <UserAvatar name={selectedMember.name} src={selectedMember.avatarUrl} className="shrink-0" />
          ) : (
            <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
              <Users className="size-4" aria-hidden="true" />
            </span>
          )}

          {mode === 'team' && selectedMember ? (
            <>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm leading-tight font-semibold text-foreground">{selectedMember.name}</p>
                <p className="truncate text-xs leading-tight text-muted-foreground">
                  {selectedMember.subtitle?.trim() || teamLabel}
                </p>
              </div>
              <span
                role="button"
                tabIndex={0}
                aria-label={t('timeEntries.view.backToTeamList')}
                title={t('timeEntries.view.backToTeamList')}
                className="shrink-0 rounded-sm p-1 text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
                onClick={(event) => {
                  event.stopPropagation()
                  onBackToList()
                }}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    event.stopPropagation()
                    onBackToList()
                  }
                }}
              >
                <ArrowLeft className="size-3.5" aria-hidden="true" />
              </span>
            </>
          ) : (
            <div className="min-w-0 flex-1">
              <p className={cn('text-sm leading-tight font-semibold', mode === 'team' ? 'text-primary' : 'text-foreground')}>
                {teamLabel}
              </p>
              <p className="text-xs leading-tight text-muted-foreground">{t('timeEntries.view.teamHint')}</p>
            </div>
          )}
        </button>
      </div>
    </div>
  )
}
