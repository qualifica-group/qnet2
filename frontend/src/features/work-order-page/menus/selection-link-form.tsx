import { useId, useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { RICH_TEXT_ALLOWED_LINK_PROTOCOLS } from '@/components/rich-text/rich-text-constants'
import { safeUrl } from '@/lib/safe-url'

interface SelectionLinkFormProps {
  editor: Editor
  /** Back to the formatting buttons (after applying or cancelling). */
  onDone: () => void
}

/**
 * Inline link editor of the selection bar. It replaces the buttons instead of
 * opening a popover, so the focus stays inside the bar and the bubble menu
 * does not hide while the writer types the address.
 */
export function SelectionLinkForm({ editor, onDone }: SelectionLinkFormProps) {
  const { t } = useTranslation()
  const inputId = useId()
  const errorId = `${inputId}-error`
  const currentHref = editor.getAttributes('link').href as string | undefined
  const [value, setValue] = useState(currentHref ?? '')
  const [invalid, setInvalid] = useState(false)

  const finish = () => {
    onDone()
    editor.commands.focus()
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const trimmed = value.trim()
    if (trimmed === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
      onDone()
      return
    }
    const href = safeUrl(trimmed, RICH_TEXT_ALLOWED_LINK_PROTOCOLS)
    if (!href) {
      setInvalid(true)
      return
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href }).run()
    onDone()
  }

  const handleRemove = () => {
    editor.chain().focus().extendMarkRange('link').unsetLink().run()
    onDone()
  }

  return (
    <form
      onSubmit={handleSubmit}
      onKeyDown={(event) => {
        if (event.key === 'Escape') {
          event.preventDefault()
          finish()
        }
      }}
      className="flex flex-col gap-1 p-0.5"
    >
      <div className="flex items-center gap-1">
        <label htmlFor={inputId} className="sr-only">
          {t('workOrderPage.menus.link.urlLabel')}
        </label>
        <Input
          id={inputId}
          value={value}
          autoFocus
          onChange={(event) => {
            setValue(event.target.value)
            setInvalid(false)
          }}
          placeholder={t('workOrderPage.menus.link.urlPlaceholder')}
          aria-invalid={invalid}
          aria-describedby={invalid ? errorId : undefined}
          className="h-6 w-44 text-xs"
        />
        <Button type="submit" size="xs">
          {t('workOrderPage.menus.link.apply')}
        </Button>
        {currentHref ? (
          <Button type="button" size="xs" variant="ghost" onClick={handleRemove}>
            {t('workOrderPage.menus.link.remove')}
          </Button>
        ) : null}
        <Button type="button" size="xs" variant="ghost" onClick={finish}>
          {t('workOrderPage.menus.link.cancel')}
        </Button>
      </div>
      {invalid ? (
        <span id={errorId} role="alert" className="px-1 text-xs text-destructive">
          {t('workOrderPage.menus.link.invalid')}
        </span>
      ) : null}
    </form>
  )
}
