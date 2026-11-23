import { useTranslation } from 'react-i18next'
import type { useArchivioSmartConnection } from '@/features/document-checklists/use-archivio-smart-connection'

interface ArchivioSmartConnectionTestResultProps {
  test: ReturnType<typeof useArchivioSmartConnection>['test']
}

/** Outcome of the connection test in a live region (carried by text, not only by colour). */
export function ArchivioSmartConnectionTestResult({ test }: ArchivioSmartConnectionTestResultProps) {
  const { t } = useTranslation()
  const result = test.data

  let content = null
  if (test.isPending) {
    content = <span>{t('documentChecklists.connection.testing')}</span>
  } else if (test.isError) {
    content = <span className="text-destructive">{t('documentChecklists.connection.testError')}</span>
  } else if (result) {
    content = (
      <span className={result.ok ? 'text-foreground' : 'text-destructive'}>
        <span className="font-medium">
          {t(result.ok ? 'documentChecklists.connection.testOk' : 'documentChecklists.connection.testFailed')}
        </span>
        {result.http_status !== null ? (
          <span> {t('documentChecklists.connection.httpStatus', { status: result.http_status })}.</span>
        ) : null}
        <span> {result.message}</span>
      </span>
    )
  }

  return (
    <div role="status" className="min-h-5 text-xs">
      {content}
    </div>
  )
}
