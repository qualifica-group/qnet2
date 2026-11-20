import { useId, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Paperclip, X } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { formatBytes } from '@/features/attachments/format-bytes'
import type { RequestCreateDocuments } from '@/features/request-management/use-request-create-documents'

/** From two parent categories up there are several offers to choose from. */
const MIN_GROUPS_FOR_CHOICE = 2

interface RequestCreateDocumentsSectionProps {
  documents: RequestCreateDocuments
}

/**
 * "Documenti" of "Nuova richiesta" (spec 0415, D-4): the files wait here and
 * are uploaded after the save. With lines of several parent categories the
 * save creates one offer each (spec 0411), so every file carries the choice of
 * the offer it goes to; with one parent category there is nothing to choose.
 */
export function RequestCreateDocumentsSection({ documents }: RequestCreateDocumentsSectionProps) {
  const { t } = useTranslation()
  const inputId = useId()
  const { documents: files, groups, add, remove, changeRoot } = documents
  const canChoose = groups.length >= MIN_GROUPS_FOR_CHOICE

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    add(Array.from(event.target.files ?? []))
    // Reset so re-selecting the same file still fires a change event.
    event.target.value = ''
  }

  return (
    <FormSection
      icon={Paperclip}
      title={t('requestManagement.form.create.documents.title')}
      description={t(
        canChoose
          ? 'requestManagement.form.create.documents.descriptionPerOffer'
          : 'requestManagement.form.create.documents.description',
      )}
    >
      <div className="flex flex-col gap-2">
        <label htmlFor={inputId} className="sr-only">
          {t('requestManagement.form.create.documents.pick')}
        </label>
        <Input id={inputId} type="file" multiple className="h-8 text-xs" onChange={handleChange} />
        {files.length > 0 ? (
          <ul className="flex flex-col gap-1.5">
            {files.map((document) => (
              <li
                key={document.key}
                className="flex flex-wrap items-center gap-2 rounded-md border bg-card px-2.5 py-1.5 text-xs"
              >
                <span className="min-w-0 flex-1 truncate" title={document.file.name}>
                  {document.file.name}
                </span>
                <span className="shrink-0 text-muted-foreground">{formatBytes(document.file.size)}</span>
                {canChoose && document.rootId !== null ? (
                  <Select value={String(document.rootId)} onValueChange={(value) => changeRoot(document.key, Number(value))}>
                    <SelectTrigger
                      size="sm"
                      className="w-full text-xs sm:w-44"
                      aria-label={t('requestManagement.form.create.documents.offerFor', { name: document.file.name })}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {groups.map((group) => (
                        <SelectItem key={group.id} value={String(group.id)} className="text-xs">
                          {t('requestManagement.form.create.documents.offerOption', { root: group.name })}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : null}
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  aria-label={t('requestManagement.form.create.documents.remove', { name: document.file.name })}
                  onClick={() => remove(document.key)}
                >
                  <X />
                </Button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </FormSection>
  )
}
