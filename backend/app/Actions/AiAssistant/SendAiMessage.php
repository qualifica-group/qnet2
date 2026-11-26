<?php

declare(strict_types=1);

namespace App\Actions\AiAssistant;

use App\Enums\AiMessageRole;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiConversation;
use App\Models\AiTurn;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AiAssistant\Agent\AiAssistantNotConfiguredException;
use App\Services\AiAssistant\Agent\AiAssistantSwitchedOffException;
use App\Services\AiAssistant\Agent\AiTurnAlreadyActiveException;
use App\Services\AiAssistant\Agent\ConnectorClient;
use App\Services\AiAssistant\Agent\OpenRouterAgentClient;
use App\Services\AiAssistant\Mentions\AiMentionNotFoundException;
use App\Services\AiAssistant\Mentions\AiMentionResolver;
use App\Services\AiAssistant\Mentions\ResolvedAiMention;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Starts one assistant turn (spec 0433, D-8, D-16): validates and saves the person's message
 * with its turn in one transaction, then queues the turn. Everything that can fail for a client
 * reason happens BEFORE any row is written. The message stays whatever happens to the turn.
 */
final class SendAiMessage
{
    public const string ATTACHMENT_COLLECTION = 'ai-assistant';

    public const int TITLE_MAX_CHARS = 60;

    public function __construct(
        private readonly AiMentionResolver $mentionResolver,
        private readonly AiAssistantSettingsService $settings,
    ) {}

    /**
     * @throws AiAssistantSwitchedOffException
     * @throws AiAssistantNotConfiguredException
     * @throws AiTurnAlreadyActiveException
     * @throws ValidationException unknown mentioned record (mentions.N.id)
     * @throws AuthorizationException mentioned record not visible to the user
     */
    public function handle(User $user, ?PersonalAccessToken $session, AiConversation $conversation, SendAiMessageData $data): AiTurn
    {
        // Step 1: the assistant must be on and configured, before any write
        if (! $this->settings->effective()->enabled) {
            throw new AiAssistantSwitchedOffException;
        }

        if (! OpenRouterAgentClient::isConfigured() || ! ConnectorClient::isConfigured()) {
            throw new AiAssistantNotConfiguredException;
        }

        // Step 2: resolve and authorize every mentioned record
        $mentions = $this->resolveMentions($user, $data->mentions);

        // Step 3: message, files and turn, one active turn per conversation
        $turn = $this->persist($conversation, $session, $data, $mentions);

        // Step 4: the turn runs in the queue
        RunAiTurn::dispatch($turn->id);

        return $turn;
    }

    /**
     * @param  list<array{module: string, id: int}>  $mentions
     * @return list<ResolvedAiMention>
     */
    private function resolveMentions(User $user, array $mentions): array
    {
        $resolved = [];

        foreach ($mentions as $index => $mention) {
            try {
                $resolved[] = $this->mentionResolver->resolve($user, $mention['module'], $mention['id']);
            } catch (AiMentionNotFoundException) {
                throw ValidationException::withMessages(["mentions.{$index}.id" => __('The mentioned record does not exist.')]);
            }
        }

        return $resolved;
    }

    /**
     * @param  list<ResolvedAiMention>  $mentions
     */
    private function persist(AiConversation $conversation, ?PersonalAccessToken $session, SendAiMessageData $data, array $mentions): AiTurn
    {
        /** @var list<Attachment> $stored */
        $stored = [];

        try {
            return DB::transaction(function () use ($conversation, $session, $data, $mentions, &$stored): AiTurn {
                // The conversation row is the lock that makes "one active turn" hold under concurrent requests.
                AiConversation::query()->whereKey($conversation->id)->lockForUpdate()->first();

                if ($conversation->turns()->whereIn('status', AiTurnStatus::activeValues())->exists()) {
                    throw new AiTurnAlreadyActiveException;
                }

                $message = $conversation->messages()->create([
                    'role' => AiMessageRole::User,
                    'content' => $data->content,
                    'mentions' => array_map(
                        static fn (ResolvedAiMention $mention): array => ['module' => $mention->module, 'id' => $mention->id, 'label' => $mention->label],
                        $mentions,
                    ),
                    'page_guide_key' => $data->pageGuideKey,
                ]);

                foreach ($data->files as $file) {
                    $stored[] = $message->attach($file, self::ATTACHMENT_COLLECTION);
                }

                $conversation->update([
                    'last_message_at' => now(),
                    'title' => $conversation->title !== '' ? $conversation->title : $this->titleFrom($data->content),
                ]);

                return $conversation->turns()->create([
                    'user_message_id' => $message->id,
                    'personal_access_token_id' => $session?->getKey(),
                    'status' => AiTurnStatus::Queued,
                    'locale' => $data->locale,
                    'page_guide_text' => $data->pageGuideText,
                ]);
            });
        } catch (Throwable $exception) {
            // The rollback removes the rows but not the binaries already written.
            foreach ($stored as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            throw $exception;
        }
    }

    private function titleFrom(string $content): string
    {
        return mb_substr(Str::squish($content), 0, self::TITLE_MAX_CHARS);
    }
}
