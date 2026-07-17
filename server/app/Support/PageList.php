<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;

class PageList
{
    public const PER_PAGE = 10;

    /** @param Collection<int, mixed> $items */
    public static function paginateCollection(
        Collection $items,
        Request $request,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $page = max(1, (int) $request->input($pageName, 1));
        $total = $items->count();
        $slice = $items->forPage($page, self::PER_PAGE)->values();

        return new PaginatorImpl(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            [
                'path'     => $request->url(),
                'pageName' => $pageName,
                'query'    => $request->query(),
            ],
        );
    }
}
