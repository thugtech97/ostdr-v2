<?php

namespace Tests\Feature\StockRequests;

use App\Models\Audit;
use App\Models\IssuedItem;
use App\Models\RequestedItem;
use App\Models\Satellite;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\ProductCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class StockRequestTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private User $requestor;

    /** @var array<string, array{code: string, name: string, uom: string, oem: ?string}> */
    private array $catalogue = [
        '10001' => ['code' => '10001', 'name' => 'BOLT, HEX 1/2 X 2', 'uom' => 'PC', 'oem' => 'ACME'],
        '10002' => ['code' => '10002', 'name' => 'NUT, HEX 1/2', 'uom' => 'PC', 'oem' => null],
        '10003' => ['code' => '10003', 'name' => 'GLOVES, LEATHER', 'uom' => 'PR', 'oem' => null],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Satellite::create(['name' => 'ICT', 'description' => 'ICT', 'active' => true]);
        Satellite::create(['name' => 'MOTORPOOL', 'description' => 'MOTORPOOL', 'active' => true]);

        $this->requestor = $this->requestorUser('ICT');

        $this->mock(ProductCatalogue::class, function ($mock) {
            $mock->shouldReceive('findByCodes')->andReturnUsing(
                fn (array $codes) => collect($this->catalogue)->only($codes)->map(fn ($p) => (object) $p),
            );
            $mock->shouldReceive('search')->andReturnUsing(
                fn (string $field, string $term) => collect($this->catalogue)
                    ->filter(fn ($p) => str_starts_with(strtolower($p[$field]), strtolower($term)))
                    ->values()->all(),
            );
        });
    }

    private function requestorUser(string $dept, array $pages = ['Main Dashboard', 'Manage Stock Request', 'Stock Request', 'Unsaved Stock Request']): User
    {
        $user = User::factory()->create(['dept' => $dept, 'role' => 'REQUESTOR']);

        foreach ($pages as $page) {
            $this->grantUser($user, $page, ['view', 'create', 'edit', 'delete', 'print']);
        }

        return $user;
    }

    private function makeRequest(array $attributes = [], array $items = [['10001', 5]]): StockRequest
    {
        $request = StockRequest::create([
            'date_filed' => '2026-09-01',
            'time_filed' => '09:30',
            'date_needed' => '2026-09-10',
            'dept' => $this->requestor->dept,
            'origin' => 'MCD MINE',
            'requestor' => $this->requestor->name,
            'requested_by' => 'ICT_'.$this->requestor->username.'_09/01/2026 09:30 AM',
            'created_by' => $this->requestor->username,
            'status' => 'Pending',
            'isSaved' => true,
            'active' => true,
            ...$attributes,
        ]);
        $request->update(['transaction_no' => StockRequest::makeTransactionNo('2026-09-01', $request->id)]);

        foreach ($items as [$code, $qty]) {
            RequestedItem::create([
                'stock_code' => $code,
                'description' => $this->catalogue[$code]['name'] ?? 'OLD ITEM',
                'uom' => $this->catalogue[$code]['uom'] ?? 'EA',
                'requested_qty' => $qty,
                'remarks' => 'for repair',
                'transaction_no' => $request->transaction_no,
            ]);
        }

        return $request->refresh();
    }

    private function issue(StockRequest $request, int $issued, ?int $balance = null): void
    {
        $item = $request->items()->first();
        IssuedItem::create([
            'item_id' => $item->id,
            'item_code' => $item->stock_code,
            'issuance_qty' => $issued,
            'balance' => $balance ?? ($item->requested_qty - $issued),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'origin' => 'MCD MINE',
            'date_needed' => '2026-10-01',
            'requestor' => 'Juan Dela Cruz',
            'remarks' => 'Deliver to ICT office',
            'items' => [
                ['stock_code' => '10001', 'requested_qty' => 5, 'remarks' => 'for repair'],
                ['stock_code' => '10002', 'requested_qty' => 5, 'remarks' => 'for repair'],
            ],
            ...$overrides,
        ];
    }

    public function test_dashboard_splits_the_departments_requests_like_legacy(): void
    {
        $this->makeRequest(['status' => 'Pending']);
        $this->makeRequest(['status' => 'FULLY APPROVED']);
        $done = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $this->issue($done, 5, 0);
        $this->makeRequest(['dept' => 'MOTORPOOL', 'status' => 'Pending']);

        $this->actingAs($this->requestor)
            ->get('/stockrequests/main-dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('StockRequests/Dashboard')
                ->where('total', 3)
                ->has('pending', 1)
                ->has('inProgress', 1)
                ->has('completed', 1));
    }

    public function test_dashboard_url_redirects_to_the_main_dashboard(): void
    {
        $this->actingAs($this->requestor)->get('/dashboard')->assertRedirect('/stockrequests/main-dashboard');
    }

    public function test_manage_list_shows_only_the_users_open_requests(): void
    {
        $mine = $this->makeRequest();
        $this->makeRequest(['created_by' => 'SOMEONEELSE']);
        $this->makeRequest(['active' => false]);
        $this->makeRequest(['isSaved' => false]);
        $finished = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $this->issue($finished, 5, 0);

        $this->actingAs($this->requestor)
            ->get('/stockrequests/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('StockRequests/Index')
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $mine->id)
                ->where('requests.data.0.badge.label', 'Pending'));
    }

    public function test_completed_requests_with_unserved_items_stay_on_the_list(): void
    {
        $closedUnserved = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $closedUnserved->items()->update(['isClosed' => 1]);

        $this->actingAs($this->requestor)
            ->get('/stockrequests/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.badge.label', 'Closed')
                ->where('requests.data.0.badge.note', 'Unserved'));
    }

    public function test_manage_list_searches_item_descriptions_and_filters_dates(): void
    {
        $this->makeRequest([], [['10003', 2]]);
        $this->makeRequest();

        $this->actingAs($this->requestor)
            ->get('/stockrequests/dashboard?search=GLOVES')
            ->assertInertia(fn ($page) => $page->where('requests.total', 1));

        $this->actingAs($this->requestor)
            ->get('/stockrequests/dashboard?date_from=2099-01-01')
            ->assertInertia(fn ($page) => $page->where('requests.total', 0));
    }

    public function test_pages_require_their_legacy_permissions(): void
    {
        $user = User::factory()->create(['dept' => 'ICT']);

        $this->actingAs($user)->get('/stockrequests/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/stockrequests/create')->assertForbidden();
        $this->actingAs($user)->get('/stockrequests/unsaved-dashboard')->assertForbidden();
        $this->actingAs($user)->getJson('/products/search?field=code&q=1')->assertForbidden();
        $this->actingAs($user)->get('/stockrequests/main-dashboard')->assertOk();
    }

    public function test_create_form_offers_origins_except_the_users_own_department(): void
    {
        $this->actingAs($this->requestor)
            ->get('/stockrequests/create')
            ->assertInertia(fn ($page) => $page
                ->component('StockRequests/Create')
                ->where('origins', ['MCD MINE', 'MCD MILL', 'MOTORPOOL'])
                ->where('defaults.dept', 'ICT'));
    }

    public function test_a_request_can_be_saved(): void
    {
        $this->travelTo('2026-09-26 14:05:00');

        $response = $this->actingAs($this->requestor)->post('/stockrequests', $this->payload());

        $request = StockRequest::sole();
        $response->assertRedirect(route('stockrequests.edit', $request, absolute: false));

        $this->assertSame('20260926-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT), $request->transaction_no);
        $this->assertSame('Pending', $request->status);
        $this->assertTrue($request->isSaved);
        $this->assertSame('ICT', $request->dept);
        $this->assertSame($this->requestor->username, $request->created_by);
        $this->assertSame("ICT_{$this->requestor->username}_09/26/2026 02:05 PM", $request->requested_by);
        $this->assertNull($request->WFS_connection);

        $items = $request->items()->get();
        $this->assertCount(2, $items);
        $this->assertSame('BOLT, HEX 1/2 X 2', $items[0]->description);
        $this->assertSame('PC', $items[0]->uom);
        $this->assertSame($request->requested_by, $items[0]->requested_by);

        $this->assertSame(1, Audit::where('auditable_type', StockRequest::class)->where('event', 'created')->count());
    }

    public function test_saving_validates_like_legacy(): void
    {
        $user = $this->actingAs($this->requestor);

        $user->post('/stockrequests', $this->payload(['items' => []]))->assertSessionHasErrors(['items' => 'Please add atleast 1 item!']);
        $user->post('/stockrequests', $this->payload(['origin' => 'ICT']))->assertSessionHasErrors('origin');
        $user->post('/stockrequests', $this->payload(['date_needed' => '']))->assertSessionHasErrors('date_needed');
        $user->post('/stockrequests', $this->payload(['items' => [
            ['stock_code' => '10001', 'requested_qty' => 1, 'remarks' => 'x'],
            ['stock_code' => '10001', 'requested_qty' => 1, 'remarks' => 'x'],
        ]]))->assertSessionHasErrors('items.1.stock_code');
        $user->post('/stockrequests', $this->payload(['items' => [
            ['stock_code' => '99999', 'requested_qty' => 1, 'remarks' => 'x'],
        ]]))->assertSessionHasErrors('items.0.stock_code');
        $user->post('/stockrequests', $this->payload(['items' => [
            ['stock_code' => '10001', 'requested_qty' => 0, 'remarks' => ''],
        ]]))->assertSessionHasErrors(['items.0.requested_qty', 'items.0.remarks']);
        $user->post('/stockrequests', $this->payload([
            'items' => collect(range(1, 11))->map(fn ($i) => ['stock_code' => (string) (20000 + $i), 'requested_qty' => 1, 'remarks' => 'x'])->all(),
        ]))->assertSessionHasErrors('items');

        $this->assertSame(0, StockRequest::count());
    }

    public function test_a_request_can_be_updated_and_keeps_items_no_longer_in_the_catalogue(): void
    {
        $request = $this->makeRequest(['WFS_connection' => null], [['55555', 3]]);

        $this->actingAs($this->requestor)
            ->put("/stockrequests/{$request->id}", $this->payload([
                'origin' => 'MCD MILL',
                'items' => [
                    ['stock_code' => '55555', 'requested_qty' => 4, 'remarks' => 'still needed'],
                    ['stock_code' => '10003', 'requested_qty' => 1, 'remarks' => 'new'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('MCD MILL', $request->origin);
        $this->assertSame('Pending', $request->status);
        $this->assertSame('0', (string) $request->WFS_connection);

        $items = $request->items()->get();
        $this->assertSame(['55555', '10003'], $items->pluck('stock_code')->all());
        $this->assertSame('OLD ITEM', $items[0]->description);
        $this->assertSame(4, $items[0]->requested_qty);
    }

    public function test_saving_with_submit_submits_the_request_without_sending_it_to_wfs(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->requestor)
            ->put("/stockrequests/{$request->id}", $this->payload(['submit' => true]))
            ->assertRedirect(route('stockrequests.index', absolute: false));

        $request->refresh();
        $this->assertSame('Submitted', $request->status);
        $this->assertSame('0', (string) $request->WFS_connection);
    }

    public function test_a_pending_request_can_be_submitted_from_the_list(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->requestor)->patch("/stockrequests/{$request->id}/submit")->assertSessionHas('success');
        $this->assertSame('Submitted', $request->refresh()->status);

        $this->actingAs($this->requestor)->patch("/stockrequests/{$request->id}/submit")->assertSessionHas('error');
    }

    public function test_requests_without_items_can_not_be_submitted(): void
    {
        $request = $this->makeRequest([], []);

        $this->actingAs($this->requestor)->patch("/stockrequests/{$request->id}/submit")->assertSessionHas('error');
        $this->assertSame('Pending', $request->refresh()->status);
    }

    public function test_submitted_or_received_requests_can_not_be_changed(): void
    {
        $submitted = $this->makeRequest(['status' => 'Submitted']);
        $received = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);

        $this->actingAs($this->requestor)->get("/stockrequests/edit/{$submitted->id}")->assertForbidden();
        $this->actingAs($this->requestor)->put("/stockrequests/{$received->id}", $this->payload())->assertForbidden();
        $this->actingAs($this->requestor)->delete("/stockrequests/{$submitted->id}")->assertForbidden();
    }

    public function test_other_peoples_requests_can_not_be_changed(): void
    {
        $theirs = $this->makeRequest(['created_by' => 'SOMEONEELSE']);

        $this->actingAs($this->requestor)->get("/stockrequests/edit/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->requestor)->patch("/stockrequests/{$theirs->id}/submit")->assertNotFound();
        $this->actingAs($this->requestor)->delete("/stockrequests/{$theirs->id}")->assertNotFound();
    }

    public function test_a_request_can_be_deleted(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->requestor)->delete("/stockrequests/{$request->id}")->assertSessionHas('success');

        $request->refresh();
        $this->assertFalse($request->active);
        $this->assertSame($this->requestor->username, $request->deleted_by);
        $this->assertNotNull($request->deleted_at);
    }

    public function test_deleting_needs_the_delete_permission_of_the_list_it_is_on(): void
    {
        $viewer = User::factory()->create(['dept' => 'ICT']);
        $this->grantUser($viewer, 'Stock Request', ['view', 'create', 'edit']);
        $this->grantUser($viewer, 'Manage Stock Request', ['view']);
        $request = $this->makeRequest(['created_by' => $viewer->username]);

        $this->actingAs($viewer)->delete("/stockrequests/{$request->id}")->assertForbidden();
        $this->assertTrue($request->refresh()->active);
    }

    public function test_unsaved_list_shows_only_drafts(): void
    {
        $this->makeRequest();
        $draft = $this->makeRequest(['isSaved' => false]);

        $this->actingAs($this->requestor)
            ->get('/stockrequests/unsaved-dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('StockRequests/Unsaved')
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $draft->id)
                ->where('counts.unsaved', 1));
    }

    public function test_department_mates_can_view_but_other_departments_can_not(): void
    {
        $request = $this->makeRequest();
        $mate = $this->requestorUser('ICT');
        $outsider = $this->requestorUser('MOTORPOOL');

        $this->actingAs($mate)->get("/stockrequests/view/{$request->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('StockRequests/Show')->has('items', 1));
        $this->actingAs($outsider)->get("/stockrequests/view/{$request->id}")->assertNotFound();
    }

    public function test_the_stock_request_form_can_be_printed(): void
    {
        $request = $this->makeRequest();

        $response = $this->actingAs($this->requestor)->get("/stockrequests/print/{$request->id}");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString("StockRequest#{$request->transaction_no}.pdf", $response->headers->get('content-disposition'));
    }

    public function test_the_list_can_be_exported_as_csv(): void
    {
        $request = $this->makeRequest();

        $response = $this->actingAs($this->requestor)->get('/stockrequests/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Transaction #', $csv);
        $this->assertStringContainsString($request->transaction_no, $csv);
    }

    public function test_product_search_returns_catalogue_matches(): void
    {
        $this->actingAs($this->requestor)
            ->getJson('/products/search?field=name&q=bolt')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', '10001');
    }

    public function test_status_badges_match_legacy(): void
    {
        $badge = fn (array $attributes) => StockRequest::query()->withProgress()->find($this->makeRequest($attributes)->id)->statusBadge();

        $this->assertSame('Approved', $badge(['status' => 'FULLY APPROVED'])['label']);
        $this->assertSame('Cancelled', $badge(['status' => 'CANCELLED'])['label']);
        $this->assertSame('Hold', $badge(['status' => 'HOLD'])['label']);
        $this->assertSame('Submitted', $badge(['status' => 'Submitted'])['label']);

        $received = $badge(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $this->assertSame(['Received', 'Unserved'], [$received['label'], $received['note']]);

        $partial = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $this->issue($partial, 2);
        $this->assertSame('Partially Served', StockRequest::query()->withProgress()->find($partial->id)->statusBadge()['note']);

        $done = $this->makeRequest(['status' => 'FULLY APPROVED', 'isReceived' => true]);
        $this->issue($done, 5, 0);
        $this->assertSame('Completed', StockRequest::query()->withProgress()->find($done->id)->statusBadge()['label']);
    }
}
