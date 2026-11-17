<?php

namespace App\Services\Import;

final class WooCommerceApiNormalizer
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, string>
     */
    public function product(array $product): array
    {
        $images = $this->images($product);
        $categories = $this->categoryNames($product);
        $attributes = $this->attributes($product);

        $row = [
            'id' => (string) ($product['id'] ?? ''),
            'type' => (string) ($product['type'] ?? 'simple'),
            'sku' => (string) ($product['sku'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'published' => ($product['status'] ?? 'publish') === 'publish' ? '1' : '0',
            'is featured' => ! empty($product['featured']) ? '1' : '0',
            'short description' => (string) ($product['short_description'] ?? ''),
            'description' => (string) ($product['description'] ?? ''),
            'regular price' => (string) ($product['regular_price'] ?? $product['price'] ?? ''),
            'sale price' => (string) ($product['sale_price'] ?? ''),
            'categories' => $categories[0] ?? '',
            'images' => implode(', ', $images),
            'stock' => ($product['stock_quantity'] ?? null) !== null ? (string) $product['stock_quantity'] : '',
            'in stock' => ($product['stock_status'] ?? 'instock') === 'outofstock' ? '0' : '1',
            'weight' => (string) ($product['weight'] ?? ''),
            'virtual' => ! empty($product['virtual']) ? '1' : '0',
            'downloadable' => ! empty($product['downloadable']) ? '1' : '0',
            'tax class' => (string) ($product['tax_class'] ?? ''),
        ];

        foreach ($attributes as $index => $attribute) {
            $row['attribute '.($index + 1).' name'] = $attribute['name'];
            $row['attribute '.($index + 1).' value s'] = $attribute['value'];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $variation
     * @param  array<string, mixed>  $parent
     * @return array<string, string>
     */
    public function variation(array $variation, array $parent): array
    {
        $attributes = $this->attributes($variation);
        $images = $this->images($variation);
        $name = trim((string) ($parent['name'] ?? 'Variation'));

        if ($attributes !== []) {
            $name .= ' - '.implode(' / ', array_column($attributes, 'value'));
        }

        $row = [
            'type' => 'variation',
            'parent' => $this->parentReference($parent),
            'sku' => (string) ($variation['sku'] ?? ''),
            'name' => $name,
            'published' => ($variation['status'] ?? 'publish') === 'publish' ? '1' : '0',
            'regular price' => (string) ($variation['regular_price'] ?? $variation['price'] ?? ''),
            'sale price' => (string) ($variation['sale_price'] ?? ''),
            'stock' => ($variation['stock_quantity'] ?? null) !== null ? (string) $variation['stock_quantity'] : '',
            'in stock' => ($variation['stock_status'] ?? 'instock') === 'outofstock' ? '0' : '1',
            'weight' => (string) ($variation['weight'] ?? ''),
            'images' => implode(', ', $images),
            'virtual' => ! empty($variation['virtual']) ? '1' : '0',
            'downloadable' => ! empty($variation['downloadable']) ? '1' : '0',
            'tax class' => (string) ($variation['tax_class'] ?? ''),
        ];

        foreach ($attributes as $index => $attribute) {
            $row['attribute '.($index + 1).' name'] = $attribute['name'];
            $row['attribute '.($index + 1).' value s'] = $attribute['value'];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $rate
     * @return array<string, string>
     */
    public function taxRate(array $rate): array
    {
        $postcodes = array_filter(array_map('strval', (array) ($rate['postcodes'] ?? [])));

        if (isset($rate['postcode']) && trim((string) $rate['postcode']) !== '') {
            $postcodes[] = trim((string) $rate['postcode']);
        }

        $cities = array_filter(array_map('strval', (array) ($rate['cities'] ?? [])));

        if (isset($rate['city']) && trim((string) $rate['city']) !== '') {
            $cities[] = trim((string) $rate['city']);
        }

        return [
            'country code' => (string) ($rate['country'] ?? ''),
            'state code' => (string) ($rate['state'] ?? ''),
            'postcode' => implode(', ', array_values(array_unique($postcodes))),
            'city' => implode(', ', array_values(array_unique($cities))),
            'rate' => (string) ($rate['rate'] ?? ''),
            'tax name' => (string) ($rate['name'] ?? 'WooCommerce tax rate'),
            'priority' => (string) ($rate['priority'] ?? '1'),
            'compound' => ! empty($rate['compound']) ? '1' : '0',
            'shipping' => ! empty($rate['shipping']) ? '1' : '0',
            'tax class' => (string) ($rate['class'] ?? 'standard'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $settings
     * @return array<string, mixed>
     */
    public function taxSettings(array $settings): array
    {
        $values = [];

        foreach ($settings as $setting) {
            if (is_array($setting) && isset($setting['id'])) {
                $values[(string) $setting['id']] = $setting['value'] ?? null;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, string>
     */
    public function customer(array $customer): array
    {
        $billing = (array) ($customer['billing'] ?? []);
        $shipping = (array) ($customer['shipping'] ?? []);
        $first = (string) ($customer['first_name'] ?? $billing['first_name'] ?? '');
        $last = (string) ($customer['last_name'] ?? $billing['last_name'] ?? '');

        $row = [
            'customer id' => (string) ($customer['id'] ?? ''),
            'email' => (string) ($customer['email'] ?? $billing['email'] ?? ''),
            'display name' => trim((string) ($customer['username'] ?? '')) ?: trim($first.' '.$last),
            'first name' => $first,
            'last name' => $last,
            'date created' => (string) ($customer['date_created'] ?? ''),
        ];

        foreach ($this->addressFields() as $key => $label) {
            $row['billing '.$label] = (string) ($billing[$key] ?? '');
            $row['shipping '.$label] = (string) ($shipping[$key] ?? '');
        }

        $row['billing phone'] = (string) ($billing['phone'] ?? '');
        $row['billing company'] = (string) ($billing['company'] ?? '');
        $row['tax id'] = (string) ($billing['vat'] ?? '');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, string>
     */
    public function order(array $order): array
    {
        $billing = (array) ($order['billing'] ?? []);
        $shipping = (array) ($order['shipping'] ?? []);
        $items = [];
        $subtotal = 0.0;
        $refundTotal = 0.0;

        foreach ((array) ($order['line_items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $subtotal += (float) ($item['subtotal'] ?? 0);
            $items[] = [
                'name' => (string) ($item['name'] ?? 'Imported item'),
                'product_id' => (string) ($item['product_id'] ?? ''),
                'variation_id' => (string) ($item['variation_id'] ?? ''),
                'quantity' => (string) ($item['quantity'] ?? 1),
                'subtotal' => (string) ($item['subtotal'] ?? 0),
                'total' => (string) ($item['total'] ?? 0),
                'tax' => (string) ($item['total_tax'] ?? 0),
                'sku' => (string) ($item['sku'] ?? ''),
            ];
        }

        foreach ((array) ($order['refunds'] ?? []) as $refund) {
            if (is_array($refund)) {
                $refundTotal += (float) ($refund['total'] ?? 0);
            }
        }

        $shippingLines = (array) ($order['shipping_lines'] ?? []);
        $shippingLine = is_array($shippingLines[0] ?? null) ? $shippingLines[0] : [];
        $coupons = [];

        foreach ((array) ($order['coupon_lines'] ?? []) as $coupon) {
            if (is_array($coupon) && ! empty($coupon['code'])) {
                $coupons[] = (string) $coupon['code'];
            }
        }

        $row = [
            'order id' => (string) ($order['id'] ?? ''),
            'order number' => (string) ($order['number'] ?? $order['id'] ?? ''),
            'order date' => (string) ($order['date_created'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'order currency' => (string) ($order['currency'] ?? ''),
            'order subtotal' => $this->money($subtotal),
            'order total' => (string) ($order['total'] ?? ''),
            'discount total' => (string) ($order['discount_total'] ?? ''),
            'shipping total' => (string) ($order['shipping_total'] ?? ''),
            'tax total' => (string) ($order['total_tax'] ?? ''),
            'refund total' => $this->money($refundTotal),
            'payment method' => (string) ($order['payment_method_title'] ?? $order['payment_method'] ?? ''),
            'transaction id' => (string) ($order['transaction_id'] ?? ''),
            'customer note' => (string) ($order['customer_note'] ?? ''),
            'date paid' => (string) ($order['date_paid'] ?? ''),
            'date completed' => (string) ($order['date_completed'] ?? ''),
            'coupon code' => implode(', ', $coupons),
            'shipping method' => (string) ($shippingLine['method_id'] ?? ''),
            'shipping method title' => (string) ($shippingLine['method_title'] ?? ''),
            'line items' => json_encode($items, JSON_THROW_ON_ERROR),
        ];

        foreach ($this->addressFields() as $key => $label) {
            $row['billing '.$label] = (string) ($billing[$key] ?? '');
            $row['shipping '.$label] = (string) ($shipping[$key] ?? '');
        }

        $row['billing phone'] = (string) ($billing['phone'] ?? '');
        $row['billing email'] = (string) ($billing['email'] ?? $order['billing']['email'] ?? '');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function parentReference(array $product): string
    {
        $sku = trim((string) ($product['sku'] ?? ''));

        if ($sku !== '') {
            return $sku;
        }

        return 'id:'.(string) ($product['id'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<array{name: string, value: string}>
     */
    private function attributes(array $entity): array
    {
        $attributes = [];

        foreach ((array) ($entity['attributes'] ?? []) as $attribute) {
            if (! is_array($attribute) || empty($attribute['name'])) {
                continue;
            }

            $values = (array) ($attribute['options'] ?? []);
            $value = implode(', ', array_filter(array_map('strval', $values)));

            if (isset($attribute['option'])) {
                $value = (string) $attribute['option'];
            }

            $attributes[] = [
                'name' => (string) $attribute['name'],
                'value' => $value !== '' ? $value : 'Any',
            ];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function images(array $entity): array
    {
        $images = [];

        foreach ((array) ($entity['images'] ?? []) as $image) {
            $src = is_array($image) ? ($image['src'] ?? null) : $image;

            if (is_string($src) && trim($src) !== '') {
                $images[] = trim($src);
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function categoryNames(array $entity): array
    {
        $names = [];

        foreach ((array) ($entity['categories'] ?? []) as $category) {
            if (is_array($category) && ! empty($category['name'])) {
                $names[] = (string) $category['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function addressFields(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'company' => 'company',
            'address_1' => 'address 1',
            'address_2' => 'address 2',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'country' => 'country',
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
