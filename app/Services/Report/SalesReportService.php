<?php

namespace App\Services\Report;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityLog;
use App\Models\User;
use App\Support\Periods;
use App\Support\RoleChecks;
use Illuminate\Support\Collection;

class SalesReportService
{
    use Periods, RoleChecks;

    public function activity(array $filters, ?User $actor = null): array
    {
        $filters = $this->filtersFor($filters, $actor);

        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            1
        );

        $logs = OpportunityLog::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->when($filters['sales_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->get(['id', 'user_id', 'field_name', 'description', 'created_at']);

        $rows = $logs->groupBy('user_id')
            ->map(function (Collection $items) {
                $created = $items->filter(fn ($log) => $log->description === 'Opportunity created')->count();
                $statusUpdated = $items->filter(fn ($log) => $log->field_name === 'status')->count();
                $updated = $items->filter(
                    fn ($log) => $log->field_name !== null
                        && $log->field_name !== 'status'
                        && $log->description === null
                )->count();

                $user = $items->first()->user;

                return [
                    'user' => [
                        'id' => $user?->id,
                        'name' => $user?->name,
                    ],
                    'created' => $created,
                    'updated' => $updated,
                    'status_updated' => $statusUpdated,
                    'total' => $items->count(),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $rows,
        ];
    }

    public function performance(array $filters, ?User $actor = null): array
    {
        $filters = $this->filtersFor($filters, $actor);

        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            1
        );

        $grouped = $this->periodOpportunities($filters, $start, $end)->groupBy('user_id');

        $users = User::query()
            ->whereIn('id', $grouped->keys())
            ->when(
                $filters['sales_id'] ?? null,
                fn ($query, $id) => $query->orWhere('id', $id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $rows = $users->map(function (User $user) use ($grouped) {
            $summary = $this->summarize($grouped->get($user->id, collect()));

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ] + $summary;
        })->values();

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $rows,
        ];
    }

    public function pipeline(array $filters, ?User $actor = null): array
    {
        $filters = $this->filtersFor($filters, $actor);

        [$start, $end] = $this->resolveRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            1
        );

        $opportunities = $this->periodOpportunities($filters, $start, $end);

        $rows = collect(OpportunityStatus::cases())
            ->map(function (OpportunityStatus $status) use ($opportunities) {
                $rows = $opportunities->where('status', $status->value);

                return [
                    'status' => $status->value,
                    'count' => $rows->count(),
                    'value' => round((float) $rows->sum('value'), 2),
                ];
            })
            ->when(
                $filters['status'] ?? null,
                fn ($collection, $status) => $collection->filter(fn (array $row) => $row['status'] === $status)
            )
            ->values()
            ->all();

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'total_count' => array_sum(array_column($rows, 'count')),
            'total_value' => round(array_sum(array_column($rows, 'value')), 2),
            'data' => $rows,
        ];
    }

    public function export(array $filters, ?User $actor = null): string
    {
        $filters = $this->filtersFor($filters, $actor);

        $report = $this->performance($filters, $actor);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'sales_id',
            'sales_name',
            'sales_email',
            'total_deals',
            'won_deals',
            'lost_deals',
            'win_rate',
            'total_revenue',
            'avg_deal_size',
        ]);

        foreach ($report['data'] as $row) {
            fputcsv($stream, [
                $row['user']['id'],
                $row['user']['name'],
                $row['user']['email'],
                $row['total_deals'],
                $row['won_deals'],
                $row['lost_deals'],
                $row['win_rate'],
                $row['total_revenue'],
                $row['avg_deal_size'],
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function filtersFor(array $filters, ?User $actor): array
    {
        if ($actor && ! $this->isViewer($actor)) {
            $filters['sales_id'] = $actor->id;
        }

        return $filters;
    }

    private function periodOpportunities(array $filters, string $start, string $end): Collection
    {
        return Opportunity::query()
            ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->when($filters['sales_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get(['id', 'user_id', 'status', 'value']);
    }

    private function summarize(Collection $opportunities): array
    {
        $total = $opportunities->count();
        $won = $opportunities->where('status', OpportunityStatus::WON->value)->count();
        $lost = $opportunities->where('status', OpportunityStatus::LOST->value)->count();
        $closed = $won + $lost;
        $revenue = (float) $opportunities->where('status', OpportunityStatus::WON->value)->sum('value');

        return [
            'total_deals' => $total,
            'won_deals' => $won,
            'lost_deals' => $lost,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 2) : 0.0,
            'total_revenue' => round($revenue, 2),
            'avg_deal_size' => $won > 0 ? round($revenue / $won, 2) : 0.0,
        ];
    }
}
