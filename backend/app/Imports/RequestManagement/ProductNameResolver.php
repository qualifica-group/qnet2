<?php

namespace App\Imports\RequestManagement;

/**
 * Matches the free text of the file's "Prodotto" column to ONE product of the
 * chosen category (spec 0348, AC-008). The text is first reduced to its
 * meaningful words: stopwords, the words naming the category and its ancestors
 * ("dil", "lombardia"), noise words ("corso", "milano"), years and punctuation
 * are dropped. Then, in order, the first rule that finds exactly one product wins:
 *   1. the whole text equals the product name;
 *   2. the whole text equals the acronym before " - " ("OAC");
 *   3. every remaining word of the text is found among the words of the product
 *      name, a word matching when it is equal, a prefix of at least
 *      `product_min_prefix` letters ("Agg" for "Aggiornamento") or a near-miss
 *      typo ("segretaria" for "segreteria");
 *   4. an acronym of a product is one of the text's words.
 * Several candidates for a rule resolve to nothing: guessing which product was
 * sold is worse than leaving the line out (the text goes to the notes).
 */
final class ProductNameResolver
{
    private const string ACRONYM_SEPARATOR = ' - ';

    private const string YEAR_PATTERN = '/^(19|20)\d{2}$/';

    public function __construct(private readonly RequestImportLookups $lookups) {}

    public function resolve(int $categoryId, string $text): ?int
    {
        $products = $this->lookups->productsOf($categoryId);

        return $this->byExactName($products, $text)
            ?? $this->byAcronym($products, $text)
            ?? $this->byWords($products, $this->meaningfulWords($categoryId, $text));
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulWords(int $categoryId, string $text): array
    {
        $ignored = [
            ...(array) config('imports.request_management.product_stopwords'),
            ...(array) config('imports.request_management.product_noise_words'),
            ...$this->lookups->categoryWords($categoryId),
        ];

        return array_values(array_filter(
            RequestImportText::words($text),
            static fn (string $word): bool => ! in_array($word, $ignored, true) && preg_match(self::YEAR_PATTERN, $word) !== 1,
        ));
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $products
     */
    private function byExactName(array $products, string $text): ?int
    {
        $key = RequestImportText::key($text);

        return $this->only(array_filter(
            $products,
            static fn (array $product): bool => $key !== '' && RequestImportText::key($product['name']) === $key,
        ));
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $products
     */
    private function byAcronym(array $products, string $text): ?int
    {
        $key = RequestImportText::key($text);

        return $this->only(array_filter(
            $products,
            fn (array $product): bool => $key !== '' && $this->acronym($product['name']) === $key,
        ));
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $products
     * @param  array<int, string>  $fileWords
     */
    private function byWords(array $products, array $fileWords): ?int
    {
        if ($fileWords === []) {
            return null;
        }

        $byContainment = array_filter($products, function (array $product) use ($fileWords): bool {
            $productWords = RequestImportText::words($product['name']);

            return array_all($fileWords, fn (string $word): bool => array_any(
                $productWords,
                fn (string $productWord): bool => $this->wordMatches($productWord, $word),
            ));
        });

        return $this->only($byContainment) ?? ($byContainment !== [] ? null : $this->only(array_filter(
            $products,
            function (array $product) use ($fileWords): bool {
                $acronym = $this->acronym($product['name']);

                return $acronym !== null && in_array($acronym, $fileWords, true);
            },
        )));
    }

    /**
     * The part of the name before " - ", when the name has one.
     */
    private function acronym(string $name): ?string
    {
        return str_contains($name, self::ACRONYM_SEPARATOR)
            ? RequestImportText::key(explode(self::ACRONYM_SEPARATOR, $name, 2)[0])
            : null;
    }

    private function wordMatches(string $productWord, string $fileWord): bool
    {
        if ($productWord === $fileWord) {
            return true;
        }

        if (strlen($fileWord) >= (int) config('imports.request_management.product_min_prefix') && str_starts_with($productWord, $fileWord)) {
            return true;
        }

        // One typo per started five letters of a word long enough to survive it.
        $tolerance = intdiv(strlen($productWord), 5);

        return $tolerance > 0 && levenshtein($productWord, $fileWord) <= $tolerance;
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $matches
     */
    private function only(array $matches): ?int
    {
        return count($matches) === 1 ? array_values($matches)[0]['id'] : null;
    }
}
