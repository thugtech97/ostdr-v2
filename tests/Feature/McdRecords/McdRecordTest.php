<?php

namespace Tests\Feature\McdRecords;

use App\Models\Batch;
use App\Models\IssuedItem;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\ProductCatalogue;
use App\Services\StockListReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class McdRecordTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private User $records;

    protected function setUp(): void
    {
        parent::setUp();

        $this->records = User::factory()->create(['dept' => 'MCD', 'role' => 'MCD RECORDS']);
        $this->grantUser($this->records, 'Completed Dashboard', ['view', 'create', 'edit', 'delete', 'print']);
        $this->grantUser($this->records, 'Manage Batches', ['view', 'create', 'edit', 'delete', 'print']);
        $this->grantUser($this->records, 'Item Updating', ['view', 'create']);
    }

    /**
     * Items are [code, requested, issued]; issued equal to requested makes the item complete.
     *
     * @param  list<array{0: string, 1: int, 2: int}>  $items
     */
    private function makeRequest(array $items = [['10001', 5, 5]], array $attributes = []): StockRequest
    {
        $request = StockRequest::create([
            'date_filed' => '2026-09-01',
            'time_filed' => '09:30',
            'dept' => 'ICT',
            'origin' => 'MCD MINE',
            'requested_by' => 'ICT_juan_09/01/2026 09:30 AM',
            'created_by' => 'juan',
            'status' => 'FULLY APPROVED',
            'isReceived' => true,
            'isSaved' => true,
            'active' => true,
            ...$attributes,
        ]);
        $request->update(['transaction_no' => StockRequest::makeTransactionNo('2026-09-01', $request->id)]);

        foreach ($items as [$code, $requested, $issued]) {
            $item = RequestedItem::create([
                'stock_code' => $code,
                'description' => "ITEM {$code}",
                'uom' => 'PC',
                'requested_qty' => $requested,
                'transaction_no' => $request->transaction_no,
            ]);

            if ($issued > 0) {
                IssuedItem::create(['item_id' => $item->id, 'item_code' => $code, 'issuance_qty' => $issued, 'balance' => $requested - $issued]);
            }
        }

        return $request->refresh();
    }

    public function test_completed_transactions_lists_completed_requests_with_batch_numbers(): void
    {
        $done = $this->makeRequest();
        $done->items()->first()->update(['batchno' => 'B-001']);
        $this->makeRequest([['10001', 5, 2]]);

        $this->actingAs($this->records)
            ->get('/mcdrecords/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('McdRecords/Index')
                ->where('requests.total', 1)
                ->where('requests.data.0.batch_numbers', 'B-001'));
    }

    public function test_completed_transactions_can_show_only_fully_batched_requests(): void
    {
        $batched = $this->makeRequest();
        $batched->items()->first()->update(['batchno' => 'B-001']);
        $this->makeRequest();

        $this->actingAs($this->records)
            ->get('/mcdrecords/dashboard?batch_status=all-batched')
            ->assertInertia(fn ($page) => $page->where('requests.total', 1)->where('requests.data.0.id', $batched->id));
    }

    public function test_records_users_can_view_and_print_any_request(): void
    {
        $this->mock(ProductCatalogue::class, fn ($mock) => $mock->shouldReceive('findByCodes')->andReturn(collect()));
        $request = $this->makeRequest();

        $this->actingAs($this->records)
            ->get("/mcdrecords/request/{$request->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Receiving/Show')->where('items.0.serve.label', 'COMPLETED'));

        $this->actingAs($this->records)->get("/mcdrecords/request/{$request->id}/print")->assertOk();
    }

    public function test_create_offers_requests_with_issued_items_left_to_batch(): void
    {
        $open = $this->makeRequest([['10001', 5, 5], ['10002', 2, 0]]);
        $this->makeRequest([['10001', 5, 2]]); // nothing fully issued
        $batched = $this->makeRequest();
        $batched->items()->first()->update(['batchno' => 'B-001']);

        $this->actingAs($this->records)
            ->get('/mcdrecords/create?transactions[]='.$open->transaction_no)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('McdRecords/BatchCreate')
                ->has('requests', 1)
                ->where('requests.0.transaction_no', $open->transaction_no)
                ->has('items', 1)
                ->where('items.0.stock_code', '10001'));
    }

    public function test_storing_a_batch_tags_the_checked_items(): void
    {
        $request = $this->makeRequest([['10001', 5, 5], ['10002', 4, 4]]);
        [$first, $second] = $request->items()->get()->all();

        $this->actingAs($this->records)
            ->post('/mcdrecords', ['batchno' => 'B-100', 'description' => 'September', 'item_ids' => [$first->id]])
            ->assertRedirect('/mcdrecords/batches');

        $this->assertDatabaseHas('batches', ['batchno' => 'B-100', 'created_by' => $this->records->username]);
        $this->assertSame('B-100', $first->refresh()->batchno);
        $this->assertNull($second->refresh()->batchno);
    }

    public function test_batch_numbers_must_be_unique_and_items_checked(): void
    {
        Batch::create(['batchno' => 'B-100', 'description' => 'x']);

        $this->actingAs($this->records)
            ->post('/mcdrecords', ['batchno' => 'B-100', 'description' => 'again', 'item_ids' => []])
            ->assertSessionHasErrors(['batchno', 'item_ids']);
    }

    public function test_renaming_a_batch_renames_its_items(): void
    {
        $batch = Batch::create(['batchno' => 'B-100', 'description' => 'x']);
        $request = $this->makeRequest();
        $item = $request->items()->first();
        $item->update(['batchno' => 'B-100']);

        $this->actingAs($this->records)
            ->put("/mcdrecords/{$batch->id}", ['batchno' => 'B-200', 'description' => 'renamed'])
            ->assertRedirect('/mcdrecords/batches');

        $this->assertSame('B-200', $batch->refresh()->batchno);
        $this->assertSame('B-200', $item->refresh()->batchno);
    }

    public function test_items_can_be_added_to_and_excluded_from_a_batch(): void
    {
        $batch = Batch::create(['batchno' => 'B-100', 'description' => 'x']);
        $request = $this->makeRequest([['10001', 5, 5], ['10002', 4, 0]]);
        [$issued, $unissued] = $request->items()->get()->all();

        $this->actingAs($this->records)
            ->post("/mcdrecords/{$batch->id}/include", ['transactions' => [$request->transaction_no]])
            ->assertSessionHas('success');

        $this->assertSame('B-100', $issued->refresh()->batchno);
        $this->assertNull($unissued->refresh()->batchno);

        $this->actingAs($this->records)
            ->patch("/mcdrecords/{$batch->id}/items/{$issued->id}/exclude")
            ->assertSessionHas('success');

        $this->assertNull($issued->refresh()->batchno);
    }

    public function test_deleting_a_batch_unbatches_its_items(): void
    {
        $batch = Batch::create(['batchno' => 'B-100', 'description' => 'x']);
        $item = $this->makeRequest()->items()->first();
        $item->update(['batchno' => 'B-100']);

        $this->actingAs($this->records)->delete("/mcdrecords/{$batch->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
        $this->assertNull($item->refresh()->batchno);
    }

    public function test_unbatched_and_unserved_reports(): void
    {
        $request = $this->makeRequest([['10001', 5, 5], ['10002', 4, 0], ['10003', 1, 0]]);
        [$issued, $unserved, $closed] = $request->items()->get()->all();
        $issued->update(['batchno' => 'B-1']);
        $closed->update(['isClosed' => 1]);

        $this->actingAs($this->records)
            ->get('/mcdrecords/unbatched')
            ->assertInertia(fn ($page) => $page->component('McdRecords/ItemReport')->where('items.total', 1)->where('items.data.0.id', $unserved->id));

        $this->actingAs($this->records)
            ->get('/mcdrecords/unserved?sort=issued_qty&direction=asc')
            ->assertInertia(fn ($page) => $page->where('items.total', 1)->where('items.data.0.id', $unserved->id));
    }

    public function test_stock_list_upload_updates_the_catalogue(): void
    {
        $rows = [['code' => '10001', 'description' => 'BOLT', 'oem' => 'X', 'uom' => 'PC']];

        $this->mock(StockListReader::class, fn ($mock) => $mock->shouldReceive('read')->andReturn($rows));
        $this->mock(ProductCatalogue::class, fn ($mock) => $mock->shouldReceive('importStockList')
            ->once()->with($rows, $this->records->id)->andReturn(['updated' => 1, 'created' => 0]));

        $this->actingAs($this->records)
            ->post('/mcdrecords/uploadStocks', ['itemFile' => UploadedFile::fake()->create('stock.xlsx', 10)])
            ->assertSessionHas('success');
    }

    public function test_stock_list_upload_rejects_other_files(): void
    {
        $this->actingAs($this->records)
            ->post('/mcdrecords/uploadStocks', ['itemFile' => UploadedFile::fake()->create('stock.csv', 10)])
            ->assertSessionHasErrors('itemFile');
    }

    public function test_pages_need_their_permission(): void
    {
        $other = User::factory()->create(['role' => 'REQUESTOR']);
        $this->grantUser($other, 'Stock Request', ['view']);

        foreach (['/mcdrecords/dashboard', '/mcdrecords/batches', '/mcdrecords/unbatched', '/mcdrecords/update-items'] as $url) {
            $this->actingAs($other)->get($url)->assertForbidden();
        }
    }
}
