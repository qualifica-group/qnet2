<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Users\UserOnboardingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * One-shot welcome email to every active, non-service user (spec 0369): each
 * gets a new generated password, must change it at the next access and loses
 * the open sessions, exactly like the resend from the user sheet. Idempotent:
 * whoever already has `welcome_sent_at` is skipped, so a re-run (or a run
 * resumed after a crash) never emails the same user twice.
 */
class SendWelcomeToActiveUsers extends Command
{
    private const int CHUNK_SIZE = 100;

    protected $signature = 'users:send-welcome
        {--dry-run : Report how many users would receive the email, without writing or sending}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Send the welcome email (new generated password) to every active user who has not received it yet';

    public function handle(UserOnboardingService $onboarding): int
    {
        // Step 1: who still has to receive the welcome email
        $pending = $this->pendingUsers()->count();

        if ($this->option('dry-run')) {
            $this->info("{$pending} utenti riceveranno l'email di benvenuto.");

            return self::SUCCESS;
        }

        // Step 2: explicit confirmation, every recipient's password is replaced
        if ($pending > 0 && ! $this->option('force') && ! $this->confirm(
            "{$pending} utenti riceveranno una nuova password via email e verranno disconnessi. Procedere?"
        )) {
            $this->warn('Operazione annullata: nessun utente modificato.');

            return self::SUCCESS;
        }

        // Step 3: send, in chunks (each user is marked inside reissueWelcome)
        $sent = 0;

        $this->pendingUsers()->chunkById(self::CHUNK_SIZE, function ($users) use ($onboarding, &$sent): void {
            foreach ($users as $user) {
                $onboarding->reissueWelcome($user);
                $sent++;
            }
        });

        $this->info("Email di benvenuto inviata a {$sent} utenti.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<User>
     */
    private function pendingUsers(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->where('is_service_account', false)
            ->whereNull('welcome_sent_at');
    }
}
