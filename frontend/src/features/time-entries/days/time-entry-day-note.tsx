/**
 * Inline day note (spec 0122 D-13/AC-036), replicating q-net's
 * `WorkActivityDayNoteInline` flow with a plain `Textarea` (D-2: no rich
 * text). Read-only when `canWrite` is false — hidden entirely when also
 * empty. Editable: click-to-edit, save on blur only if the trimmed value
 * changed (`useTimeEntryDayNote`).
 */

import { useLayoutEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronUp, Pencil, StickyNote } from 'lucide-react'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { useTimeEntryDayNote } from '@/features/time-entries/days/use-time-entry-day-note'

/** Collapsed note height (px), mirroring q-net's `COLLAPSED_MAX_HEIGHT`. */
const COLLAPSED_MAX_HEIGHT = 60

interface TimeEntryDayNoteProps {
  date: string
  note: string | null
  canWrite: boolean
  /** The dashboard's selected user (D-8), forwarded to the save payload. */
  selectedUserId?: number
}

/** Expand/collapse toggle shared by the read-only and editable branches. */
function ExpandToggle({
  isExpanded,
  onToggle,
}: {
  isExpanded: boolean
  onToggle: (event: React.MouseEvent<HTMLButtonElement>) => void
}) {
  const { t } = useTranslation()
  return (
    <button
      className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
      onClick={onToggle}
      type="button"
    >
      {isExpanded ? <ChevronUp className="size-3.5" /> : <ChevronDown className="size-3.5" />}
      {isExpanded ? t('timeEntries.dayCard.collapse') : t('timeEntries.dayCard.expand')}
    </button>
  )
}

export function TimeEntryDayNote({ date, note, canWrite, selectedUserId }: TimeEntryDayNoteProps) {
  const { t } = useTranslation()
  const value = note ?? ''
  const { save, isSaving } = useTimeEntryDayNote({ date, note: value, selectedUserId })

  const [draft, setDraft] = useState(value)
  const [isEditing, setIsEditing] = useState(false)
  const [isExpanded, setIsExpanded] = useState(false)
  const [isOverflowing, setIsOverflowing] = useState(false)
  const contentRef = useRef<HTMLParagraphElement>(null)

  useLayoutEffect(() => {
    const node = contentRef.current
    setIsOverflowing(Boolean(node && node.scrollHeight > COLLAPSED_MAX_HEIGHT + 1))
  }, [value, isEditing])

  if (!canWrite && !value) {
    return null
  }

  const clampStyle = { maxHeight: isExpanded ? undefined : COLLAPSED_MAX_HEIGHT }

  if (!canWrite) {
    return (
      <div className="flex items-start gap-2">
        <StickyNote className="mt-1.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <div className="min-w-0 flex-1">
          <p
            className="overflow-hidden whitespace-pre-wrap text-xs leading-5 text-foreground transition-[max-height] duration-300"
            ref={contentRef}
            style={clampStyle}
          >
            {value}
          </p>
          {isOverflowing ? (
            <ExpandToggle isExpanded={isExpanded} onToggle={() => setIsExpanded((prev) => !prev)} />
          ) : null}
        </div>
      </div>
    )
  }

  return (
    <div className="flex items-start gap-2">
      <StickyNote className="mt-2 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      <div className="min-w-0 flex-1">
        {isEditing ? (
          <Textarea
            autoFocus
            className="min-h-16 text-xs"
            disabled={isSaving}
            onBlur={() => {
              setIsEditing(false)
              save(draft)
            }}
            onChange={(event) => setDraft(event.target.value)}
            placeholder={t('timeEntries.dayCard.notePlaceholder')}
            value={draft}
          />
        ) : (
          <button
            className={cn(
              'group flex min-h-8 w-full items-start gap-1.5 rounded-md px-2 py-1.5 text-left',
              isSaving ? 'cursor-not-allowed opacity-60' : 'hover:bg-muted/40',
            )}
            disabled={isSaving}
            onClick={() => {
              // Seeds the draft from the current value right as editing starts
              // (not via an effect, react-hooks.md): avoids syncing state that
              // only ever needs to track the prop at one specific moment.
              setDraft(value)
              setIsEditing(true)
            }}
            type="button"
          >
            {value ? (
              <p
                className="min-w-0 flex-1 overflow-hidden whitespace-pre-wrap text-xs leading-5 text-foreground"
                ref={contentRef}
                style={clampStyle}
              >
                {value}
              </p>
            ) : (
              <span className="min-w-0 flex-1 text-xs italic text-muted-foreground">
                {t('timeEntries.dayCard.notePlaceholder')}
              </span>
            )}
            <Pencil
              aria-hidden="true"
              className="mt-0.5 size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
            />
          </button>
        )}
        {!isEditing && value && isOverflowing ? (
          <ExpandToggle
            isExpanded={isExpanded}
            onToggle={(event) => {
              event.stopPropagation()
              setIsExpanded((prev) => !prev)
            }}
          />
        ) : null}
      </div>
    </div>
  )
}
