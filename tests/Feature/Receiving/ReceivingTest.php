<?php

namespace Tests\Feature\Receiving;

use App\Models\IssuedItem;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\ProductCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['dept' => 'MCD MINE', 'role' => 'RECEIVER']);

        foreach (['Manage Receiving', 'Completed Request'] as $page) {
            $this->grantUser($this->receiver, $page, ['view', 'create', 'edit', 'delete', 'print']);
        }

        $this->mock(ProductCatalogue::class, fn ($mock) => $mock->shouldReceive('findByCodes')->andReturn(collect()));
    }

    /**
     * @param  list<array{0: string, 1: int}>  $items
     */
    private function makeRequest(array $attributes = [], array $items = [['10001', 5], ['10002', 3]]): StockRequest
    {
        $request = StockRequest::create([
            'date_filed' => '2026-09-01',
            'time_filed' => '09:30',
            'date_needed' => '2026-09-10',
            'dept' => 'ICT',
            'origin' => 'MCD MINE',
            'requestor' => 'Juan Dela Cruz',
            'requested_by' => 'ICT_juan_09/01/2026 09:30 AM',
            'created_by' => 'juan',
            'status' => 'FULLY APPROVED',
            'isSaved' => true,
            'active' => true,
            ...$attributes,
        ]);
        $request->update(['transaction_no' => StockRequest::makeTransactionNo('2026-09-01', $request->id)]);

        foreach ($items as [$code, $qty]) {
            RequestedItem::create([
                'stock_code' => $code,
                'description' => "ITEM {$code}",
                'uom' => 'PC',
                'requested_qty' => $qty,
                'transaction_no' => $request->transaction_no,
            ]);
        }

        return $request->refresh();
    }

    public function test_manage_receiving_lists_approved_requests_from_the_receivers_department(): void
    {
        $this->makeRequest();
        $this->makeRequest(['isReceived' => true]);
        $this->makeRequest(['status' => 'Submitted']);
        $this->makeRequest(['origin' => 'MCD MILL']);

        $this->actingAs($this->receiver)
            ->get('/mcds/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receiving/Index')
                ->where('requests.total', 2)
                ->where('requests.data.0.badge.label', 'Received')
                ->where('requests.data.1.badge.label', 'Approved'));
    }

    public function test_completed_requests_move_to_the_completed_dashboard(): void
    {
        $open = $this->makeRequest(['isReceived' => true]);
        $done = $this->makeRequest(['isReceived' => true], [['10001', 2]]);
        $item = $done->items()->first();
        IssuedItem::create(['item_id' => $item->id, 'item_code' => '10001', 'issuance_qty' => 2, 'balance' => 0]);

        $this->actingAs($this->receiver)
            ->get('/mcds/dashboard')
            ->assertInertia(fn ($page) => $page->where('requests.total', 1)->where('requests.data.0.id', $open->id));

        $this->actingAs($this->receiver)
            ->get('/mcds/completed-dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Receiving/Completed')->where('requests.total', 1)->where('requests.data.0.id', $done->id));
    }

    public function test_items_closed_without_issuance_keep_the_request_on_manage_receiving(): void
    {
        $request = $this->makeRequest(['isReceived' => true], [['10001', 2]]);
        $request->items()->first()->update(['isClosed' => 1, 'isClosed_remarks' => 'no stock']);

        $this->actingAs($this->receiver)
            ->get('/mcds/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.badge.note', 'Unserved'));
    }

    public function test_receiver_receives_a_request(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->receiver)
            ->patch("/mcds/{$request->id}/receive")
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertTrue($request->isReceived);
        $this->assertSame($this->receiver->username, $request->received_by);
        $this->assertNotNull($request->received_at);
    }

    public function test_requests_from_another_department_cannot_be_received(): void
    {
        $request = $this->makeRequest(['origin' => 'MCD MILL']);

        $this->actingAs($this->receiver)->patch("/mcds/{$request->id}/receive")->assertNotFound();
    }

    public function test_view_only_receivers_cannot_receive(): void
    {
        $viewer = User::factory()->create(['dept' => 'MCD MINE', 'role' => 'RECEIVER']);
        $this->grantUser($viewer, 'Manage Receiving', ['view']);
        $request = $this->makeRequest();

        $this->actingAs($viewer)->patch("/mcds/{$request->id}/receive")->assertForbidden();
    }

    public function test_edit_requires_the_request_to_be_received(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->receiver)->get("/mcds/edit/{$request->id}")->assertRedirect('/mcds/dashboard');
    }

    public function test_edit_lists_only_items_with_a_balance(): void
    {
        $request = $this->makeRequest(['isReceived' => true], [['10001', 5], ['10002', 3], ['10003', 1]]);
        [$partial, $full, $closed] = $request->items()->get()->all();
        IssuedItem::create(['item_id' => $partial->id, 'item_code' => '10001', 'issuance_qty' => 2, 'balance' => 3]);
        IssuedItem::create(['item_id' => $full->id, 'item_code' => '10002', 'issuance_qty' => 3, 'balance' => 0]);
        $closed->update(['isClosed' => 1]);

        $this->actingAs($this->receiver)
            ->get("/mcds/edit/{$request->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receiving/Edit')
                ->has('items', 1)
                ->where('items.0.id', $partial->id)
                ->where('items.0.issued_qty', 2)
                ->where('items.0.balance', 3)
                ->has('items.0.history', 1));
    }

    public function test_issuing_records_quantities_and_balances(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);
        [$first, $second] = $request->items()->get()->all();

        $this->actingAs($this->receiver)
            ->post("/mcds/{$request->id}/issue", ['items' => [
                ['id' => $first->id, 'issuance_qty' => 2],
                ['id' => $second->id, 'issuance_qty' => null],
            ]])
            ->assertRedirect('/mcds/dashboard');

        $this->assertSame(1, IssuedItem::count());
        $issued = IssuedItem::first();
        $this->assertSame(2, (int) $issued->issuance_qty);
        $this->assertSame(3, (int) $issued->balance);
        $this->assertSame($request->requested_by, $issued->received_by);
        $this->assertSame($this->receiver->username, $issued->issued_by);
    }

    public function test_saving_with_nothing_entered_returns_to_the_dashboard(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);

        $this->actingAs($this->receiver)
            ->post("/mcds/{$request->id}/issue", ['items' => [['id' => $request->items()->first()->id, 'issuance_qty' => null]]])
            ->assertRedirect('/mcds/dashboard');

        $this->assertSame(0, IssuedItem::count());
    }

    public function test_overissue_is_rejected(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);
        $first = $request->items()->first();

        $this->actingAs($this->receiver)
            ->post("/mcds/{$request->id}/issue", ['items' => [['id' => $first->id, 'issuance_qty' => 6]]])
            ->assertSessionHasErrors("items.{$first->id}");

        $this->assertSame(0, IssuedItem::count());
    }

    public function test_closing_an_item_needs_a_reason(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);
        $item = $request->items()->first();

        $this->actingAs($this->receiver)->patch("/mcds/items/{$item->id}/close", ['remarks' => ' '])->assertSessionHasErrors('remarks');

        $this->actingAs($this->receiver)->patch("/mcds/items/{$item->id}/close", ['remarks' => 'out of stock'])->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(1, (int) $item->isClosed);
        $this->assertSame('out of stock', $item->isClosed_remarks);
    }

    public function test_excluding_an_item_soft_deletes_it(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);
        $item = $request->items()->first();

        $this->actingAs($this->receiver)->delete("/mcds/items/{$item->id}")->assertSessionHas('success');

        $item->refresh();
        $this->assertNotNull($item->deleted_at);
        $this->assertSame($this->receiver->username, $item->deleted_by);
        $this->assertSame(1, $request->items()->count());
    }

    public function test_show_reports_how_each_item_was_served(): void
    {
        $request = $this->makeRequest(['isReceived' => true], [['10001', 5], ['10002', 3]]);
        $first = $request->items()->first();
        IssuedItem::create(['item_id' => $first->id, 'item_code' => '10001', 'issuance_qty' => 2, 'balance' => 3]);

        $this->actingAs($this->receiver)
            ->get("/mcds/view/{$request->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receiving/Show')
                ->where('items.0.serve.label', 'PARTIALLY SERVED')
                ->where('items.1.serve.label', 'UNSERVED'));
    }

    public function test_print_downloads_the_issuer_form(): void
    {
        $request = $this->makeRequest(['isReceived' => true]);

        $response = $this->actingAs($this->receiver)->get("/mcds/printStockRequest/{$request->id}");

        $response->assertOk();
        $this->assertStringContainsString('ReceivedStockRequest#'.$request->transaction_no, $response->headers->get('content-disposition'));
    }

    public function test_export_downloads_csv(): void
    {
        $request = $this->makeRequest();

        $response = $this->actingAs($this->receiver)->get('/mcds/export');

        $response->assertOk();
        $this->assertStringContainsString($request->transaction_no, $response->streamedContent());
    }

    public function test_sidebar_counts_requests_waiting_to_be_received(): void
    {
        $this->makeRequest();
        $this->makeRequest(['isReceived' => true]);

        $this->actingAs($this->receiver)
            ->get('/mcds/dashboard')
            ->assertInertia(fn ($page) => $page->where('counts.receiving', 1));
    }
}
