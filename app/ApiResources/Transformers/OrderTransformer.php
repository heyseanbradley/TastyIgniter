<?php

declare(strict_types=1);

namespace App\ApiResources\Transformers;

use Igniter\Api\ApiResources\Transformers\OrderTransformer as TastyIgniterOrderTransformer;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Currency\Facades\Currency;
use Throwable;

/**
 * Vendor OrderTransformer calls Currency::getDefault()->currency_code and
 * getOrderMenusWithOptions() after the order is already committed. Either
 * can 500 the create response while the kitchen ticket still exists.
 */
class OrderTransformer extends TastyIgniterOrderTransformer
{
    public function transform(Order $order): array
    {
        $payload = [
            'currency' => $this->currencyCode(),
            'order_totals' => $this->orderTotals($order),
            // Skip getOrderMenusWithOptions() — it 500s on the in-memory
            // model right after create, while a later GET of the same row works.
            'order_menus' => $this->orderMenus($order),
        ];

        try {
            return $this->mergesIdAttribute($order, $payload);
        } catch (Throwable) {
            return array_merge([
                'id' => $order->getKey(),
                'status_id' => $order->status_id,
                'order_type' => $order->order_type,
                'order_total' => $order->order_total,
            ], $payload);
        }
    }

    protected function currencyCode(): ?string
    {
        try {
            $currency = Currency::getDefault();

            return $currency?->currency_code;
        } catch (Throwable) {
            return null;
        }
    }

    protected function orderTotals(Order $order): mixed
    {
        try {
            return $order->getOrderTotals();
        } catch (Throwable) {
            return [];
        }
    }

    protected function orderMenus(Order $order): mixed
    {
        try {
            return $order->getOrderMenus();
        } catch (Throwable) {
            return [];
        }
    }
}
