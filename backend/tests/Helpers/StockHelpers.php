<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('stockUserWith')) {
    /**
     * A user holding exactly the given `stock` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function stockUserWith(array $abilities = ['viewAny', 'manage']): User
    {
        foreach (['viewAny', 'manage'] as $ability) {
            Permission::findOrCreate("stock.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("stock.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('stockTrackedProduct')) {
    function stockTrackedProduct(): Product
    {
        return Product::factory()->internalUse()->create();
    }
}

if (! function_exists('stockPayload')) {
    /**
     * Request body of POST /api/stock-movements.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function stockPayload(StockMovementType $type, Product $product, OperationalSite $site, array $overrides = []): array
    {
        return [
            'type' => $type->value,
            'product_id' => $product->id,
            'operational_site_id' => $site->id,
            ...$overrides,
        ];
    }
}

if (! function_exists('stockQuantity')) {
    /** Quantity of the (product, site, user) row, null when the row does not exist. */
    function stockQuantity(Product $product, OperationalSite $site, ?User $user = null): ?string
    {
        return StockBalance::query()
            ->where('product_id', $product->id)
            ->where('operational_site_id', $site->id)
            ->where('user_id', $user?->id)
            ->first()
            ?->quantity;
    }
}

if (! function_exists('stockSeed')) {
    /** A balance row created directly, bypassing the service. */
    function stockSeed(Product $product, OperationalSite $site, string $quantity, ?User $user = null): StockBalance
    {
        return StockBalance::factory()->create([
            'product_id' => $product->id,
            'operational_site_id' => $site->id,
            'user_id' => $user?->id,
            'quantity' => $quantity,
        ]);
    }
}
