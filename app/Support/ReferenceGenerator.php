<?php

namespace App\Support;

use App\Models\Commission;
use App\Models\Dispute;
use App\Models\HomeownerEnquiry;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ReferenceGenerator
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function generate(string $prefix, string $model, string $column, int $padding = 4): string
    {
        $attempts = 0;

        do {
            $next = self::nextSequence($model, $column, $prefix) + $attempts;
            $reference = $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
            $exists = $model::query()->where($column, $reference)->exists();
            $attempts++;
        } while ($exists && $attempts < 100);

        if ($exists) {
            $reference = $prefix.strtoupper(Str::random(max($padding, 6)));
        }

        return $reference;
    }

    public static function lead(): string
    {
        return self::generate('LD-', Lead::class, 'lead_reference', 4);
    }

    public static function package(): string
    {
        return self::generate('PKG-', LeadPackage::class, 'package_reference', 3);
    }

    public static function payment(): string
    {
        return self::generate('PAY-', Payment::class, 'payment_reference', 5);
    }

    public static function invoice(): string
    {
        return self::generate('INV-', Invoice::class, 'invoice_reference', 5);
    }

    public static function payout(): string
    {
        return self::generate('PO-', Payout::class, 'payout_reference', 5);
    }

    public static function commission(): string
    {
        return self::generate('COM-', Commission::class, 'commission_reference', 5);
    }

    public static function thread(): string
    {
        return self::generate('THR-', MessageThread::class, 'thread_reference', 5);
    }

    public static function dispute(): string
    {
        return self::generate('DSP-', Dispute::class, 'dispute_reference', 5);
    }

    public static function homeownerEnquiry(): string
    {
        return self::generate('HE-', HomeownerEnquiry::class, 'reference', 5);
    }

    public static function purchase(): string
    {
        return self::generate('PUR-', Purchase::class, 'purchase_reference', 5);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function nextSequence(string $model, string $column, string $prefix): int
    {
        $latest = $model::query()
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value($column);

        if (! is_string($latest)) {
            return 1;
        }

        $numeric = (int) preg_replace('/\D+/', '', Str::after($latest, $prefix));

        return max(1, $numeric + 1);
    }
}
