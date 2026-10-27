<?php

use App\Models\Company;
use App\Models\CompanyScopedModel;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('company_probes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained();
        $table->string('name');
    });

    $this->probe = new class extends CompanyScopedModel
    {
        protected $table = 'company_probes';

        protected $guarded = [];

        public $timestamps = false;
    };

    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    DB::table('company_probes')->insert([
        ['company_id' => $this->companyA->id, 'name' => 'a'],
        ['company_id' => $this->companyB->id, 'name' => 'b'],
    ]);
});

test('a user only sees records of their own company', function () {
    $this->actingAs(User::factory()->for($this->companyA)->admin()->create());

    $otherCompanyRecordId = DB::table('company_probes')->where('name', 'b')->value('id');

    expect($this->probe::query()->pluck('name')->all())->toBe(['a'])
        ->and($this->probe::query()->find($otherCompanyRecordId))->toBeNull();
});

test('new records belong to the user\'s company whatever company id is given', function () {
    $this->actingAs(User::factory()->for($this->companyA)->create());

    $record = $this->probe::query()->create(['name' => 'new', 'company_id' => $this->companyB->id]);

    expect($record->company_id)->toBe($this->companyA->id);
});

test('without a signed-in user or a chosen company nothing is visible and nothing can be created', function () {
    expect($this->probe::query()->count())->toBe(0);

    $this->probe::query()->create(['name' => 'orphan']);
})->throws(LogicException::class);

test('code can run for a chosen company and the previous company is restored afterwards', function () {
    $names = CurrentCompany::run($this->companyB, fn () => $this->probe::query()->pluck('name')->all());

    expect($names)->toBe(['b'])
        ->and(CurrentCompany::id())->toBeNull();
});

test('a record cannot be moved to another company', function () {
    $this->actingAs(User::factory()->for($this->companyA)->create());

    $this->probe::query()->firstOrFail()->update(['company_id' => $this->companyB->id]);
})->throws(LogicException::class);

test('queued jobs dispatched for a chosen company run for that company', function () {
    config(['queue.default' => 'database']);

    CurrentCompany::run($this->companyB, function () {
        dispatch(function () {
            cache()->put('company seen by job', CurrentCompany::id());
        });
    });

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(cache()->get('company seen by job'))->toBe($this->companyB->id);
});
