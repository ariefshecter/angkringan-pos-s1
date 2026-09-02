<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Kasir;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_only_counts_paid_transactions_on_selected_date(): void
    {
        $this->createTransaction('DAY-CASH', Transaction::PAYMENT_PAID, 100000, 'cash', '2026-08-10 08:30:00');
        $this->createTransaction('DAY-DIGITAL', Transaction::PAYMENT_PAID, 50000, 'midtrans', '2026-08-10 14:15:00');
        $this->createTransaction('DAY-PENDING', Transaction::PAYMENT_PENDING, 900000, 'cash', null);
        $this->createTransaction('DAY-OTHER', Transaction::PAYMENT_PAID, 700000, 'cash', '2026-08-09 23:59:59');
        $admin = Admin::factory()->create();

        $this->activateDevice()
            ->actingAs($admin)
            ->get(route('admin.reports.sales', ['mode' => 'daily', 'date' => '2026-08-10']))
            ->assertOk()
            ->assertSee('Laporan Harian')
            ->assertViewHas('mode', 'daily')
            ->assertViewHas('date', '2026-08-10')
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'revenue' => 150000,
                'transactions' => 2,
                'average' => 75000,
                'cash' => 1,
                'digital' => 1,
            ])
            ->assertViewHas('chartRows', fn ($rows): bool => $rows->count() === 24
                && $rows->sum('revenue') === 150000
                && $rows->sum('orders') === 2);
    }

    public function test_monthly_report_only_counts_paid_transactions_in_selected_month(): void
    {
        $this->createTransaction('MONTH-START', Transaction::PAYMENT_PAID, 80000, 'cash', '2026-07-01 00:00:00');
        $this->createTransaction('MONTH-END', Transaction::PAYMENT_PAID, 120000, 'midtrans', '2026-07-31 23:59:59');
        $this->createTransaction('MONTH-OTHER', Transaction::PAYMENT_PAID, 500000, 'cash', '2026-08-01 00:00:00');
        $admin = Admin::factory()->create();

        $this->activateDevice()
            ->actingAs($admin)
            ->get(route('admin.reports.sales', ['mode' => 'monthly', 'month' => '2026-07']))
            ->assertOk()
            ->assertSee('Laporan Bulanan')
            ->assertViewHas('mode', 'monthly')
            ->assertViewHas('month', '2026-07')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['revenue'] === 200000
                && $summary['transactions'] === 2)
            ->assertViewHas('chartRows', fn ($rows): bool => $rows->count() === 31
                && $rows->sum('revenue') === 200000
                && $rows->sum('orders') === 2);
    }

    public function test_admin_can_create_and_update_snapshot_for_the_same_period(): void
    {
        $admin = Admin::factory()->create();
        $this->createTransaction('SNAPSHOT-FIRST', Transaction::PAYMENT_PAID, 100000, 'cash', '2026-08-10 10:00:00');

        $this->activateDevice()->actingAs($admin)
            ->post(route('admin.reports.store'), ['mode' => 'daily', 'date' => '2026-08-10'])
            ->assertRedirect(route('admin.reports.sales', ['mode' => 'daily', 'date' => '2026-08-10']))
            ->assertSessionHas('success');

        $report = Report::query()->sole();
        $this->assertSame('2026-08-10', $report->period_start->toDateString());
        $this->assertSame('2026-08-10', $report->period_end->toDateString());
        $this->assertSame(100000, $report->total_revenue);
        $this->assertSame(1, $report->transaction_count);
        $this->assertSame($admin->id, $report->admin_id);

        $this->createTransaction('SNAPSHOT-SECOND', Transaction::PAYMENT_PAID, 50000, 'midtrans', '2026-08-10 15:00:00');
        $this->post(route('admin.reports.store'), ['mode' => 'daily', 'date' => '2026-08-10'])
            ->assertRedirect();

        $this->assertDatabaseCount('laporan', 1);
        $report->refresh();
        $this->assertSame(150000, $report->total_revenue);
        $this->assertSame(2, $report->transaction_count);
        $this->assertSame($admin->id, $report->admin_id);
    }

    public function test_admin_can_view_snapshot_list_and_detail(): void
    {
        $admin = Admin::factory()->create(['name' => 'Admin Laporan']);
        $report = Report::query()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'total_revenue' => 350000,
            'transaction_count' => 7,
            'admin_id' => $admin->id,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.reports.snapshots'))
            ->assertOk()
            ->assertSee('Riwayat Rekap')
            ->assertSee('Admin Laporan')
            ->assertSee('350.000');
        $this->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Detail Rekap')
            ->assertSee('350.000')
            ->assertSee('Admin Laporan');
    }

    public function test_invalid_report_period_is_rejected(): void
    {
        $admin = Admin::factory()->create();

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.reports.sales', ['mode' => 'monthly', 'month' => '2026-13']))
            ->assertSessionHasErrors('month');
    }

    public function test_kasir_can_not_access_or_store_sales_reports(): void
    {
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.reports.sales'))
            ->assertForbidden();
        $this->post(route('admin.reports.store'), ['mode' => 'daily', 'date' => '2026-08-10'])
            ->assertForbidden();
        $this->get(route('admin.reports.snapshots'))->assertForbidden();
    }

    private function createTransaction(
        string $orderNumber,
        string $status,
        int $total,
        string $method,
        mixed $paidAt,
    ): Transaction {
        $customer = Customer::query()->firstOrCreate([
            'session_id' => 'report-customer',
        ], [
            'name' => 'Pelanggan',
        ]);

        return Transaction::query()->create([
            'order_number' => $orderNumber,
            'user_session_id' => $customer->session_id,
            'customer_name' => 'Pelanggan',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $method,
            'payment_status' => $status,
            'paid_at' => $paidAt,
        ]);
    }
}
