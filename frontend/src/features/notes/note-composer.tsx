import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Loader2, Send, X } from 'lucide-react'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { NoteQuoteScopeSelect } from '@/features/notes/note-quote-scope-select'
import { Form, FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { MentionBadge } from '@/features/notes/mention-badge'
import { MentionTextarea } from '@/features/notes/mention-textarea'
import { extractMentionIds, parseMentionRefs, removeMention } from '@/features/notes/mention-tokens'
import { useCreateNote, useUpdateNote } from '@/features/notes/use-note-mutations'
import type { Note, NoteQuoteRef } from '@/features/notes/types'

/** Mirrors the server-side `body: string (1..5000)` rule (D-12/data_contract). */
const BODY_MAX_LENGTH = 5000

/** Below this many characters left the hint gives way to a countdown. */
const CHARACTER_COUNTER_THRESHOLD = 500

function buildNoteComposerSchema(t: TFunction) {
  return z.object({
    body: z
      .string()
      .trim()
      .min(1, t('notes.composer.bodyRequired', { defaultValue: 'Scrivi qualcosa prima di inviare.' }))
      .max(
        BODY_MAX_LENGTH,
        t('notes.composer.bodyTooLong', {
          defaultValue: `La nota puo' contenere al massimo ${BODY_MAX_LENGTH} caratteri.`,
          count: BODY_MAX_LENGTH,
        }),
      ),
  })
}

type NoteComposerFormValues = z.infer<ReturnType<typeof buildNoteComposerSchema>>

/** Hoistato: un `[]` inline creerebbe un riferimento nuovo a ogni render. */
const NO_QUOTES: NoteQuoteRef[] = []

export interface NoteComposerProps {
  entityType: string
  entityId: number
  /** Root note id to reply under. Omit to compose a new root note. */
  parentId?: number
  /** Note being edited; when set the composer is pre-filled and PATCHes on submit. */
  editingNote?: Note
  /** Called after a successful reply/edit submit, so the caller closes the inline composer. */
  onDone?: () => void
  /** Cancels an inline reply/edit composer. The root composer has none. */
  onCancel?: () => void
  autoFocus?: boolean
  /** Le Offerte selezionabili come destinazione; vuoto = nessun selettore. */
  quotes?: NoteQuoteRef[]
  /** Destinazione preselezionata (il filtro attivo, o l'Offerta del dettaglio). */
  defaultQuoteId?: number | null
  /** Dettaglio Offerta: destinazione fissa, nessuna scelta da offrire. */
  lockQuote?: boolean
}

/**
 * Single write surface for the three note-authoring flows (new root, reply,
 * edit — data_contract `POST/PATCH /api/notes`): a `MentionTextarea` plus a
 * submit button. `mentions` is tracked separately from the RHF-validated
 * `body` field because it is derived from the body's tokens by
 * `MentionTextarea` itself (D-12), not something the user types directly.
 */
export function NoteComposer({
  entityType,
  entityId,
  parentId,
  editingNote,
  onDone,
  onCancel,
  autoFocus,
  quotes = NO_QUOTES,
  defaultQuoteId = null,
  lockQuote = false,
}: NoteComposerProps) {
  const { t } = useTranslation()
  // Spec 0085 D-4: una reply eredita SEMPRE il contesto della root, quindi qui
  // non si sceglie nulla — il server ignorerebbe comunque un `quote_id` diverso.
  const [quoteTarget, setQuoteTarget] = useState<number | 'general'>(defaultQuoteId ?? 'general')
  const [mentions, setMentions] = useState<number[]>(
    () => editingNote?.mentions.map((mention) => mention.id) ?? [],
  )
  // Avatars of the people already mentioned (edit mode) plus the ones picked in
  // this session: the body tokens carry only `{name, id}`, so without this map
  // a draft chip would fall back to initials for a user who has a photo.
  const [avatarByUserId, setAvatarByUserId] = useState<ReadonlyMap<number, string | null>>(
    () => new Map(editingNote?.mentions.map((mention) => [mention.id, mention.avatar_url]) ?? []),
  )
  const createNote = useCreateNote(entityType, entityId)
  const updateNote = useUpdateNote(entityType, entityId)
  const pending = createNote.isPending || updateNote.isPending

  const form = useForm<NoteComposerFormValues>({
    resolver: zodResolver(buildNoteComposerSchema(t)),
    defaultValues: { body: editingNote?.body ?? '' },
  })

  const handleSubmit = form.handleSubmit(async (values) => {
    // Step 1: dispatch create or update depending on the composer's mode
    // Step 2: on success, reset the draft (root only) and notify the caller
    // Step 3: on a 422, map it onto the body field with the accessible triad
    try {
      if (editingNote) {
        await updateNote.mutateAsync({
          noteId: editingNote.id,
          payload: { body: values.body, mentions },
        })
      } else {
        await createNote.mutateAsync({
          entity_type: entityType,
          entity_id: entityId,
          body: values.body,
          parent_id: parentId,
          mentions,
          // Spec 0085: la chiave viaggia SOLO quando c'e' davvero un'Offerta
          // di destinazione. Su una reply il server la ignora comunque (eredita
          // dalla root, D-4), e "generale" e' gia' il significato della sua
          // assenza — mandare `null` esplicito direbbe la stessa cosa con una
          // chiave in piu' su ogni nota di ogni opportunita' senza offerte.
          ...(!parentId && quoteTarget !== 'general' ? { quote_id: quoteTarget } : {}),
        })
        form.reset({ body: '' })
        setMentions([])
      }
      onDone?.()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, ['body'])
      if (!handled) {
        form.setError('body', {
          message: t('notes.composer.genericError', { defaultValue: "Invio non riuscito. Riprova." }),
        })
      }
    }
  })

  const bodyValue = useWatch({ control: form.control, name: 'body' })
  const remainingCharacters = BODY_MAX_LENGTH - bodyValue.length

  return (
    <Form {...form}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-2">
        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <FormControl>
                <MentionTextarea
                  value={field.value}
                  onChange={(value, nextMentions) => {
                    field.onChange(value)
                    setMentions(nextMentions)
                  }}
                  onMentionPicked={(item) => {
                    setAvatarByUserId((current) =>
                      new Map(current).set(item.id, item.avatar_url ?? null),
                    )
                  }}
                  entityType={entityType}
                  entityId={entityId}
                  placeholder={t('notes.composer.placeholder', {
                    defaultValue: 'Scrivi una nota, usa @ per menzionare un collega…',
                  })}
                  disabled={pending}
                  rows={editingNote || parentId ? 2 : 3}
                  autoFocus={autoFocus}
                />
              </FormControl>
              <MentionBadges
                body={field.value}
                avatarByUserId={avatarByUserId}
                disabled={pending}
                onRemove={(userId) => {
                  const nextBody = removeMention(field.value, userId)
                  field.onChange(nextBody)
                  setMentions(extractMentionIds(nextBody))
                }}
              />
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex flex-wrap items-center justify-end gap-2">
          {/* Destinazione: solo sulle root (una reply eredita, D-4) e solo se
              c'e' davvero una scelta da fare (non sul dettaglio Offerta). */}
          {!parentId && !editingNote && !lockQuote ? (
            <NoteQuoteScopeSelect
              value={quoteTarget}
              // Il selettore parla il vocabolario completo (`all` incluso), la
              // destinazione no: senza `includeAll` quel ramo non e' raggiungibile.
              onChange={(scope) => setQuoteTarget(scope === 'all' ? 'general' : scope)}
              quotes={quotes}
              label={t('notes.scope.targetLabel')}
              className="mr-auto h-8 w-auto min-w-40 text-xs"
            />
          ) : null}
          <p className="mr-auto text-[11px] text-muted-foreground">
            {remainingCharacters <= CHARACTER_COUNTER_THRESHOLD
              ? t('notes.composer.charactersLeft', {
                  defaultValue: '{{count}} caratteri rimasti',
                  count: remainingCharacters,
                })
              : t('notes.composer.hint', {
                  defaultValue: 'Digita @ per menzionare un collega',
                })}
          </p>
          {onCancel ? (
            <Button type="button" variant="ghost" size="sm" onClick={onCancel} disabled={pending}>
              {t('common.cancel')}
            </Button>
          ) : null}
          <Button type="submit" size="sm" disabled={pending || bodyValue.trim().length === 0}>
            {pending ? (
              <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            ) : (
              <Send className="size-3.5" aria-hidden="true" />
            )}
            {editingNote
              ? t('notes.composer.save', { defaultValue: 'Salva' })
              : t('notes.composer.send', { defaultValue: 'Invia' })}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface MentionBadgesProps {
  /** Wire body (D-12): the badges are derived from the tokens it still carries. */
  body: string
  /** Avatars of the mentioned users, so a draft chip matches the posted one. */
  avatarByUserId: ReadonlyMap<number, string | null>
  disabled?: boolean
  onRemove: (userId: number) => void
}

/**
 * The people the draft mentions, as removable badges. The field itself only
 * ever shows the readable `@Name`, so this row is where a mention becomes a
 * visible, dismissible entity instead of raw markup.
 */
function MentionBadges({ body, avatarByUserId, disabled, onRemove }: MentionBadgesProps) {
  const { t } = useTranslation()
  const refs = parseMentionRefs(body)

  if (refs.length === 0) {
    return null
  }

  return (
    <ul className="flex flex-wrap items-center gap-1.5 pt-1.5">
      {refs.map((ref) => (
        <li key={ref.id} className="flex items-center gap-0.5">
          <MentionBadge
            userId={ref.id}
            name={ref.name}
            avatarUrl={avatarByUserId.get(ref.id) ?? null}
            className="max-w-40"
          />
          <button
            type="button"
            onClick={() => onRemove(ref.id)}
            disabled={disabled}
            aria-label={t('notes.composer.removeMention', {
              defaultValue: 'Rimuovi la menzione di {{name}}',
              name: ref.name,
            })}
            className="flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
          >
            <X className="size-3" aria-hidden="true" />
          </button>
        </li>
      ))}
    </ul>
  )
}
