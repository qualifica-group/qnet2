<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialCardCircuit;
use App\Enums\FinancialCardType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Financial account (spec 0189): a bank account, a card or a cash box, in one
 * table discriminated by `type` (D-7). `card_number` is encrypted at rest, hidden
 * from serialization and excluded from the activity log; the CVV/PIN are never
 * stored (D-1). `card_last_four` is derived by FinancialAccountService.
 */
#[Fillable([
    'type', 'name', 'company_id', 'iban', 'account_number', 'address_line', 'postal_code',
    'country_id', 'state_id', 'province_id', 'city_id', 'card_type', 'card_circuit',
    'linked_account_id', 'card_holder', 'card_number', 'card_last_four', 'card_expiry', 'notes',
])]
#[Hidden(['card_number'])]
class FinancialAccount extends BaseModel
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'card_type' => FinancialCardType::class,
            'card_circuit' => FinancialCardCircuit::class,
            'card_number' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The bank account a card is associated with.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_account_id');
    }

    /**
     * The cards associated with this bank account (the delete guard's set).
     *
     * @return HasMany<FinancialAccount, $this>
     */
    public function linkedCards(): HasMany
    {
        return $this->hasMany(self::class, 'linked_account_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
