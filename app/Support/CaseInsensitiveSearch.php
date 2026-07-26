<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class CaseInsensitiveSearch
{
    /**
     * Operator that is case-insensitive on PostgreSQL (ilike) and falls back to like elsewhere.
     */
    public static function operator(): string
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        return $connection === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     * @param  list<string>  $columns  qualified or bare column names
     */
    public static function whereAny(
        EloquentBuilder|QueryBuilder $query,
        array $columns,
        string $term,
    ): void {
        $pattern = '%'.addcslashes($term, '%_\\').'%';
        $op = self::operator();

        $query->where(function ($inner) use ($columns, $pattern, $op) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $inner->where($column, $op, $pattern);
                } else {
                    $inner->orWhere($column, $op, $pattern);
                }
            }
        });
    }
}
