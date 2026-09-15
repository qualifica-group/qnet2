import { useEffect, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Loader2, Send } from 'lucide-react'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { NoteQuoteScopeSelect } from '@/features/notes/note-quote-scope-select'
import { Form, FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { RICH_TEXT_NOTE_TEXT_MAX } from '@/components/rich-text/rich-text-constants'
import { extractMentionIds, getVisibleTextLength } from '@/features/notes/note-rich-text'
import { useNoteMentionExtension } from '@/features/notes/use-note-mention-extension'
import { useCreateNote, useUpdateNote } from '@/features/notes/use-note-mutations'
import type { Note, NoteQuoteRef } from '@/features/notes/types'

/** Below this many characters left the hint gives way to a countdown. */
const CHARACTER_COUNTER_THRESHOLD = 500

function buildNoteComposerSchema(t: TFunction) {
  return z.object({
    body: z
      .string()
      .nullable()
      .superRefine((value, ctx) => {
        if (!value) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: t('notes.composer.bodyRequired', { defaultValue: 'Scrivi qualcosa prima di inviare.' }),
          })
          return
        }
        if (getVisibleTextLength(value) > RICH_TEXT_NOTE_TEXT_MAX) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: t('notes.composer.bodyTooLong', {
              defaultValue: `La nota puo' contenere al massimo ${RICH_TEXT_NOTE_TEXT_MAX} caratteri.`,
              count: RICH_TEXT_NOTE_TEXT_MAX,
            }),
          })
        }
      }),
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
 * edit — data_contract `POST/PATCH /api/notes`): the shared `RichTextEditor`
 * (D-11) plus a submit button. `mentions` is never separate state — it is
 * derived at submit time straight from the mention nodes the body HTML
 * actually holds (D-7/D-12), so a mention deleted from the doc drops out on
 * its own.
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
  const createNote = useCreateNote(entityType, entityId)
  const updateNote = useUpdateNote(entityType, entityId)
  const pending = createNote.isPending || updateNote.isPending
  const mentionExtension = useNoteMentionExtension({ entityType, entityId })
  const editorContainerRef = useRef<HTMLDivElement | null>(null)

  const form = useForm<NoteComposerFormValues>({
    resolver: zodResolver(buildNoteComposerSchema(t)),
    defaultValues: { body: editingNote?.body ?? null },
  })

  // RichTextEditor (D-11) isn't a form control the browser can autofocus by
  // attribute: it exposes a contentEditable `role="textbox"` inside its own
  // subtree, so autofocus is a DOM lookup once that subtree is mounted.
  useEffect(() => {
    if (!autoFocus) {
      return
    }
    editorContainerRef.current?.querySelector<HTMLElement>('[role="textbox"]')?.focus()
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional: focus once, on mount only.
  }, [])

  const handleSubmit = form.handleSubmit(async (values) => {
    // Step 1: derive the wire body/mentions from what the editor actually holds
    // Step 2: dispatch create or update depending on the composer's mode
    // Step 3: on success, reset the draft (root only) and notify the caller
    // Step 4: on a 422, map it onto the body field with the accessible triad
    const body = values.body ?? ''
    const mentions = extractMentionIds(body)
    try {
      if (editingNote) {
        await updateNote.mutateAsync({ noteId: editingNote.id, payload: { body, mentions } })
      } else {
        await createNote.mutateAsync({
          entity_type: entityType,
          entity_id: entityId,
          body,
          parent_id: parentId,
          mentions,
          // Spec 0085: la chiave viaggia SOLO quando c'e' davvero un'Offerta
          // di destinazione. Su una reply il server la ignora comunque (eredita
          // dalla root, D-4), e "generale" e' gia' il significato della sua
          // assenza — mandare `null` esplicito direbbe la stessa cosa con una
          // chiave in piu' su ogni nota di ogni opportunita' senza offerte.
          ...(!parentId && quoteTarget !== 'general' ? { quote_id: quoteTarget } : {}),
        })
        form.reset({ body: null })
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
  const remainingCharacters = RICH_TEXT_NOTE_TEXT_MAX - getVisibleTextLength(bodyValue)

  return (
    <Form {...form}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-2">
        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <div ref={editorContainerRef}>
                <FormControl>
                  <RichTextEditor
                    value={field.value}
                    onChange={field.onChange}
                    extraExtensions={mentionExtension}
                    placeholder={t('notes.composer.placeholder', {
                      defaultValue: 'Scrivi una nota, usa @ per menzionare un collega…',
                    })}
                    disabled={pending}
                    minHeight="compact"
                  />
                </FormControl>
              </div>
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
          <Button type="submit" size="sm" disabled={pending || !bodyValue}>
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
