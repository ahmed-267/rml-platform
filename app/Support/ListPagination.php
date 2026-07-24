<?php

namespace App\Support;

use Illuminate\Http\Request;

class ListPagination
{
    /**
     * @param  list<int>  $allowed
     */
    public static function perPage(
        Request $request,
        int $default = 10,
        array $allowed = [10, 25, 50, 100],
    ): int {
        $perPage = (int) $request->input('per_page', $default);

        if (! in_array($perPage, $allowed, true)) {
            return $default;
        }

        return $perPage;
    }
}
