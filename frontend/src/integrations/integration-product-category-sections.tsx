import { INTEGRATIONS } from '@/integrations'
import { useActiveIntegrations } from '@/integrations/use-active-integrations'

/** The product category detail sections of the ACTIVE integrations, in registry order. */
export function IntegrationProductCategorySections({ categoryId }: { categoryId: number }) {
  const { active } = useActiveIntegrations()

  return INTEGRATIONS.map((integration) => {
    const Section = integration.ProductCategorySection
    return Section && active.includes(integration.key) ? <Section key={integration.key} categoryId={categoryId} /> : null
  })
}
