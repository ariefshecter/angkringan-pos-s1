<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Report;
use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SalesReportController extends Controller
{
    public function index(Request $request, SalesReportService $reports): View
    {
        $filters = $this->validatedFilters($request);

        return view('admin.reports.sales', $reports->calculate(
            $filters['mode'],
            $filters['date'] ?? null,
            $filters['month'] ?? null,
            $filters['end_date'] ?? null,
            $filters['end_month'] ?? null,
        ));
    }

    public function store(Request $request, SalesReportService $reports): RedirectResponse
    {
        $filters = $this->validatedFilters($request);
        $report = $reports->calculate(
            $filters['mode'],
            $filters['date'] ?? null,
            $filters['month'] ?? null,
            $filters['end_date'] ?? null,
            $filters['end_month'] ?? null,
        );
        /** @var Admin $admin */
        $admin = $request->user();

        DB::transaction(function () use ($report, $admin): void {
            $snapshot = Report::query()
                ->whereDate('period_start', $report['start']->toDateString())
                ->whereDate('period_end', $report['end']->toDateString())
                ->lockForUpdate()
                ->first();

            $attributes = [
                'period_start' => $report['start']->toDateString(),
                'period_end' => $report['end']->toDateString(),
                'total_revenue' => $report['summary']['revenue'],
                'transaction_count' => $report['summary']['transactions'],
                'admin_id' => $admin->id,
            ];

            if ($snapshot) {
                $snapshot->update($attributes);
            } else {
                Report::query()->create($attributes);
            }
        });

        return redirect()
            ->route('admin.reports.sales', $filters)
            ->with('success', 'Rekap laporan berhasil disimpan.');
    }

    public function snapshots(): View
    {
        return view('admin.reports.snapshots', [
            'reports' => Report::query()->with('admin')->latest('period_end')->latest('id')->paginate(15),
        ]);
    }

    public function show(Report $report): View
    {
        return view('admin.reports.show', [
            'report' => $report->load('admin'),
        ]);
    }

    public function pdf(Request $request, SalesReportService $reports): Response
    {
        $filters = $this->validatedFilters($request);
        $report = $reports->calculate(
            $filters['mode'],
            $filters['date'] ?? null,
            $filters['month'] ?? null,
            $filters['end_date'] ?? null,
            $filters['end_month'] ?? null,
        );
        $filename = 'laporan-'.$report['mode'].'-'.$report['start']->format('Y-m-d').'.pdf';

        return Pdf::loadView('pdf.sales-report', [
            ...$report,
            'isSnapshot' => false,
            'recordedBy' => null,
        ])->setPaper('a4')->download($filename);
    }

    public function snapshotPdf(Report $report): Response
    {
        $report->load('admin');
        $transactionCount = $report->transaction_count;

        return Pdf::loadView('pdf.sales-report', [
            'periodLabel' => 'Snapshot Laporan',
            'rangeLabel' => $report->period_start->format('d/m/Y').' - '.$report->period_end->format('d/m/Y'),
            'start' => $report->period_start,
            'end' => $report->period_end,
            'summary' => [
                'revenue' => $report->total_revenue,
                'transactions' => $transactionCount,
                'average' => $transactionCount > 0 ? (int) round($report->total_revenue / $transactionCount) : 0,
                'cash' => null,
                'digital' => null,
            ],
            'chartRows' => collect(),
            'isSnapshot' => true,
            'recordedBy' => $report->admin?->name,
        ])->setPaper('a4')->download("laporan-snapshot-{$report->id}.pdf");
    }

    /** @return array{mode: string, date?: string, end_date?: string, month?: string, end_month?: string} */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['daily', 'monthly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'end_month' => ['nullable', 'date_format:Y-m', 'after_or_equal:month'],
        ]);
        $mode = $validated['mode'] ?? 'daily';

        return [
            'mode' => $mode,
            ...match ($mode) {
                'monthly' => [
                    'month' => $validated['month'] ?? now()->format('Y-m'),
                    'end_month' => $validated['end_month'] ?? $validated['month'] ?? now()->format('Y-m'),
                ],
                default => [
                    'date' => $validated['date'] ?? now()->toDateString(),
                    ...(! empty($validated['end_date']) ? ['end_date' => $validated['end_date']] : []),
                ],
            },
        ];
    }
}
