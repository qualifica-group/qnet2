<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use Illuminate\Database\Eloquent\Builder;

/**
 * The "every word starts a word" quick-search semantics of spec 0179, shared
 * by RequestClientSearch and RegistrySearch (spec 0211): read through the
 * FULLTEXT indexes on MySQL/MariaDB, emulated with a word-start LIKE on
 * SQLite (spec 0179 D-4).
 *
 * SECURITY: words contain only letters and digits, so no boolean-mode
 * operator typed by the user reaches MATCH; the value is always bound.
 */
final class WordPrefixMatcher
{
    /** Mirrors InnoDB's default `innodb_ft_min_token_size`: shorter words are not indexed (spec 0179 D-2). */
    public const int MIN_WORD_LENGTH = 3;

    /**
     * InnoDB's default stopwords of 3+ characters: with `+` a stopword empties
     * the whole match, so they are dropped from the term (spec 0179 D-2).
     *
     * @var array<int, string>
     */
    private const array STOPWORDS = [
        'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this',
        'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www',
    ];

    /** Characters after which a word starts, for the SQLite emulation. */
    private const array WORD_DELIMITERS = [' ', '.', '@', '-', "'"];

    private const array FULLTEXT_DRIVERS = ['mysql', 'mariadb'];

    /**
     * The searchable words of `$term`, lowercased and deduplicated.
     *
     * @return array<int, string>
     */
    public function words(string $term): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH
                && ! in_array($word, self::STOPWORDS, true),
        )));
    }

    /**
     * Every word must start a word of one of `$columns` (together). On
     * MySQL/MariaDB `$columns` must be exactly the column list of a FULLTEXT index.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $words
     */
    public function whereWordsStart(Builder $query, array $columns, array $words): void
    {
        if (in_array($query->getConnection()->getDriverName(), self::FULLTEXT_DRIVERS, true)) {
            $booleanTerm = implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $words));
            $query->whereFullText($columns, $booleanTerm, ['mode' => 'boolean']);

            return;
        }

        foreach ($words as $word) {
            $query->where(static function (Builder $wordQuery) use ($columns, $word): void {
                foreach ($columns as $column) {
                    $wordQuery->orWhere($column, 'like', $word.'%');

                    foreach (self::WORD_DELIMITERS as $delimiter) {
                        $wordQuery->orWhere($column, 'like', '%'.$delimiter.$word.'%');
                    }
                }
            });
        }
    }
}
