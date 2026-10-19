import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Copy, Upload } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import type {
  MigrationColumn,
  MigrationColumnsRequest,
  MigrationColumnsSample,
} from '@/features/migrations/types'

/** How long the copy button shows its "copied" confirmation, in ms. */
const COPY_FEEDBACK_MS = 1500

interface MigrationTemplatePanelProps {
  sourceLabel: string
  columns: MigrationColumn[]
  request: MigrationColumnsRequest | undefined
  sample: MigrationColumnsSample | undefined
  isLoading: boolean
  isError: boolean
  onImportClick: () => void
  /** False when this installation may not import the source (spec 0285 D-5). */
  isImportAvailable: boolean
}

/** Copies `value` to the clipboard and briefly flips to a "copied" state. */
function useCopyToClipboard() {
  const [copied, setCopied] = useState(false)

  const copy = (value: string) => {
    void navigator.clipboard
      ?.writeText(value)
      .then(() => {
        setCopied(true)
        window.setTimeout(() => setCopied(false), COPY_FEEDBACK_MS)
      })
      .catch(() => {})
  }

  return { copied, copy }
}

/** Copy button for a single text value, with an accessible transient confirmation. */
function CopyButton({ value, label, copiedLabel }: { value: string; label: string; copiedLabel: string }) {
  const { copied, copy } = useCopyToClipboard()

  return (
    <Button type="button" variant="outline" size="sm" onClick={() => copy(value)} className="shrink-0">
      {copied ? (
        <Check aria-hidden="true" className="text-emerald-600 dark:text-emerald-400" />
      ) : (
        <Copy aria-hidden="true" />
      )}
      <span aria-live="polite">{copied ? copiedLabel : label}</span>
    </Button>
  )
}

/** The "expected endpoint" block: method + full request URL, with a copy action. */
function EndpointBlock({ request, t }: { request: MigrationColumnsRequest; t: ReturnType<typeof useTranslation>['t'] }) {
  const hasBaseUrl = request.base_url !== ''
  const displayUrl = hasBaseUrl ? request.url : request.path

  return (
    <div className="flex flex-col gap-2 rounded-md border p-3">
      <h3 className="text-sm font-semibold">{t('template.endpointTitle')}</h3>
      <div className="flex flex-wrap items-center gap-2">
        <Badge variant="secondary" className="font-mono" aria-label={t('template.method', { method: request.method })}>
          {request.method}
        </Badge>
        <code className="min-w-0 flex-1 break-all font-mono text-xs sm:text-sm">{displayUrl}</code>
        <CopyButton value={displayUrl} label={t('template.copyUrl')} copiedLabel={t('template.copied')} />
      </div>
      {!hasBaseUrl ? (
        <p className="text-xs text-muted-foreground">{t('template.baseUrlMissing')}</p>
      ) : null}
    </div>
  )
}

/** The "sample response" block: pretty-printed JSON, with a copy action. */
function SampleBlock({ sample, t }: { sample: MigrationColumnsSample; t: ReturnType<typeof useTranslation>['t'] }) {
  const sampleJson = JSON.stringify(sample, null, 2)

  return (
    <div className="flex flex-col gap-2 rounded-md border p-3">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-semibold">{t('template.sampleTitle')}</h3>
        <CopyButton value={sampleJson} label={t('template.copyJson')} copiedLabel={t('template.copied')} />
      </div>
      <pre className="max-h-64 overflow-auto rounded-md bg-muted/40 p-3 text-xs">
        <code>{sampleJson}</code>
      </pre>
    </div>
  )
}

/**
 * Primary view of the Migrations page (spec 0013 redesign, template-first):
 * qnet is the contract owner, so this renders the field schema the external
 * source is EXPECTED to return -- `useMigrationColumns` is a static, backend
 * `data_contract` lookup with no external call. Alongside the field schema,
 * it also surfaces the expected endpoint URL and a copyable sample response,
 * both additive fields of that same contract. The "import this source"
 * action lives here, right next to the schema it commits to.
 */
export function MigrationTemplatePanel({
  sourceLabel,
  columns,
  request,
  sample,
  isLoading,
  isError,
  onImportClick,
  isImportAvailable,
}: MigrationTemplatePanelProps) {
  const { t } = useTranslation('migrations')

  return (
    <Card>
      <CardHeader className="border-b pb-4">
        <CardTitle>{t('template.title')}</CardTitle>
        <CardDescription>{t('template.description', { source: sourceLabel })}</CardDescription>
        {isImportAvailable ? null : (
          <p className="text-sm text-muted-foreground" role="note">
            {t('template.importUnavailable')}
          </p>
        )}
        <CardAction>
          <Button type="button" onClick={onImportClick} disabled={!isImportAvailable}>
            <Upload aria-hidden="true" />
            {t('template.importButton')}
          </Button>
        </CardAction>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {isError ? (
          <p className="text-sm text-destructive" role="alert">
            {t('template.loadError')}
          </p>
        ) : isLoading ? (
          <div className="flex flex-col gap-2">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </div>
        ) : (
          <>
            {request ? <EndpointBlock request={request} t={t} /> : null}
            {sample ? <SampleBlock sample={sample} t={t} /> : null}

            {columns.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('template.empty')}</p>
            ) : (
              <div className="max-h-96 overflow-auto rounded-md border">
                <table className="w-full text-left text-sm">
                  <thead className="bg-muted/60">
                    <tr>
                      <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                        {t('template.fieldHeader')}
                      </th>
                      <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                        {t('template.typeHeader')}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {columns.map((column) => (
                      <tr key={column.id} className="border-t">
                        <td className="px-3 py-2">
                          <div className="font-mono text-sm">{column.id}</div>
                          {column.label && column.label !== column.id ? (
                            <div className="truncate text-xs text-muted-foreground">
                              {column.label}
                            </div>
                          ) : null}
                        </td>
                        <td className="px-3 py-2">
                          <Badge variant="secondary" className="font-mono">
                            {column.type}
                          </Badge>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}
