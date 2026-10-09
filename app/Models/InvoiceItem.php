<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'integer',
            'tax_rate' => 'float',
            'tax_amount' => 'integer',
            'subtotal' => 'integer',
            'total' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The invoice this item belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Optional product or service referenced.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit price in major currency units.
     */
    protected function unitPriceDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->unit_price / 100, 2),
        );
    }

    /**
     * Subtotal in major currency units.
     */
    protected function subtotalDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->subtotal / 100, 2),
        );
    }

    /**
     * Tax amount in major currency units.
     */
    protected function taxAmountDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->tax_amount / 100, 2),
        );
    }

    /**
     * Total in major currency units.
     */
    protected function totalDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->total / 100, 2),
        );
    }

    /**
     * Formatted string for total.
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '$'.number_format($this->total / 100, 2),
        );
    }
}
