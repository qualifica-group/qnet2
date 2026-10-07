<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * Runs a domain's OWN update FormRequest against one inline cell edit (spec
 * 0206, D-2), so the grid follows exactly the rules the form follows: the
 * same `rules()`, the same `withValidator()` cross-checks (which read the
 * persisted row through `route()`), the same `prepareForValidation()`
 * normalisation and the same EnforcesFieldPermissions gate — none of them
 * re-declared in a column catalogue, where they would drift.
 *
 * The request is built as the form's own PATCH would arrive: a JSON body
 * carrying only the submitted keys (the update rules are all `sometimes`),
 * the actor as its user and the row bound under the controller's route
 * parameter name. Authorization stays where it already is (the cell engine's
 * Policy + field-permission steps); a request's own authorize() runs too.
 */
final class FormRequestCellValidator
{
    private const string CELL_URI = '/table-cell';

    public function __construct(private readonly Container $container) {}

    /**
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $requestClass
     * @param  array<string, mixed>  $payload
     * @return TRequest the validated request, ready for its own `toData()`
     *
     * @throws ValidationException
     */
    public function validate(string $requestClass, string $routeParameter, Model $row, User $actor, array $payload): FormRequest
    {
        $request = $requestClass::create(
            self::CELL_URI,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: (string) json_encode($payload),
        );

        $request->setContainer($this->container)->setRedirector($this->container->make(Redirector::class));
        $request->setUserResolver(static fn (): User => $actor);

        $route = (new Route('PATCH', self::CELL_URI, []))->bind($request);
        $route->setParameter($routeParameter, $row);
        $request->setRouteResolver(static fn (): Route => $route);

        $request->validateResolved();

        return $request;
    }
}
