<?php

namespace App\Imports\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * The single-level matching mechanics behind GeoResolver::resolveFuzzy()
 * (spec 0033 AC-005): exact, case-insensitive match first (like
 * GeoResolver::findByName(), but distinguishing NOT-FOUND from AMBIGUOUS so
 * both can carry candidates); a level with ZERO exact matches falls back to
 * a similarity score (similar_text()) against every row in the given scope.
 * Extracted out of GeoResolver purely to keep that class within the file
 * size guideline — GeoResolver still owns the hierarchy walk (country ->
 * state -> province -> city) and calls this once per level.
 *
 * The comparison that DECIDES is always the PHP one (mb_strtolower /
 * similar_text), identical on every database collation. What the database
 * does is only limit how much has to be read: a row whose city column has no
 * country/region/province to scope it searches the whole `cities` table
 * (156k rows in the production world dataset), so nothing here may hydrate
 * that scope into models — the exact pass narrows it to an equality the
 * index can serve, and the fuzzy fallback streams id/name tuples instead.
 * Hydrating the scope per staged row is what exhausted PHP's memory limit in
 * production and left the import run stuck in `staging`.
 */
final class GeoFuzzyMatcher
{
    /** Minimum similar_text() percent (0-100) for a candidate to be accepted. */
    private const int THRESHOLD_PERCENT = 82;

    /** Max candidates surfaced per ambiguous level. */
    private const int CANDIDATE_LIMIT = 5;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  ?int  $preferCountryId  Home country used to break a cross-country
     *                                 homonym tie (see homeCountryWinner()); null
     *                                 disables the tiebreak.
     * @return array{model: ?TModel, candidates: array<int, array{id: int, name: string}>}
     */
    public function match(Builder $query, string $name, ?int $preferCountryId = null): array
    {
        $target = $this->normalize($name);

        // Step 1: exact hit, the path a well-formed name always takes.
        $exact = $this->exactCandidates($query, $target, $preferCountryId);

        if ($exact->isNotEmpty()) {
            return $this->fromExact($query, $exact, $preferCountryId);
        }

        // Step 2: nothing matched exactly -> score the whole scope, streamed.
        return $this->fromFuzzy($query, $target, $preferCountryId);
    }

    /**
     * The exact-only half of match(), for callers that have no fuzzy
     * fallback (GeoResolver::findByName()): the single row whose name equals
     * $name within the scope, or null both when NOT FOUND and when AMBIGUOUS.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    public function exactOne(Builder $query, string $name): ?Model
    {
        $target = $this->normalize($name);
        $candidates = $this->exactCandidates($query, $target, null);

        // A collation stricter than mb_strtolower (SQLite lowercases ASCII
        // only) can return nothing here for a name that PHP would match, so
        // an empty narrowed set still has to be confirmed against the scope.
        if ($candidates->isEmpty()) {
            $candidates = $this->scan($query, null)
                ->filter(fn (object $row): bool => $this->normalize((string) $row->name) === $target)
                ->values();
        }

        return $candidates->count() === 1 ? $this->hydrate($query, (int) $candidates->first()->id) : null;
    }

    /**
     * Rows equal to the target, read as id/name tuples. SQL narrows with a
     * wildcard-free `like` — an equality the index serves, under a collation
     * that may match MORE (case/accent-insensitive) but never wrongly, since
     * the PHP pass filters the result back down to a true equality.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, object>
     */
    private function exactCandidates(Builder $query, string $target, ?int $preferCountryId): Collection
    {
        $narrowed = $query->clone()->where(
            $query->getModel()->qualifyColumn('name'),
            'like',
            addcslashes($target, '%_\\'),
        );

        return $this->leanQuery($narrowed, $preferCountryId)
            ->get()
            ->filter(fn (object $row): bool => $this->normalize((string) $row->name) === $target)
            ->values();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, object>  $exact
     * @return array{model: ?TModel, candidates: array<int, array{id: int, name: string}>}
     */
    private function fromExact(Builder $query, Collection $exact, ?int $preferCountryId): array
    {
        if ($exact->count() === 1) {
            return ['model' => $this->hydrate($query, (int) $exact->first()->id), 'candidates' => []];
        }

        $winner = $this->homeCountryWinner($exact, $preferCountryId);

        return $winner !== null
            ? ['model' => $this->hydrate($query, (int) $winner->id), 'candidates' => []]
            : ['model' => null, 'candidates' => $this->toCandidates($exact)];
    }

    /**
     * Scores every row in scope and keeps only what an answer needs: the
     * accepted set in full (the home-country tiebreak has to see all of it)
     * and the closest few overall, for the suggestion list.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{model: ?TModel, candidates: array<int, array{id: int, name: string}>}
     */
    private function fromFuzzy(Builder $query, string $target, ?int $preferCountryId): array
    {
        $accepted = [];
        $closest = [];

        foreach ($this->scan($query, $preferCountryId) as $row) {
            $score = $this->similarity($target, (string) $row->name);

            if ($score >= self::THRESHOLD_PERCENT) {
                $accepted[] = ['row' => $row, 'score' => $score];
            }

            $closest = $this->keepClosest($closest, ['row' => $row, 'score' => $score]);
        }

        $accepted = $this->byScoreDesc($accepted);

        if (count($accepted) === 1) {
            return ['model' => $this->hydrate($query, (int) $accepted[0]['row']->id), 'candidates' => []];
        }

        if (count($accepted) > 1) {
            $winner = $this->homeCountryWinner(collect($accepted)->pluck('row'), $preferCountryId);

            if ($winner !== null) {
                return ['model' => $this->hydrate($query, (int) $winner->id), 'candidates' => []];
            }

            return ['model' => null, 'candidates' => $this->toCandidates(
                collect($accepted)->take(self::CANDIDATE_LIMIT)->pluck('row')
            )];
        }

        // Nothing crossed the threshold: surface the closest few as
        // suggestions rather than an empty candidate list.
        return ['model' => null, 'candidates' => $this->toCandidates(collect($this->byScoreDesc($closest))->pluck('row'))];
    }

    /**
     * @param  array<int, array{row: object, score: float}>  $closest
     * @param  array{row: object, score: float}  $entry
     * @return array<int, array{row: object, score: float}>
     */
    private function keepClosest(array $closest, array $entry): array
    {
        $closest[] = $entry;

        if (count($closest) <= self::CANDIDATE_LIMIT) {
            return $closest;
        }

        return array_slice($this->byScoreDesc($closest), 0, self::CANDIDATE_LIMIT);
    }

    /**
     * @param  array<int, array{row: object, score: float}>  $entries
     * @return array<int, array{row: object, score: float}>
     */
    private function byScoreDesc(array $entries): array
    {
        // PHP's sort is stable, so equal scores keep the database's order —
        // the same tie-break the previous sortByDesc() had.
        usort($entries, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $entries;
    }

    /**
     * The whole scope as a lazily read stream of id/name tuples: constant
     * memory whatever the level's size.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LazyCollection<int, object>
     */
    private function scan(Builder $query, ?int $preferCountryId)
    {
        return $this->leanQuery($query, $preferCountryId)->cursor();
    }

    /**
     * The scope reduced to the columns matching actually reads. `country_id`
     * is selected only when the tiebreak can fire, so the Country level —
     * which has no such column — never asks for it.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function leanQuery(Builder $query, ?int $preferCountryId): QueryBuilder
    {
        $model = $query->getModel();
        $columns = [$model->qualifyColumn('id'), $model->qualifyColumn('name')];

        if ($preferCountryId !== null) {
            $columns[] = $model->qualifyColumn('country_id');
        }

        return $query->clone()->toBase()->select($columns);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function hydrate(Builder $query, int $id): ?Model
    {
        return $query->clone()->whereKey($id)->first();
    }

    /**
     * Home-country tiebreak: an ambiguous set spanning several countries (a
     * bare homonym like "Rome", matching Italy AND the US) collapses to the
     * single candidate that belongs to the configured home country, when
     * exactly one does. Rows without a `country_id` (the Country level itself)
     * never match, so this is a no-op there; more than one home-country
     * candidate stays ambiguous, since the tie is real.
     *
     * @param  Collection<int, object>  $tied
     */
    private function homeCountryWinner(Collection $tied, ?int $preferCountryId): ?object
    {
        if ($preferCountryId === null) {
            return null;
        }

        $inHome = $tied->filter(
            static fn (object $row): bool => (int) ($row->country_id ?? 0) === $preferCountryId
        );

        return $inHome->count() === 1 ? $inHome->first() : null;
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function similarity(string $normalizedTarget, string $candidateName): float
    {
        similar_text($normalizedTarget, $this->normalize($candidateName), $percent);

        return $percent;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{id: int, name: string}>
     */
    private function toCandidates(Collection $rows): array
    {
        return $rows
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->values()
            ->all();
    }
}
