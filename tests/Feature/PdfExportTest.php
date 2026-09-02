<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Kasir;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_download_paid_receipt_as_pdf_and_keep_thermal_page(): void
    {
        $admin = Admin::factory()->create();
        $transaction = $this->createTransaction('PDF-PAID', Transaction::PAYMENT_PAID, 25000, '2026-08-10 10:00:00');
        $transaction->items()->create([
            'menu_name' => 'Nasi Bakar',
            'price' => 25000,
            'quantity' => 1,
            'subtotal' => 25000,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.orders.receipt', $transaction))
            ->assertOk()
            ->assertSee('Struk Pembayaran')
            ->assertSee('Nasi Bakar');

        $response = $this->get(route('admin.orders.receipt.pdf', $transaction));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('struk-PDF-PAID.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pending_receipt_is_rejected_for_thermal_and_pdf(): void
    {
        $admin = Admin::factory()->create();
        $transaction = $this->createTransaction('PDF-PENDING', Transaction::PAYMENT_PENDING, 25000);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.orders.receipt', $transaction))
            ->assertNotFound();
        $this->get(route('admin.orders.receipt.pdf', $transaction))->assertNotFound();
    }

    public function test_pending_receipt_can_not_be_marked_as_printed(): void
    {
        $admin = Admin::factory()->create();
        $transaction = $this->createTransaction('PDF-PRINT-PENDING', Transaction::PAYMENT_PENDING, 25000);

        $response = $this->activateDevice()->actingAs($admin)
            ->post(route('admin.orders.printed', $transaction), [], ['Accept' => 'application/json']);

        $response->assertNotFound();
        $this->assertNull($transaction->fresh()->printed_at);
    }

    public function test_admin_can_download_active_report_and_snapshot_pdf(): void
    {
        $admin = Admin::factory()->create(['name' => 'Admin PDF']);
        $this->createTransaction('REPORT-PDF-1', Transaction::PAYMENT_PAID, 100000, '2026-08-10 08:00:00');
        $this->createTransaction('REPORT-PDF-2', Transaction::PAYMENT_PAID, 50000, '2026-08-10 12:00:00');
        $this->createTransaction('REPORT-PDF-PENDING', Transaction::PAYMENT_PENDING, 900000);
        $snapshot = Report::query()->create([
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-10',
            'total_revenue' => 150000,
            'transaction_count' => 2,
            'admin_id' => $admin->id,
        ]);

        $this->activateDevice()->actingAs($admin)
            ->get(route('admin.reports.sales', ['mode' => 'daily', 'date' => '2026-08-10']))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['revenue'] === $snapshot->total_revenue
                && $summary['transactions'] === $snapshot->transaction_count);

        $activePdf = $this->get(route('admin.reports.pdf', ['mode' => 'daily', 'date' => '2026-08-10']));
        $activePdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('laporan-daily-2026-08-10.pdf');
        $this->assertStringStartsWith('%PDF-', $activePdf->getContent());

        $snapshotPdf = $this->get(route('admin.reports.snapshot.pdf', $snapshot));
        $snapshotPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("laporan-snapshot-{$snapshot->id}.pdf");
        $this->assertStringStartsWith('%PDF-', $snapshotPdf->getContent());
    }

    public function test_kasir_can_not_download_report_pdf(): void
    {
        $kasir = Kasir::factory()->create();
        $snapshot = Report::query()->create([
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-10',
            'total_revenue' => 0,
            'transaction_count' => 0,
            'admin_id' => Admin::factory()->create()->id,
        ]);

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('admin.reports.pdf', ['mode' => 'daily', 'date' => '2026-08-10']))
            ->assertForbidden();
        $this->get(route('admin.reports.snapshot.pdf', $snapshot))->assertForbidden();
    }

    private function createTransaction(
        string $orderNumber,
        string $status,
        int $total,
        ?string $paidAt = null,
    ): Transaction {
        $customer = Customer::query()->firstOrCreate([
            'session_id' => 'pdf-customer',
        ], [
            'name' => 'Pelanggan PDF',
        ]);

        return Transaction::query()->create([
            'order_number' => $orderNumber,
            'user_session_id' => $customer->session_id,
            'customer_name' => 'Pelanggan PDF',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => $status,
            'paid_at' => $paidAt,
        ]);
    }
}
