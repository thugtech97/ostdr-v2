<?php

namespace Tests\Feature\Reports;

use App\Models\Audit;
use App\Models\IssuedItem;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\ProductCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['dept' => 'ICT']);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $items  [requested, issued]
     */
    private function makeRequest(array $attributes = [], array $items = [[5, 0]]): StockRequest
    {
        $request = StockRequest::create([
            'date_filed' => '2026-09-01',
            'time_filed' => '09:30',
            'dept' => 'ICT',
            'origin' => 'MCD MINE',
            'created_by' => 'juan',
            'status' => 'FULLY APPROVED',
            'isReceived' => true,
            'isSaved' => true,
            'active' => true,
            ...$attributes,
        ]);
        $request->update(['transaction_no' => StockRequest::makeTransactionNo('2026-09-01', $request->id)]);

        foreach ($items as $i => [$requested, $issued]) {
            $item = RequestedItem::create([
                'stock_code' => '1000'.$i,
                'description' => "ITEM {$i}",
                'uom' => 'PC',
                'requested_qty' => $requested,
                'transaction_no' => $request->transaction_no,
            ]);

            if ($issued) {
                IssuedItem::create(['item_id' => $item->id, 'item_code' => $item->stock_code, 'issuance_qty' => $issued, 'balance' => $requested - $issued]);
            }
        }

        return $request->refresh();
    }

    public function test_stock_request_report_covers_the_users_department_with_status_filter(): void
    {
        $this->makeRequest(['status' => 'Pending', 'isReceived' => false]);
        $done = $this->makeRequest([], [[5, 5]]);
        $this->makeRequest(['dept' => 'MOTORPOOL']);

        $this->actingAs($this->user)->get('/reports/stockRequests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/StockRequests')->where('requests.total', 2));

        $this->actingAs($this->user)->get('/reports/stockRequests?status=completed')
            ->assertInertia(fn ($page) => $page->where('requests.total', 1)->where('requests.data.0.id', $done->id));
    }

    public function test_report_requests_can_be_viewed_and_printed_by_the_department_only(): void
    {
        $this->mock(ProductCatalogue::class, fn ($mock) => $mock->shouldReceive('findByCodes')->andReturn(collect()));
        $own = $this->makeRequest();
        $other = $this->makeRequest(['dept' => 'MOTORPOOL']);

        $this->actingAs($this->user)->get("/reports/stockRequests/view/{$own->id}")->assertOk();
        $this->actingAs($this->user)->get("/reports/stockRequests/print/{$own->id}")->assertOk();
        $this->actingAs($this->user)->get("/reports/stockRequests/view/{$other->id}")->assertNotFound();
    }

    public function test_unserved_report_lists_owed_lines_for_both_sides_of_the_transfer(): void
    {
        $this->makeRequest([], [[5, 0], [4, 2], [3, 3]]);
        $this->makeRequest(['dept' => 'MOTORPOOL', 'origin' => 'ICT'], [[2, 0]]);
        $this->makeRequest(['dept' => 'MOTORPOOL', 'origin' => 'MCD MILL'], [[2, 0]]);

        $this->actingAs($this->user)->get('/reports/unserved')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/Unserved')->where('items.total', 2));

        $this->actingAs($this->user)->get('/reports/unserved?served=partial')
            ->assertInertia(fn ($page) => $page->where('items.total', 1)->where('items.data.0.unserved_qty', 2));

        $this->actingAs($this->user)->get('/reports/unserved?served=all&sort=issued_total&direction=desc')
            ->assertInertia(fn ($page) => $page->where('items.total', 3)->where('items.data.0.issued_total', 2));
    }

    public function test_closed_lines_stay_on_the_unserved_report(): void
    {
        $request = $this->makeRequest([], [[5, 0], [2, 0]]);
        $request->items()->first()->update(['isClosed' => 1, 'isClosed_remarks' => 'no stock']);

        $this->actingAs($this->user)->get('/reports/unserved?item_state=closed')
            ->assertInertia(fn ($page) => $page->where('items.total', 1)->where('items.data.0.isClosed_remarks', 'no stock'));

        $this->actingAs($this->user)->get('/reports/unserved?item_state=open')
            ->assertInertia(fn ($page) => $page->where('items.total', 1));
    }

    public function test_audit_log_defaults_to_today_and_records_the_report_view(): void
    {
        $this->grantUser($this->user, 'User Action Monitoring', ['view']);
        Audit::create(['event' => 'updated', 'auditable_type' => 'App\Models\Role', 'auditable_id' => 1, 'old_values' => [], 'new_values' => ['a' => 1]]);
        Audit::create(['event' => 'created', 'auditable_type' => 'App\Models\Role', 'auditable_id' => 1, 'old_values' => [], 'new_values' => []])
            ->forceFill(['created_at' => now()->subDays(3)])->save();

        // Creating the test user is audited too, so narrow to the role entries.
        $this->actingAs($this->user)->get('/reports/audit-logs?search=Role')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/AuditLogs')
                ->where('filters.date_from', now()->toDateString())
                ->where('logs.total', 1)
                ->where('logs.data.0.new_values', '{"a":1}'));

        $this->assertSame(1, Audit::where('event', 'Generate Report')->count());
    }

    public function test_error_log_reads_the_log_table(): void
    {
        $this->grantUser($this->user, 'Application Error Logs', ['view']);
        DB::table('log')->insert(['message' => 'Boom', 'level' => 400, 'level_name' => 'ERROR', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->user)->get('/reports/error-logs?search=boom')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/ErrorLogs')->where('logs.total', 1)->where('logs.data.0.level_name', 'ERROR'));
    }

    public function test_errors_are_written_to_the_log_table(): void
    {
        logger()->channel('database')->error('Something failed', ['exception' => new \RuntimeException('bad')]);

        $row = DB::table('log')->sole();
        $this->assertSame('Something failed', $row->message);
        $this->assertSame('ERROR', $row->level_name);
        $this->assertStringContainsString('RuntimeException', $row->context);
    }

    public function test_exports_stream_csv(): void
    {
        $this->grantUser($this->user, 'User Action Monitoring', ['view']);
        $request = $this->makeRequest();

        $this->assertStringContainsString($request->transaction_no, $this->actingAs($this->user)->get('/reports/unserved/export')->streamedContent());
        $this->assertStringContainsString($request->transaction_no, $this->actingAs($this->user)->get('/reports/stockRequests/export')->streamedContent());
        $this->assertStringContainsString('Activity Date', $this->actingAs($this->user)->get('/reports/audit-logs/export')->streamedContent());
    }

    public function test_user_manual_page(): void
    {
        $this->actingAs($this->user)->get('/users/user-manual')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/UserManual'));
    }
}
