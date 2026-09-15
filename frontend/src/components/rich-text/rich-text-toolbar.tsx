import { useRef, useState } from 'react'
import type { ComponentType } from 'react'
import { useTranslation } from 'react-i18next'
import { useEditorState } from '@tiptap/react'
import type { Editor } from '@tiptap/react'
import {
  Bold,
  Code2,
  Heading2,
  Heading3,
  ImagePlus,
  Italic,
  Link2,
  List,
  ListOrdered,
  Quote,
  Strikethrough,
  Underline,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { safeUrl } from '@/lib/safe-url'
import {
  RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES,
  RICH_TEXT_ALLOWED_LINK_PROTOCOLS,
} from '@/components/rich-text/rich-text-constants'

export interface RichTextToolbarProps {
  editor: Editor | null
  disabled?: boolean
  onPickImages: (files: File[]) => void
}

interface ToolbarState {
  bold: boolean
  italic: boolean
  underline: boolean
  strike: boolean
  bulletList: boolean
  orderedList: boolean
  heading2: boolean
  heading3: boolean
  blockquote: boolean
  codeBlock: boolean
  link: boolean
}

const EMPTY_STATE: ToolbarState = {
  bold: false,
  italic: false,
  underline: false,
  strike: false,
  bulletList: false,
  orderedList: false,
  heading2: false,
  heading3: false,
  blockquote: false,
  codeBlock: false,
  link: false,
}

interface ToggleButtonConfig {
  key: keyof ToolbarState
  labelKey: string
  defaultLabel: string
  icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>
  run: (editor: Editor) => void
}

const TOGGLE_BUTTONS: ToggleButtonConfig[] = [
  { key: 'bold', labelKey: 'richText.toolbar.bold', defaultLabel: 'Grassetto', icon: Bold, run: (e) => e.chain().focus().toggleBold().run() },
  { key: 'italic', labelKey: 'richText.toolbar.italic', defaultLabel: 'Corsivo', icon: Italic, run: (e) => e.chain().focus().toggleItalic().run() },
  { key: 'underline', labelKey: 'richText.toolbar.underline', defaultLabel: 'Sottolineato', icon: Underline, run: (e) => e.chain().focus().toggleUnderline().run() },
  { key: 'strike', labelKey: 'richText.toolbar.strike', defaultLabel: 'Barrato', icon: Strikethrough, run: (e) => e.chain().focus().toggleStrike().run() },
  { key: 'bulletList', labelKey: 'richText.toolbar.bulletList', defaultLabel: 'Elenco puntato', icon: List, run: (e) => e.chain().focus().toggleBulletList().run() },
  { key: 'orderedList', labelKey: 'richText.toolbar.orderedList', defaultLabel: 'Elenco numerato', icon: ListOrdered, run: (e) => e.chain().focus().toggleOrderedList().run() },
  { key: 'heading2', labelKey: 'richText.toolbar.heading2', defaultLabel: 'Titolo 2', icon: Heading2, run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run() },
  { key: 'heading3', labelKey: 'richText.toolbar.heading3', defaultLabel: 'Titolo 3', icon: Heading3, run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run() },
  { key: 'blockquote', labelKey: 'richText.toolbar.blockquote', defaultLabel: 'Citazione', icon: Quote, run: (e) => e.chain().focus().toggleBlockquote().run() },
  { key: 'codeBlock', labelKey: 'richText.toolbar.codeBlock', defaultLabel: 'Blocco di codice', icon: Code2, run: (e) => e.chain().focus().toggleCodeBlock().run() },
]

/**
 * Compact toolbar (text-xs/size-3.5, D-11) for `RichTextEditor`. Reads active
 * marks/nodes via `useEditorState` so pressed state stays in sync with the
 * selection without manual transaction listeners. Wraps on narrow widths
 * (`flex-wrap`) instead of scrolling (ui-design.md §6.1).
 */
export function RichTextToolbar({ editor, disabled, onPickImages }: RichTextToolbarProps) {
  const { t } = useTranslation()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [linkOpen, setLinkOpen] = useState(false)
  const [linkValue, setLinkValue] = useState('')
  const [linkError, setLinkError] = useState(false)

  const state = useEditorState({
    editor,
    selector: ({ editor: current }): ToolbarState =>
      current
        ? {
            bold: current.isActive('bold'),
            italic: current.isActive('italic'),
            underline: current.isActive('underline'),
            strike: current.isActive('strike'),
            bulletList: current.isActive('bulletList'),
            orderedList: current.isActive('orderedList'),
            heading2: current.isActive('heading', { level: 2 }),
            heading3: current.isActive('heading', { level: 3 }),
            blockquote: current.isActive('blockquote'),
            codeBlock: current.isActive('codeBlock'),
            link: current.isActive('link'),
          }
        : EMPTY_STATE,
  })

  if (!editor) {
    return null
  }

  // `useEditorState` types its result as nullable to match `editor: Editor |
  // null` in general; narrowed here since the early return above guarantees
  // an editor, and its selector always returns `EMPTY_STATE` as a fallback.
  const activeState = state ?? EMPTY_STATE

  const openLinkPopover = () => {
    const href = editor.getAttributes('link').href
    setLinkValue(typeof href === 'string' ? href : '')
    setLinkError(false)
    setLinkOpen(true)
  }

  const applyLink = () => {
    const trimmed = linkValue.trim()
    if (trimmed === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
      setLinkOpen(false)
      return
    }
    const validated = safeUrl(trimmed, RICH_TEXT_ALLOWED_LINK_PROTOCOLS)
    if (!validated) {
      setLinkError(true)
      return
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: validated }).run()
    setLinkOpen(false)
  }

  return (
    <div className="flex flex-wrap items-center gap-0.5 rounded-t-md border-b border-input bg-surface px-1.5 py-1">
      {TOGGLE_BUTTONS.map(({ key, labelKey, defaultLabel, icon: Icon, run }) => (
        <Button
          key={key}
          type="button"
          variant="ghost"
          size="icon-xs"
          aria-label={t(labelKey, { defaultValue: defaultLabel })}
          aria-pressed={activeState[key]}
          disabled={disabled}
          onClick={() => run(editor)}
          className={cn(activeState[key] && 'bg-accent text-accent-foreground')}
        >
          <Icon className="size-3.5" aria-hidden="true" />
        </Button>
      ))}
      <Popover open={linkOpen} onOpenChange={setLinkOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('richText.toolbar.link', { defaultValue: 'Link' })}
            aria-pressed={activeState.link}
            disabled={disabled}
            onClick={openLinkPopover}
            className={cn(activeState.link && 'bg-accent text-accent-foreground')}
          >
            <Link2 className="size-3.5" aria-hidden="true" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-2" align="start">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="rich-text-link-url" className="text-xs font-medium text-foreground">
              {t('richText.link.urlLabel', { defaultValue: 'Indirizzo' })}
            </label>
            <Input
              id="rich-text-link-url"
              value={linkValue}
              onChange={(event) => {
                setLinkValue(event.target.value)
                setLinkError(false)
              }}
              placeholder={t('richText.link.urlPlaceholder', { defaultValue: 'https://…' })}
              aria-invalid={linkError}
              className="h-8 text-xs"
            />
            {linkError ? (
              <p className="text-xs text-destructive">
                {t('richText.link.invalidUrl', {
                  defaultValue: "Indirizzo non valido. Usa un link http, https o mailto.",
                })}
              </p>
            ) : null}
            <div className="flex justify-end gap-1.5 pt-1">
              <Button type="button" variant="ghost" size="xs" onClick={() => setLinkOpen(false)}>
                {t('richText.link.cancel', { defaultValue: 'Annulla' })}
              </Button>
              <Button type="button" size="xs" onClick={applyLink}>
                {t('richText.link.apply', { defaultValue: 'Applica' })}
              </Button>
            </div>
          </div>
        </PopoverContent>
      </Popover>
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        aria-label={t('richText.toolbar.image', { defaultValue: 'Immagine' })}
        disabled={disabled}
        onClick={() => fileInputRef.current?.click()}
      >
        <ImagePlus className="size-3.5" aria-hidden="true" />
      </Button>
      <input
        ref={fileInputRef}
        type="file"
        accept={RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES.join(',')}
        multiple
        hidden
        onChange={(event) => {
          const files = Array.from(event.target.files ?? [])
          event.target.value = ''
          if (files.length > 0) {
            onPickImages(files)
          }
        }}
      />
    </div>
  )
}
