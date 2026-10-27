<?php

namespace App\Models;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Base for every CRM model: rows belong to one company and are only visible inside it (PER-5).
 * Queries without a current company return nothing rather than every company's rows.
 * The guards run on model events, so never put company_id in a query-builder update().
 *
 * @property int $company_id
 */
abstract class CompanyScopedModel extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('company', function (Builder $query): void {
            $companyId = CurrentCompany::id();

            $companyId === null
                ? $query->whereRaw('1 = 0')
                : $query->where($query->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (CompanyScopedModel $model): void {
            $model->company_id = CurrentCompany::id()
                ?? throw new LogicException('Cannot create '.$model::class.' without a current company.');
        });

        static::updating(function (CompanyScopedModel $model): void {
            if ($model->isDirty('company_id')) {
                throw new LogicException('Cannot move '.$model::class.' to another company.');
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
