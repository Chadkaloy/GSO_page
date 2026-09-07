<?php

namespace App\Traits;

trait HasRelevanceSearch
{
    /**
     * Adds an ORDER BY that ranks rows by closeness to $search across the
     * given columns: 0 = exact match on any column, 1 = starts-with match,
     * 2 = contains match, everything else last. Ordered ascending, so the
     * closest match rises to the top of the table. This only adds ordering —
     * it does NOT filter rows; pair it with your existing WHERE/LIKE filter
     * (or use applyRelevanceSearch() below to get both in one call).
     *
     * Any ->orderBy() you already have after this call still works — it
     * just becomes a secondary tiebreaker for rows with equal relevance.
     */
    protected function applyRelevanceOrder($query, string $search, array $columns)
    {
        $search = trim($search);
        if ($search === '' || empty($columns)) {
            return $query;
        }

        $likeSearch = '%' . $search . '%';
        $startsWithSearch = $search . '%';

        $caseParts = [];
        $bindings = [];

        foreach ($columns as $column) {
            $quotedColumn = "`{$column}`";
            $caseParts[] = "WHEN {$quotedColumn} = ? THEN 0";
            $bindings[] = $search;
            $caseParts[] = "WHEN {$quotedColumn} LIKE ? THEN 1";
            $bindings[] = $startsWithSearch;
            $caseParts[] = "WHEN {$quotedColumn} LIKE ? THEN 2";
            $bindings[] = $likeSearch;
        }

        $query->orderByRaw('CASE ' . implode(' ', $caseParts) . ' ELSE 3 END', $bindings);

        return $query;
    }

    /**
     * Drop-in replacement for the usual:
     *   $query->where(function ($q) use ($search) {
     *       $q->where('col1', 'LIKE', "%{$search}%")->orWhere('col2', ...);
     *   });
     *
     * Filters rows exactly the same way (any column contains the term), but
     * also ranks the results by relevance via applyRelevanceOrder() above.
     */
    protected function applyRelevanceSearch($query, string $search, array $columns)
    {
        $search = trim($search);
        if ($search === '' || empty($columns)) {
            return $query;
        }

        $likeSearch = '%' . $search . '%';

        $query->where(function ($q) use ($columns, $likeSearch) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', $likeSearch);
            }
        });

        return $this->applyRelevanceOrder($query, $search, $columns);
    }
}