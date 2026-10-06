<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\EmailTemplateModule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves the EmailOwner of an owner model (by morph alias) or of an
 * EmailTemplateModule. The alias => class map lives in
 * `config('outbound_emails.owners')`; implementations are built lazily
 * through the container, so none is instantiated until first used.
 */
final class EmailOwnerRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(Model $owner): EmailOwner
    {
        return $this->forAlias($owner->getMorphClass());
    }

    public function forAlias(string $alias): EmailOwner
    {
        /** @var array<string, class-string<EmailOwner>> $owners */
        $owners = config('outbound_emails.owners', []);

        if (! isset($owners[$alias])) {
            throw new InvalidArgumentException("No email owner registered for [{$alias}].");
        }

        return $this->container->make($owners[$alias]);
    }

    public function forModule(EmailTemplateModule $module): EmailOwner
    {
        /** @var array<string, class-string<EmailOwner>> $owners */
        $owners = config('outbound_emails.owners', []);

        foreach (array_keys($owners) as $alias) {
            $owner = $this->forAlias($alias);

            if ($owner->templateModule() === $module) {
                return $owner;
            }
        }

        throw new InvalidArgumentException("No email owner registered for module [{$module->value}].");
    }
}
