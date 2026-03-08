<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

/**
 * Field Name Converter
 *
 * Converts developer-friendly field names to J&T Express API format.
 * This converter ensures consistency across all package operations by mapping
 * clean, intuitive property names to J&T's specific API field requirements.
 */
class FieldNameConverter
{
    /**
     * Convert clean field names to J&T API format.
     *
     * Maps developer-friendly property names to J&T's API field names throughout
     * the entire order structure (top-level, sender/receiver, items, packageInfo).
     *
     * Field name mappings:
     * - Order level: orderId → txlogisticId, trackingNumber → billCode
     * - Address fields: state → prov
     * - Item fields: name → itemName, quantity → number, price → itemValue, description → itemDesc
     * - Package fields: quantity → packageQuantity, value → packageValue
     *
     * @param  array<string, mixed>  $data  Order data with clean field names
     * @return array<string, mixed> Order data with J&T API field names
     *
     * @example
     * ```php
     * $cleanData = [
     *     'orderId' => 'ORDER123',
     *     'sender' => ['state' => 'Selangor'],
     *     'items' => [['name' => 'Widget', 'quantity' => 2]],
     * ];
     *
     * $apiData = FieldNameConverter::convert($cleanData);
     * // Result: ['txlogisticId' => 'ORDER123', 'sender' => ['prov' => 'Selangor'], ...]
     * ```
     */
    public static function convert(array $data): array
    {
        $converted = $data;

        // Order-level field mappings
        if (isset($data['orderId'])) {
            $converted['txlogisticId'] = $data['orderId'];
            unset($converted['orderId']);
        }

        if (isset($data['trackingNumber'])) {
            $converted['billCode'] = $data['trackingNumber'];
            unset($converted['trackingNumber']);
        }

        // Convert sender address fields
        if (isset($data['sender']) && is_array($data['sender'])) {
            $converted['sender'] = self::convertAddress($data['sender']);
        }

        // Convert receiver address fields
        if (isset($data['receiver']) && is_array($data['receiver'])) {
            $converted['receiver'] = self::convertAddress($data['receiver']);
        }

        // Convert items array
        if (isset($data['items']) && is_array($data['items'])) {
            $converted['items'] = array_map(
                fn ($item): mixed => is_array($item) ? self::convertItem($item) : $item,
                $data['items']
            );
        }

        // Convert packageInfo fields
        if (isset($data['packageInfo']) && is_array($data['packageInfo'])) {
            $converted['packageInfo'] = self::convertPackageInfo($data['packageInfo']);
        }

        return $converted;
    }

    /**
     * Convert address field names (sender/receiver)
     *
     * Maps clean address field names to J&T API format.
     *
     * Mappings: state → prov
     *
     * @param  array  $address  Address data with clean field names
     * @return array Address data with J&T API field names
     *
     * @example
     * ```php
     * $clean = ['name' => 'John Doe', 'state' => 'Selangor'];
     * $api = FieldNameConverter::convertAddress($clean);
     * // Result: ['name' => 'John Doe', 'prov' => 'Selangor']
     * ```
     */
    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    public static function convertAddress(array $address): array
    {
        $converted = $address;

        if (isset($address['state'])) {
            $converted['prov'] = $address['state'];
            unset($converted['state']);
        }

        return $converted;
    }

    /**
     * Convert item field names
     *
     * Maps clean item field names to J&T API format.
     *
     * Mappings:
     * - name → itemName
     * - quantity → number
     * - price → itemValue
     * - description → itemDesc
     *
     * @param  array  $item  Item data with clean field names
     * @return array Item data with J&T API field names
     *
     * @example
     * ```php
     * $clean = [
     *     'name' => 'Widget',
     *     'quantity' => 2,
     *     'price' => 99.99,
     *     'description' => 'Test product'
     * ];
     * $api = FieldNameConverter::convertItem($clean);
     * // Result: ['itemName' => 'Widget', 'number' => 2, 'itemValue' => 99.99, 'itemDesc' => 'Test product']
     * ```
     */
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function convertItem(array $item): array
    {
        $converted = $item;

        if (isset($item['name'])) {
            $converted['itemName'] = $item['name'];
            unset($converted['name']);
        }

        if (isset($item['quantity'])) {
            $converted['number'] = $item['quantity'];
            unset($converted['quantity']);
        }

        if (isset($item['price'])) {
            $converted['itemValue'] = $item['price'];
            unset($converted['price']);
        }

        if (isset($item['description'])) {
            $converted['itemDesc'] = $item['description'];
            unset($converted['description']);
        }

        return $converted;
    }

    /**
     * Convert packageInfo field names
     *
     * Maps clean package field names to J&T API format.
     *
     * Mappings:
     * - quantity → packageQuantity
     * - value → packageValue
     *
     * @param  array  $packageInfo  Package info data with clean field names
     * @return array Package info data with J&T API field names
     *
     * @example
     * ```php
     * $clean = ['quantity' => 1, 'value' => 100.00, 'weight' => 1.5];
     * $api = FieldNameConverter::convertPackageInfo($clean);
     * // Result: ['packageQuantity' => 1, 'packageValue' => 100.00, 'weight' => 1.5]
     * ```
     */
    /**
     * @param  array<string, mixed>  $packageInfo
     * @return array<string, mixed>
     */
    public static function convertPackageInfo(array $packageInfo): array
    {
        $converted = $packageInfo;

        if (isset($packageInfo['quantity'])) {
            $converted['packageQuantity'] = $packageInfo['quantity'];
            unset($converted['quantity']);
        }

        if (isset($packageInfo['value'])) {
            $converted['packageValue'] = $packageInfo['value'];
            unset($converted['value']);
        }

        return $converted;
    }

    /**
     * Get all field name mappings.
     *
     * Returns an array of all clean-to-API field name mappings used by this converter.
     * Useful for documentation, debugging, or building custom converters.
     *
     * @return array<string, array<string, string>> Mappings organized by context
     *
     * @example
     * ```php
     * $mappings = FieldNameConverter::getMappings();
     * // Returns:
     * // [
     * //     'order' => ['orderId' => 'txlogisticId', 'trackingNumber' => 'billCode'],
     * //     'address' => ['state' => 'prov'],
     * //     'item' => ['name' => 'itemName', 'quantity' => 'number', ...],
     * //     'package' => ['quantity' => 'packageQuantity', 'value' => 'packageValue'],
     * // ]
     * ```
     */
    public static function getMappings(): array
    {
        return [
            'order' => [
                'orderId' => 'txlogisticId',
                'trackingNumber' => 'billCode',
            ],
            'address' => [
                'state' => 'prov',
            ],
            'item' => [
                'name' => 'itemName',
                'quantity' => 'number',
                'price' => 'itemValue',
                'description' => 'itemDesc',
            ],
            'package' => [
                'quantity' => 'packageQuantity',
                'value' => 'packageValue',
            ],
        ];
    }
}
