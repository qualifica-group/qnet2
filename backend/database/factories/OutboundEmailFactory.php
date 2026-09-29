<?php

namespace Database\Factories;

use App\Enums\OutboundEmailStatus;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Defaults to a draft owned by a fresh WorkOrder/User pair; override the
 * owner with forEmailable(), the state with the draft()/queued()/sent()/
 * failed() helpers below.
 *
 * @extends Factory<OutboundEmail>
 */
class OutboundEmailFactory extends Factory
{
    protected $model = OutboundEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emailable_type' => (new WorkOrder)->getMorphClass(),
            'emailable_id' => WorkOrder::factory(),
            'email_template_id' => null,
            'status' => OutboundEmailStatus::Draft,
            'sender_user_id' => User::factory(),
            'from_address' => null,
            'to_recipients' => [fake()->safeEmail()],
            'cc_recipients' => [],
            'bcc_recipients' => [],
            'subject' => fake()->sentence(4),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'queued_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * Attach the email to an existing owner, replacing the default WorkOrder.
     */
    public function forEmailable(Model $emailable): static
    {
        return $this->state(fn (): array => [
            'emailable_type' => $emailable->getMorphClass(),
            'emailable_id' => $emailable->getKey(),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => OutboundEmailStatus::Queued,
            'from_address' => fake()->safeEmail(),
            'queued_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => OutboundEmailStatus::Sent,
            'from_address' => fake()->safeEmail(),
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => OutboundEmailStatus::Failed,
            'from_address' => fake()->safeEmail(),
            'queued_at' => now()->subMinute(),
            'failed_at' => now(),
            'error_message' => 'Graph mailbox rejected the sender.',
        ]);
    }
}
