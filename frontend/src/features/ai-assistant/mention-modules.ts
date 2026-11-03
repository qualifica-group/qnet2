import {
  Building2,
  Briefcase,
  UserCog,
  CheckSquare,
  FileSignature,
  FileText,
  Megaphone,
  MapPin,
  Package,
  ReceiptText,
  ShoppingCart,
  Target,
  UserPlus,
  UserRound,
  Users,
  Wrench,
  FolderKanban,
  ClipboardList,
  type LucideIcon,
} from 'lucide-react'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { AiMentionModule } from '@/features/ai-assistant/types'

export interface AiMentionModuleDefinition {
  module: AiMentionModule
  /** for-select resource: identical to the module key (spec 0347). */
  resource: string
  /** i18n key of the label (`ai-assistant` namespace is created by the UI lane). */
  labelKey: string
  icon: LucideIcon
}

function define(module: AiMentionModule, labelKey: string, icon: LucideIcon): AiMentionModuleDefinition {
  return { module, resource: module, labelKey: `aiAssistant.mentionModules.${labelKey}`, icon }
}

export const AI_MENTION_MODULES: readonly AiMentionModuleDefinition[] = [
  define('companies', 'companies', Building2),
  define('company-sites', 'companySites', MapPin),
  define('referents', 'referents', UserRound),
  define('registries', 'registries', Users),
  define('leads', 'leads', UserPlus),
  define('opportunities', 'opportunities', Target),
  define('campaigns', 'campaigns', Megaphone),
  define('quotes', 'quotes', FileText),
  define('work-orders', 'workOrders', Wrench),
  define('projects', 'projects', FolderKanban),
  define('tasks', 'tasks', CheckSquare),
  define('products', 'products', Package),
  define('invoices', 'invoices', ReceiptText),
  define('purchase-invoices', 'purchaseInvoices', ClipboardList),
  define('purchase-orders', 'purchaseOrders', ShoppingCart),
  define('purchase-requests', 'purchaseRequests', Briefcase),
  define('contracts', 'contracts', FileSignature),
  define('users', 'users', UserCog),
]

const BY_MODULE = new Map(AI_MENTION_MODULES.map((definition) => [definition.module, definition]))

export function getAiMentionModule(module: AiMentionModule): AiMentionModuleDefinition {
  const definition = BY_MODULE.get(module)
  if (!definition) {
    throw new Error(`Unknown AI mention module: ${module}`)
  }
  return definition
}

/** Minimum characters before the mention search hits the server (AC-022). */
export const AI_MENTION_MIN_SEARCH_LENGTH = 2
export const AI_MENTION_SEARCH_LIMIT = 8

export async function searchAiMentions(module: AiMentionModule, term: string): Promise<ForSelectItem[]> {
  const { resource } = getAiMentionModule(module)
  const response = await fetchForSelect(resource, {
    search: term,
    limit: AI_MENTION_SEARCH_LIMIT,
    includeTotal: false,
  })
  return response.items
}
