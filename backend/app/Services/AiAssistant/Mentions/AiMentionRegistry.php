<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The mentionable modules of the AI assistant (spec 0347, D-4): 18 kebab-case
 * keys, the same as the `for-select` URL prefix and the policy permission prefix.
 */
final class AiMentionRegistry
{
    /** @var array<string, AiMentionDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        return array_keys($this->definitions());
    }

    public function has(string $module): bool
    {
        return isset($this->definitions()[$module]);
    }

    public function definition(string $module): ?AiMentionDefinition
    {
        return $this->definitions()[$module] ?? null;
    }

    /**
     * The modules the user may view (`{module}.view`), in registry order.
     *
     * @return array<int, string>
     */
    public function availableModules(User $user): array
    {
        return array_values(array_filter(
            $this->modules(),
            fn (string $module): bool => $this->canView($user, $this->definitions()[$module]),
        ));
    }

    private function canView(User $user, AiMentionDefinition $definition): bool
    {
        try {
            return $user->can($definition->viewPermission());
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * @return array<string, AiMentionDefinition>
     */
    private function definitions(): array
    {
        return $this->definitions ??= collect($this->build())->keyBy('module')->all();
    }

    /**
     * @return array<int, AiMentionDefinition>
     */
    private function build(): array
    {
        return [
            new AiMentionDefinition('companies', Company::class, static fn (Model $m): string => (string) $m->denomination),
            new AiMentionDefinition('company-sites', CompanySite::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('referents', Referent::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('registries', Registry::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('leads', Lead::class, static fn (Model $m): string => $m->registry?->name ?? '#'.$m->id, ['registry']),
            new AiMentionDefinition('opportunities', Opportunity::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('campaigns', Campaign::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('quotes', Quote::class, static fn (Model $m): string => self::codeAndTitle($m->code, $m->title)),
            new AiMentionDefinition('work-orders', WorkOrder::class, static fn (Model $m): string => self::codeAndTitle($m->code, $m->title)),
            new AiMentionDefinition('projects', Project::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('tasks', Task::class, static fn (Model $m): string => (string) $m->title),
            new AiMentionDefinition('products', Product::class, static fn (Model $m): string => (string) $m->name),
            new AiMentionDefinition('invoices', Invoice::class, static fn (Model $m): string => $m->number.'/'.$m->year),
            new AiMentionDefinition('purchase-invoices', PurchaseInvoice::class, static fn (Model $m): string => $m->document_number ?? '#'.$m->id),
            new AiMentionDefinition('purchase-orders', PurchaseOrder::class, static fn (Model $m): string => $m->code()),
            new AiMentionDefinition('purchase-requests', PurchaseRequest::class, static fn (Model $m): string => (string) $m->subject),
            new AiMentionDefinition('contracts', Contract::class, static fn (Model $m): string => $m->quote === null ? '#'.$m->id : self::codeAndTitle($m->quote->code, $m->quote->title), ['quote']),
            new AiMentionDefinition('users', User::class, static fn (Model $m): string => (string) $m->name),
        ];
    }

    /** Same "code — title" composition as the quote for-select label. */
    private static function codeAndTitle(?string $code, ?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' ? (string) $code : $code.' — '.$title;
    }
}
