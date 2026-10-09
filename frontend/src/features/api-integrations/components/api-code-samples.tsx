import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { CopyButton } from '@/features/api-integrations/components/copy-button'
import { CODE_LANGUAGES, type CodeLanguage } from '@/features/api-integrations/code-samples'

const LANGUAGE_LABELS: Record<CodeLanguage, string> = { curl: 'cURL', javascript: 'JavaScript' }
const TAB_CLASS = 'px-2 py-0.5 text-xs'

interface CodeBlockProps {
  label: string
  code: string
}

/** Code in a recessed well that scrolls inside its own box, with a copy button. */
export function CodeBlock({ label, code }: CodeBlockProps) {
  const { t } = useTranslation()

  return (
    <div className="flex min-w-0 flex-col gap-1">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-medium">{label}</p>
        <CopyButton
          value={code}
          label={t('apiIntegrations.docs.copyLabel', { label })}
          successMessage={t('apiIntegrations.docs.copied')}
        />
      </div>
      <pre
        role="region"
        aria-label={label}
        tabIndex={0}
        className="max-h-72 max-w-full overflow-auto rounded-md border border-border bg-surface p-2.5 font-mono text-xs leading-relaxed focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        {code}
      </pre>
    </div>
  )
}

interface LanguageTabsProps {
  /** Renders the content for the selected language. */
  children: (language: CodeLanguage) => ReactNode
}

/** cURL / JavaScript switch shared by every block rendered inside it. */
export function LanguageTabs({ children }: LanguageTabsProps) {
  const { t } = useTranslation()

  return (
    <Tabs defaultValue={CODE_LANGUAGES[0]} className="min-w-0 gap-2">
      <TabsList aria-label={t('apiIntegrations.docs.languageLabel')} className="w-fit">
        {CODE_LANGUAGES.map((language) => (
          <TabsTrigger key={language} value={language} className={TAB_CLASS}>
            {LANGUAGE_LABELS[language]}
          </TabsTrigger>
        ))}
      </TabsList>
      {CODE_LANGUAGES.map((language) => (
        <TabsContent key={language} value={language} className="flex min-w-0 flex-col gap-2">
          {children(language)}
        </TabsContent>
      ))}
    </Tabs>
  )
}
