<?php

declare(strict_types=1);

namespace App\Actions\AiAssistant;

use App\Enums\AiMessageRole;
use App\Http\Resources\AiAssistant\AiConversationResource;
use App\Http\Resources\AiAssistant\AiMessageResource;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AiAssistant\AiPromptBuilder;
use App\Services\AiAssistant\Drivers\AiAssistantNotConfiguredException;
use App\Services\AiAssistant\Drivers\AiChatDriver;
use App\Services\AiAssistant\Drivers\AiChatDriverManager;
use App\Services\AiAssistant\Drivers\AiProviderException;
use App\Services\AiAssistant\Drivers\AiStreamEvent;
use App\Services\AiAssistant\Mentions\AiMentionNotFoundException;
use App\Services\AiAssistant\Mentions\AiMentionResolver;
use App\Services\AiAssistant\Mentions\ResolvedAiMention;
use App\Services\AiAssistant\Prompt\AiChatPrompt;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One chat turn (spec 0347): validates and persists the user message, then
 * streams the provider answer as SSE and stores it. Everything that can fail
 * for a client reason runs BEFORE the stream opens, so it answers with the
 * usual JSON envelope (D-8); the provider is never called in those cases.
 */
final class SendAiMessage
{
    public const string ATTACHMENT_COLLECTION = 'ai-assistant';

    public const int TITLE_MAX_CHARS = 60;

    /** Headroom over the provider timeout for persisting the answer. */
    private const int TIME_LIMIT_MARGIN_SECONDS = 30;

    public function __construct(
        private readonly AiChatDriverManager $drivers,
        private readonly AiMentionResolver $mentionResolver,
        private readonly AiPromptBuilder $promptBuilder,
    ) {}

    /**
     * @throws AiAssistantNotConfiguredException
     * @throws ValidationException unknown mentioned record (mentions.N.id)
     * @throws AuthorizationException mentioned record not visible to the user
     */
    public function handle(User $user, AiConversation $conversation, SendAiMessageData $data): StreamedResponse
    {
        // Step 1: the assistant must be configured, before any write
        $driver = $this->drivers->driver();

        // Step 2: resolve and authorize every mentioned record
        $mentions = $this->resolveMentions($user, $data->mentions);

        // Step 3: persist the user message and its files
        $userMessage = $this->persistUserMessage($conversation, $data, $mentions);

        // Step 4: build the provider prompt
        $prompt = $this->promptBuilder->build($conversation, $userMessage, $mentions, $data->pageGuideText, $data->locale);

        // Step 5: stream the answer
        return response()->eventStream(
            fn (): Generator => $this->events($driver, $prompt, $conversation, $userMessage),
            endStreamWith: null,
        );
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
    private function persistUserMessage(AiConversation $conversation, SendAiMessageData $data, array $mentions): AiMessage
    {
        /** @var list<Attachment> $stored */
        $stored = [];

        try {
            $message = DB::transaction(function () use ($conversation, $data, $mentions, &$stored): AiMessage {
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

                return $message;
            });
        } catch (Throwable $exception) {
            // The rollback removes the rows but not the binaries already written.
            foreach ($stored as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            throw $exception;
        }

        return $message->load('attachments');
    }

    /**
     * @return Generator<int, StreamedEvent>
     */
    private function events(AiChatDriver $driver, AiChatPrompt $prompt, AiConversation $conversation, AiMessage $userMessage): Generator
    {
        set_time_limit((int) config('ai-assistant.timeout_seconds') + self::TIME_LIMIT_MARGIN_SECONDS);

        $completed = false;

        try {
            yield new StreamedEvent('message.created', ['user_message' => $this->resolve(new AiMessageResource($userMessage))]);

            $text = '';
            $final = null;

            foreach ($driver->stream($prompt) as $event) {
                if ($event->type === AiStreamEvent::TYPE_DELTA) {
                    $text .= $event->text;

                    yield new StreamedEvent('delta', ['text' => $event->text]);
                } elseif ($event->type === AiStreamEvent::TYPE_COMPLETED) {
                    $final = $event;
                }
            }

            // A reasoning model can spend the whole output budget thinking and close with no text.
            if ($final === null || trim($text) === '') {
                throw new AiProviderException;
            }

            $assistantMessage = $this->persistAnswer($conversation, $userMessage, $text, $final);
            $completed = true;

            yield new StreamedEvent('message.completed', [
                'assistant_message' => $this->resolve(new AiMessageResource($assistantMessage)),
                'conversation' => $this->resolve(new AiConversationResource($conversation)),
            ]);
        } catch (Throwable $exception) {
            $this->discardUserMessage($userMessage);

            Log::warning('AI assistant turn failed', ['exception' => $exception::class, 'conversation_id' => $conversation->id]);

            yield new StreamedEvent('error', ['message' => AiProviderException::GENERIC_MESSAGE]);
        } finally {
            // Client disconnected before the answer was stored: no partial state (D-13).
            if (! $completed) {
                $this->discardUserMessage($userMessage);
            }
        }
    }

    private function persistAnswer(AiConversation $conversation, AiMessage $userMessage, string $text, AiStreamEvent $final): AiMessage
    {
        return DB::transaction(function () use ($conversation, $userMessage, $text, $final): AiMessage {
            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $text,
                'model' => $final->model ?? $this->drivers->model(),
                'input_tokens' => $final->inputTokens,
                'output_tokens' => $final->outputTokens,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'title' => $conversation->title !== '' ? $conversation->title : $this->titleFrom($userMessage->content),
            ]);

            return $assistantMessage->setRelation('attachments', collect());
        });
    }

    private function titleFrom(string $content): string
    {
        return mb_substr(Str::squish($content), 0, self::TITLE_MAX_CHARS);
    }

    /**
     * Idempotent: deleting through Eloquent removes the attachment rows and
     * their files (HasAttachments), a second call finds nothing to delete.
     */
    private function discardUserMessage(AiMessage $userMessage): void
    {
        if (AiMessage::query()->whereKey($userMessage->id)->exists()) {
            $userMessage->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(JsonResource $resource): array
    {
        return $resource->resolve(request());
    }
}
