<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;

class PageList
{
    public const PER_PAGE = 10;

    public static function perPage(): int
    {
        return self::PER_PAGE;
    }

    /** @param Builder<\Illuminate\Database\Eloquent\Model> $query */
    public static function paginateQuery(Builder $query, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        return $query
            ->paginate(self::PER_PAGE, ['*'], $pageName)
            ->withQueryString();
    }

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
