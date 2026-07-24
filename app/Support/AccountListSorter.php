<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountListSorter
{
    /**
     * @param  list<string>  $allowed
     * @return array{sort: string, direction: string}
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $allowed,
        string $context,
        string $defaultSort = 'date',
        string $defaultDirection = 'desc',
    ): array {
        $sort = $request->string('sort')->toString();
        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            $sort = $defaultSort;
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        match ($context) {
            'sellers' => self::applySellerSort($query, $sort, $direction),
            'buyers' => self::applyBuyerSort($query, $sort, $direction),
            'users' => self::applyUserSort($query, $sort, $direction),
            default => $query->orderBy('users.created_at', $direction),
        };

        if ($sort !== 'date') {
            $query->orderByDesc('users.created_at');
        }

        $query->orderBy('users.id', $direction === 'asc' ? 'asc' : 'desc');

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private static function applySellerSort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'name' => $query->orderBy('users.name', $direction),
            'email' => $query->orderBy('users.email', $direction),
            'status' => $query->orderBy('users.approval_status', $direction),
            'date' => $query->orderBy('users.created_at', $direction),
            'leads_submitted' => $query->orderBy('leads_submitted_count', $direction),
            'leads_sold' => $query->orderBy('leads_sold_count', $direction),
            'company' => $query->orderBy(self::sellerCompanyNameSubquery(), $direction),
            'role' => $query->orderBy(self::roleNameSubquery(), $direction),
            default => $query->orderBy('users.created_at', $direction),
        };
    }

    private static function applyBuyerSort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'name' => $query->orderBy('users.name', $direction),
            'email' => $query->orderBy('users.email', $direction),
            'status' => $query->orderBy('users.approval_status', $direction),
            'date' => $query->orderBy('users.created_at', $direction),
            'purchases' => $query->orderBy('purchases_count', $direction),
            'company' => $query->orderBy(self::buyerCompanyNameSubquery(), $direction),
            default => $query->orderBy('users.created_at', $direction),
        };
    }

    private static function applyUserSort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'name' => $query->orderBy('users.name', $direction),
            'email' => $query->orderBy('users.email', $direction),
            'status' => $query->orderBy('users.approval_status', $direction),
            'date' => $query->orderBy('users.created_at', $direction),
            'role' => $query->orderBy(self::roleNameSubquery(), $direction),
            'company' => $query->orderByRaw(
                'COALESCE(('
                .'select companies.name from companies '
                .'inner join seller_profiles on seller_profiles.company_id = companies.id '
                .'where seller_profiles.user_id = users.id limit 1'
                .'), ('
                .'select companies.name from companies '
                .'inner join buyer_profiles on buyer_profiles.company_id = companies.id '
                .'where buyer_profiles.user_id = users.id limit 1'
                ."), '') ".$direction
            ),
            default => $query->orderBy('users.created_at', $direction),
        };
    }

    private static function sellerCompanyNameSubquery(): QueryBuilder
    {
        return DB::table('companies')
            ->select('companies.name')
            ->join('seller_profiles', 'seller_profiles.company_id', '=', 'companies.id')
            ->whereColumn('seller_profiles.user_id', 'users.id')
            ->limit(1);
    }

    private static function buyerCompanyNameSubquery(): QueryBuilder
    {
        return DB::table('companies')
            ->select('companies.name')
            ->join('buyer_profiles', 'buyer_profiles.company_id', '=', 'companies.id')
            ->whereColumn('buyer_profiles.user_id', 'users.id')
            ->limit(1);
    }

    private static function roleNameSubquery(): QueryBuilder
    {
        return DB::table('model_has_roles')
            ->select('roles.name')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereColumn('model_has_roles.model_id', 'users.id')
            ->where('model_has_roles.model_type', User::class)
            ->limit(1);
    }
}
