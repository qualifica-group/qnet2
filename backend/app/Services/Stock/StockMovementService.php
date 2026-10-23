<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\DataObjects\Stock\StockMovementData;
use App\DataObjects\Stock\StockSide;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records stock movements and keeps the balance rows in step (spec 0307,
 * D-11..D-15). Every movement runs in a transaction that locks the product
 * row first, so concurrent movements of one product are serialised; the
 * unique key of the balances is the last line of defence. A balance reaching
 * zero is deleted (the register keeps the history).
 */
final class StockMovementService
{
    public const int SCALE = 3;

    /** Relations the stock resources read (lazy loading is forbidden). */
    public const array BALANCE_RELATIONS = ['product.unitOfMeasure', 'operationalSite.addresses.city', 'user'];

    public const array MOVEMENT_RELATIONS = [
        'product.unitOfMeasure',
        'fromOperationalSite.addresses.city', 'toOperationalSite.addresses.city',
        'fromUser', 'toUser', 'creator',
    ];

    /**
     * @return array{movement: StockMovement, balances: Collection<int, StockBalance>}
     */
    public function record(User $actor, StockMovementData $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            // Step 1: serialise the movements of this product.
            Product::query()->whereKey($data->productId)->lockForUpdate()->firstOrFail();

            // Step 2: translate the request into source / destination / quantity.
            [$from, $to, $quantity] = $this->resolveSides($data);

            // Step 3: decrease the source row, increase the destination row.
            $touched = [];

            if ($from !== null) {
                $this->decrease($data, $from, $quantity);
            }

            if ($to !== null) {
                $touched[] = $this->increase($data, $to, $quantity)->id;
            }

            // Step 4: write the register line.
            $movement = $this->writeMovement($actor, $data, $from, $to, $quantity);

            // Step 5: the rows touched that still exist, with what the resources read.
            $survivors = $from === null ? [] : $this->findRow($data->productId, $from)->pluck('id')->all();

            return [
                'movement' => $movement->load(self::MOVEMENT_RELATIONS),
                'balances' => StockBalance::query()
                    ->with(self::BALANCE_RELATIONS)
                    ->whereKey([...$touched, ...$survivors])
                    ->get(),
            ];
        });
    }

    /**
     * @return array{0: StockSide|null, 1: StockSide|null, 2: string}
     */
    private function resolveSides(StockMovementData $data): array
    {
        $row = new StockSide($data->siteId, $data->userId);
        $quantity = $this->scaled((string) $data->quantity);

        return match ($data->type) {
            StockMovementType::Load => [null, new StockSide($data->siteId), $quantity],
            StockMovementType::Unload => [$row, null, $quantity],
            StockMovementType::Transfer => [$row, new StockSide((int) $data->toSiteId, $data->userId), $quantity],
            StockMovementType::Assign => [new StockSide($data->siteId), $row, $quantity],
            StockMovementType::Return => [$row, new StockSide($data->siteId), $quantity],
            StockMovementType::Adjustment => $this->resolveAdjustment($data, $row),
        };
    }

    /**
     * The client sends the counted quantity; the register records the difference.
     *
     * @return array{0: StockSide|null, 1: StockSide|null, 2: string}
     */
    private function resolveAdjustment(StockMovementData $data, StockSide $row): array
    {
        $current = $this->findRow($data->productId, $row)->value('quantity') ?? '0';
        $difference = bcsub($this->scaled((string) $data->countedQuantity), $this->scaled((string) $current), self::SCALE);

        if (bccomp($difference, '0', self::SCALE) === 0) {
            throw ValidationException::withMessages(['counted_quantity' => __('stock.adjustment_unchanged')]);
        }

        return bccomp($difference, '0', self::SCALE) > 0
            ? [null, $row, $difference]
            : [$row, null, ltrim($difference, '-')];
    }

    private function decrease(StockMovementData $data, StockSide $side, string $quantity): void
    {
        $balance = $this->findRow($data->productId, $side)->first();
        $available = $balance === null ? $this->scaled('0') : $this->scaled((string) $balance->quantity);

        if (bccomp($quantity, $available, self::SCALE) > 0) {
            throw ValidationException::withMessages(['quantity' => __('stock.insufficient', ['available' => $available])]);
        }

        $remaining = bcsub($available, $quantity, self::SCALE);

        if (bccomp($remaining, '0', self::SCALE) === 0) {
            $balance->delete();

            return;
        }

        $balance->forceFill(['quantity' => $remaining, 'last_moved_on' => $this->latest($balance, $data)])->save();
    }

    private function increase(StockMovementData $data, StockSide $side, string $quantity): StockBalance
    {
        $balance = $this->findRow($data->productId, $side)->first() ?? (new StockBalance)->forceFill([
            'product_id' => $data->productId,
            'operational_site_id' => $side->siteId,
            'user_id' => $side->userId,
            'quantity' => '0',
        ]);

        $balance->forceFill([
            'quantity' => bcadd($this->scaled((string) $balance->quantity), $quantity, self::SCALE),
            'last_moved_on' => $this->latest($balance, $data),
        ])->save();

        return $balance;
    }

    private function writeMovement(User $actor, StockMovementData $data, ?StockSide $from, ?StockSide $to, string $quantity): StockMovement
    {
        $movement = (new StockMovement)->forceFill([
            'type' => $data->type,
            'product_id' => $data->productId,
            'quantity' => $quantity,
            'from_operational_site_id' => $from?->siteId,
            'from_user_id' => $from?->userId,
            'to_operational_site_id' => $to?->siteId,
            'to_user_id' => $to?->userId,
            'moved_on' => $data->movedOn,
            'note' => $data->note,
            'created_by' => $actor->id,
        ]);
        $movement->save();

        return $movement;
    }

    /**
     * @return Builder<StockBalance>
     */
    private function findRow(int $productId, StockSide $side): Builder
    {
        return StockBalance::query()
            ->where('product_id', $productId)
            ->where('operational_site_id', $side->siteId)
            ->when(
                $side->userId === null,
                static fn (Builder $query) => $query->whereNull('user_id'),
                static fn (Builder $query) => $query->where('user_id', $side->userId),
            );
    }

    /** A back-dated movement never moves the last movement date backwards. */
    private function latest(StockBalance $balance, StockMovementData $data): string
    {
        $previous = $balance->exists ? $balance->last_moved_on->format('Y-m-d') : $data->movedOn;

        return max($previous, $data->movedOn);
    }

    private function scaled(string $quantity): string
    {
        return bcadd($quantity, '0', self::SCALE);
    }
}
