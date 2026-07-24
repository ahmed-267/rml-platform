<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ListSort
{
    /**
     * Apply a validated sort to a query.
     *
     * @param  array<string, string|Closure(Builder, string): void>  $columns
     * @return array{sort: string, direction: string}
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $columns,
        string $defaultSort,
        string $defaultDirection = 'desc',
        string $sortParam = 'sort',
        string $directionParam = 'direction',
    ): array {
        $sort = $request->string($sortParam)->toString();
        if ($sort === '' || ! array_key_exists($sort, $columns)) {
            $sort = array_key_exists($defaultSort, $columns)
                ? $defaultSort
                : (string) array_key_first($columns);
        }

        $direction = $request->filled($directionParam)
            ? ($request->input($directionParam) === 'asc' ? 'asc' : 'desc')
            : ($defaultDirection === 'asc' ? 'asc' : 'desc');

        $column = $columns[$sort];

        if ($column instanceof Closure) {
            $column($query, $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }
}
