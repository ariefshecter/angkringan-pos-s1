<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_report_only_counts_paid_transactions_from_last_seven_days(): void
    {
        $this->createTransaction('WEEK-CASH', Transaction::PAYMENT_PAID, 100000, 'cash', now());
        $this->createTransaction('WEEK-DIGITAL', Transaction::PAYMENT_PAID, 50000, 'midtrans', now()->subDay());
        $this->createTransaction('WEEK-PENDING', Transaction::PAYMENT_PENDING, 900000, 'cash', null);
        $this->createTransaction('WEEK-OLD', Transaction::PAYMENT_PAID, 700000, 'cash', now()->subDays(8));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->activateDevice()
            ->actingAs($admin)
            ->get(route('admin.reports.sales', ['period' => 'weekly']))
            ->assertOk()
            ->assertSee('Grafik Pendapatan dan Transaksi')
            ->assertViewHas('period', 'weekly')
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'revenue' => 150000,
                'transactions' => 2,
                'average' => 75000,
                'cash' => 1,
                'digital' => 1,
            ])
            ->assertViewHas('chartRows', fn ($rows): bool => $rows->count() === 7
                && $rows->sum('revenue') === 150000
                && $rows->sum('orders') === 2);
    }

    public function test_monthly_report_groups_the_last_twelve_months(): void
    {
        $this->createTransaction('MONTH-NOW', Transaction::PAYMENT_PAID, 80000, 'cash', now());
        $this->createTransaction('MONTH-PAST', Transaction::PAYMENT_PAID, 120000, 'midtrans', now()->subMonths(2));
        $this->createTransaction('MONTH-OLD', Transaction::PAYMENT_PAID, 500000, 'cash', now()->subMonths(13));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->activateDevice()
            ->actingAs($admin)
            ->get(route('admin.reports.sales', ['period' => 'monthly']))
            ->assertOk()
            ->assertViewHas('period', 'monthly')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['revenue'] === 200000
                && $summary['transactions'] === 2)
            ->assertViewHas('chartRows', fn ($rows): bool => $rows->count() === 12
                && $rows->sum('revenue') === 200000
                && $rows->sum('orders') === 2);
    }

    private function createTransaction(
        string $orderNumber,
        string $status,
        int $total,
        string $method,
        mixed $paidAt,
    ): Transaction {
        return Transaction::query()->create([
            'order_number' => $orderNumber,
            'customer_name' => 'Pelanggan',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $method,
            'payment_status' => $status,
            'paid_at' => $paidAt,
        ]);
    }
}
