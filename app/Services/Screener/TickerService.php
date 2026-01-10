<?php

namespace App\Services\Screener;

use App\Repositories\Screener\TickerRepository;

class TickerService
{
    private $repo;

    public function __construct(TickerRepository $repo)
    {
        $this->repo = $repo;
    }

    public function paginateLatestOhlc(array $opt): array
    {
        $page   = (int) ($opt['page'] ?? 1);
        $size   = (int) ($opt['size'] ?? 25);
        $search = (string) ($opt['search'] ?? '');
        $sort   = (string) ($opt['sort'] ?? 'ticker_code');
        $dir    = (string) ($opt['dir'] ?? 'asc');

        $total = $this->repo->countTickers($search);
        $rows  = $this->repo->getTickersLatestOhlc($page, $size, $search, $sort, $dir);

        $lastPage = (int) max(1, (int) ceil($total / max(1, $size)));

        return [
            'ok'        => true,
            'data'      => $rows,
            'page'      => $page,
            'size'      => $size,
            'total'     => $total,
            'last_page' => $lastPage,
        ];
    }
}
