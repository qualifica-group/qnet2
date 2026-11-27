import { useCallback, useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, MoreHorizontal, Plus, UserCog, UserMinus, Users, X, type LucideIcon } from 'lucide-react'
import { RecordPerson } from '@/components/detail/record-person'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { TaskBoardOptionList } from '@/features/work-orders/task-board/task-board-option-list'
import type { ParticipantSlotRow, WorkOrderTeamEditor } from '@/features/work-orders/use-work-order-team-editor'

/** A row's own actions: shown on hover or focus with a mouse, always on touch screens. */
const ROW_ACTION_CLASS =
  'opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100 data-[state=open]:opacity-100 [@media(hover:none)]:opacity-100'

const ROW_CLASS = 'group flex min-h-10 min-w-0 items-center gap-2 px-2 py-1'

interface AddPersonButtonProps {
  label: string
  selectedIds: number[]
  onToggle: (id: number) => void
  disabled: boolean
}

/** The modal content holding the trigger, where the popup must live to keep its own scrolling. */
const MODAL_CONTENT_SELECTOR = '[data-slot="sheet-content"], [data-slot="dialog-content"]'

/**
 * "Aggiungi": the users list in a popover, ticked for whoever is already in;
 * a pick saves at once and keeps it open. Portaled into the popup's own
 * content node, as `AsyncPaginatedSelect` does: portaled to `body`, outside
 * the modal, the list would not scroll with the wheel.
 */
function AddPersonButton({ label, selectedIds, onToggle, disabled }: AddPersonButtonProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    const modalContent = node?.closest(MODAL_CONTENT_SELECTOR)
    setPortalContainer(modalContent instanceof HTMLElement ? modalContent : null)
  }, [])

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild ref={setTrigger}>
        <Button type="button" variant="outline" size="xs" disabled={disabled} aria-label={label}>
          <Plus aria-hidden="true" />
          {t('workOrders.actions.teamPanel.add')}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" container={portalContainer} className="w-auto p-1.5">
        <TaskBoardOptionList
          resource={USERS_FOR_SELECT_RESOURCE}
          label={label}
          selectedIds={selectedIds}
          onSelect={(item) => onToggle(item.id)}
          showAvatar
          isMultiple
        />
      </PopoverContent>
    </Popover>
  )
}

interface TeamGroupProps {
  icon: LucideIcon
  title: string
  count: number
  action: ReactNode
  emptyTitle: string
  emptyHint: string
  children: ReactNode
}

function TeamGroup({ icon: Icon, title, count, action, emptyTitle, emptyHint, children }: TeamGroupProps) {
  const headingId = useId()

  return (
    <section aria-labelledby={headingId} className="flex min-w-0 flex-col gap-1.5">
      <div className="flex items-center justify-between gap-2">
        <h3 id={headingId} className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
          <Icon className="size-3.5" aria-hidden="true" />
          {title}
          <Badge variant="secondary" className="h-4 min-w-4 px-1 text-[10px] tabular-nums">
            {count}
          </Badge>
        </h3>
        {action}
      </div>
      {count > 0 ? (
        <ul className="flex min-w-0 flex-col divide-y divide-border/60 rounded-lg border bg-card">{children}</ul>
      ) : (
        <div className="flex items-center gap-2.5 rounded-lg border border-dashed bg-card px-3 py-2.5">
          <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <Icon className="size-3.5" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <p className="text-sm font-medium text-foreground">{emptyTitle}</p>
            <p className="text-xs text-muted-foreground">{emptyHint}</p>
          </div>
        </div>
      )}
    </section>
  )
}

interface ParticipantRowProps {
  row: ParticipantSlotRow
  isLast: boolean
  editor: WorkOrderTeamEditor
}

/** One "Partecipante n" slot: its number, the person (or the free place), and move/remove in a "..." menu. */
function ParticipantRow({ row, isLast, editor }: ParticipantRowProps) {
  const { t } = useTranslation()
  const slotLabel = t('workOrders.form.participantSlotLabel', { n: row.position })
  const { participant } = row

  return (
    <li className={ROW_CLASS} aria-label={participant ? `${slotLabel}: ${participant.name}` : slotLabel}>
      <span
        className="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold tabular-nums text-muted-foreground"
        title={slotLabel}
        aria-hidden="true"
      >
        {row.position}
      </span>
      <div className="min-w-0 flex-1">
        {participant ? (
          <RecordPerson user={participant} />
        ) : (
          <span className="text-xs italic text-muted-foreground">{t('workOrders.actions.teamPanel.freeSlot')}</span>
        )}
      </div>
      {editor.canEditParticipants && participant ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className={ROW_ACTION_CLASS}
              disabled={editor.isSaving}
              aria-label={t('workOrders.actions.teamPanel.rowActions', { name: participant.name })}
            >
              <MoreHorizontal aria-hidden="true" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem disabled={row.position === 1} onSelect={() => editor.moveParticipant(row.position, -1)}>
              <ArrowUp aria-hidden="true" />
              {t('workOrders.actions.teamPanel.moveUp')}
            </DropdownMenuItem>
            <DropdownMenuItem disabled={isLast} onSelect={() => editor.moveParticipant(row.position, 1)}>
              <ArrowDown aria-hidden="true" />
              {t('workOrders.actions.teamPanel.moveDown')}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onSelect={() => editor.clearParticipant(row.position)}>
              <UserMinus aria-hidden="true" />
              {t('workOrders.actions.teamPanel.remove')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ) : null}
    </li>
  )
}

/**
 * The body of the "Responsabili e Partecipanti" popup (user request
 * 2026-11-27): two groups as a CRM team panel lays them out — heading with
 * the head count and "Aggiungi", then one row per person (avatar and name
 * with the profile hover card) whose actions appear on hover. Responsabili
 * are a set (a cross removes one); Partecipanti keep their numbered slots,
 * free places included, and move or leave from the row's menu. Each change is
 * saved at once by {@link WorkOrderTeamEditor}; without the field permission
 * the group is read-only, a hidden field is not shown.
 */
export function WorkOrderTeamPanel({ editor }: { editor: WorkOrderTeamEditor }) {
  const { t } = useTranslation()
  const lastPosition = editor.participantRows.length

  return (
    <div className="flex min-w-0 flex-col gap-4" aria-busy={editor.isSaving}>
      {editor.showSupervisors ? (
        <TeamGroup
          icon={UserCog}
          title={t('workOrders.detail.supervisors')}
          count={editor.supervisors.length}
          emptyTitle={t('workOrders.actions.teamPanel.noSupervisors')}
          emptyHint={t(editor.canEditSupervisors ? 'workOrders.actions.teamPanel.noSupervisorsHint' : 'workOrders.actions.teamPanel.readOnlyHint')}
          action={
            editor.canEditSupervisors ? (
              <AddPersonButton
                label={t('workOrders.actions.teamPanel.addSupervisor')}
                selectedIds={editor.supervisorIds}
                onToggle={editor.toggleSupervisor}
                disabled={editor.isSaving}
              />
            ) : null
          }
        >
          {editor.supervisors.map((supervisor) => (
            <li key={supervisor.id} className={ROW_CLASS}>
              <div className="min-w-0 flex-1">
                <RecordPerson user={supervisor} />
              </div>
              {editor.canEditSupervisors ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className={ROW_ACTION_CLASS}
                  disabled={editor.isSaving}
                  onClick={() => editor.toggleSupervisor(supervisor.id)}
                  aria-label={t('workOrders.actions.teamPanel.removeNamed', { name: supervisor.name })}
                  title={t('workOrders.actions.teamPanel.remove')}
                >
                  <X aria-hidden="true" />
                </Button>
              ) : null}
            </li>
          ))}
        </TeamGroup>
      ) : null}

      {editor.showParticipants ? (
        <TeamGroup
          icon={Users}
          title={t('workOrders.detail.participants')}
          count={editor.participantIds.length}
          emptyTitle={t('workOrders.actions.teamPanel.noParticipants')}
          emptyHint={t(editor.canEditParticipants ? 'workOrders.actions.teamPanel.noParticipantsHint' : 'workOrders.actions.teamPanel.readOnlyHint')}
          action={
            editor.canEditParticipants ? (
              <AddPersonButton
                label={t('workOrders.actions.teamPanel.addParticipant')}
                selectedIds={editor.participantIds}
                onToggle={editor.toggleParticipant}
                disabled={editor.isSaving || !editor.canAddParticipant}
              />
            ) : null
          }
        >
          {editor.participantRows.map((row) => (
            <ParticipantRow key={row.position} row={row} isLast={row.position === lastPosition} editor={editor} />
          ))}
        </TeamGroup>
      ) : null}
    </div>
  )
}
