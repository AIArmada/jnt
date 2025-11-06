<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Builders;

use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\ExpressType;
use AIArmada\Jnt\Enums\PaymentType;
use AIArmada\Jnt\Enums\ServiceType;
use AIArmada\Jnt\Exceptions\JntException;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Rules\DimensionInCentimeters;
use AIArmada\Jnt\Rules\MalaysianPostalCode;
use AIArmada\Jnt\Rules\MonetaryValue;
use AIArmada\Jnt\Rules\PhoneNumber;
use AIArmada\Jnt\Rules\WeightInGrams;
use AIArmada\Jnt\Rules\WeightInKilograms;
use Illuminate\Support\Facades\Validator;

class OrderBuilder
{
    protected string $orderId;

    protected ExpressType|string $expressType = 'EZ';

    protected ServiceType|string $serviceType = '1';

    protected PaymentType|string $paymentType = 'PP_PM';

    protected ?AddressData $sender = null;

    protected ?AddressData $receiver = null;

    protected ?AddressData $returnAddress = null;

    /** @var array<ItemData> */
    protected array $items = [];

    protected ?PackageInfoData $packageInfo = null;

    protected ?string $pickupStartTime = null;

    protected ?string $pickupEndTime = null;

    protected ?string $insuranceValue = null;

    protected ?string $cashOnDeliveryAmount = null;

    protected ?string $remark = null;

    /** @var array<string, mixed>|null */
    protected ?array $customsInfo = null;

    /** @var array<string, mixed>|null */
    protected ?array $additionalParcels = null;

    public function __construct(protected string $customerCode, protected string $password) {}

    public function orderId(string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function expressType(ExpressType|string $expressType): self
    {
        $this->expressType = $expressType;

        return $this;
    }

    public function serviceType(ServiceType|string $serviceType): self
    {
        $this->serviceType = $serviceType;

        return $this;
    }

    public function paymentType(PaymentType|string $paymentType): self
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    public function sender(AddressData $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function receiver(AddressData $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function returnAddress(AddressData $returnAddress): self
    {
        $this->returnAddress = $returnAddress;

        return $this;
    }

    public function addItem(ItemData $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param  array<ItemData>  $items
     */
    public function items(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function packageInfo(PackageInfoData $packageInfo): self
    {
        $this->packageInfo = $packageInfo;

        return $this;
    }

    public function pickupStartTime(string $pickupStartTime): self
    {
        $this->pickupStartTime = $pickupStartTime;

        return $this;
    }

    public function pickupEndTime(string $pickupEndTime): self
    {
        $this->pickupEndTime = $pickupEndTime;

        return $this;
    }

    public function insurance(float|string $insuranceValue): self
    {
        $this->insuranceValue = (string) $insuranceValue;

        return $this;
    }

    public function cashOnDelivery(float|string $cashOnDeliveryAmount): self
    {
        $this->cashOnDeliveryAmount = (string) $cashOnDeliveryAmount;

        return $this;
    }

    public function remark(string $remark): self
    {
        $this->remark = $remark;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $customsInfo
     */
    public function customsInfo(array $customsInfo): self
    {
        $this->customsInfo = $customsInfo;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $additionalParcels
     */
    public function additionalParcels(array $additionalParcels): self
    {
        $this->additionalParcels = $additionalParcels;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $this->validate();

        $expressTypeValue = $this->expressType instanceof ExpressType
            ? $this->expressType->value
            : $this->expressType;

        $serviceTypeValue = $this->serviceType instanceof ServiceType
            ? $this->serviceType->value
            : $this->serviceType;

        $paymentTypeValue = $this->paymentType instanceof PaymentType
            ? $this->paymentType->value
            : $this->paymentType;

        $payload = [
            'customerCode' => $this->customerCode,
            'password' => $this->password,
            'txlogisticId' => $this->orderId,
            'actionType' => 'add',
            'serviceType' => $serviceTypeValue,
            'payType' => $paymentTypeValue,
            'expressType' => $expressTypeValue,
            'sender' => $this->sender->toApiArray(),
            'receiver' => $this->receiver->toApiArray(),
            'items' => array_map(fn (ItemData $item): array => $item->toApiArray(), $this->items),
            'packageInfo' => $this->packageInfo->toApiArray(),
        ];

        if ($this->returnAddress instanceof AddressData) {
            $payload['returnInfo'] = $this->returnAddress->toApiArray();
        }

        if ($this->pickupStartTime !== null) {
            $payload['sendStartTime'] = $this->pickupStartTime;
        }

        if ($this->pickupEndTime !== null) {
            $payload['sendEndTime'] = $this->pickupEndTime;
        }

        if ($this->insuranceValue !== null) {
            $payload['offerFeeInfo'] = ['offerValue' => $this->insuranceValue];
        }

        if ($this->cashOnDeliveryAmount !== null) {
            $payload['codInfo'] = ['codValue' => $this->cashOnDeliveryAmount];
        }

        if ($this->remark !== null) {
            $payload['remark'] = $this->remark;
        }

        if ($this->customsInfo !== null) {
            $payload['customsInfo'] = $this->customsInfo;
        }

        if ($this->additionalParcels !== null) {
            $payload['multipleVotes'] = $this->additionalParcels;
        }

        return $payload;
    }

    /**
     * Validate the order data using Laravel's built-in validator.
     *
     * This method leverages Laravel's powerful validation system with custom Rule objects
     * for domain-specific validation logic. Validation failures throw a JntException
     * with all error messages combined.
     *
     * @throws JntException
     */
    protected function validate(): void
    {
        // Build validation data array
        $data = $this->buildValidationData();

        // Define validation rules
        $rules = $this->buildValidationRules();

        // Create validator instance
        $validator = Validator::make($data, $rules);

        // Custom attribute names for better error messages
        $validator->setAttributeNames([
            'order_id' => 'orderId',
            'sender' => 'Sender address',
            'receiver' => 'Receiver address',
            'items' => 'items',
            'package_info' => 'Package info',
            'sender_name' => 'Sender name',
            'sender_phone' => 'Sender phone',
            'sender_address' => 'Sender address',
            'sender_post_code' => 'Sender postCode',
            'receiver_name' => 'Receiver name',
            'receiver_phone' => 'Receiver phone',
            'receiver_address' => 'Receiver address',
            'receiver_post_code' => 'Receiver postCode',
            'package_weight' => 'Package weight',
            'package_quantity' => 'Package quantity',
            'package_value' => 'Package value',
            'package_length' => 'Package length',
            'package_width' => 'Package width',
            'package_height' => 'Package height',
            'remark' => 'Remark',
            'insurance_value' => 'Insurance value',
            'cod_amount' => 'Cash on delivery amount',
            'items.*.name' => 'Item #:position name',
            'items.*.quantity' => 'Item #:position quantity',
            'items.*.weight' => 'Item #:position weight',
            'items.*.price' => 'Item #:position price',
            'items.*.description' => 'Item #:position description',
        ]);

        // Custom error messages to match expected format
        $validator->setCustomMessages([
            'order_id.required' => ':attribute is required',
            'order_id.max' => ':attribute must not exceed :max characters',
            'sender.required' => ':attribute is required',
            'receiver.required' => ':attribute is required',
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
            'package_info.required' => ':attribute is required',
            'sender_name.max' => ':attribute must not exceed :max characters',
            'receiver_name.max' => ':attribute must not exceed :max characters',
            'sender_address.max' => ':attribute must not exceed :max characters',
            'receiver_address.max' => ':attribute must not exceed :max characters',
            'remark.max' => ':attribute must not exceed :max characters',
            'items.*.name.max' => ':attribute must not exceed :max characters',
            'items.*.description.max' => ':attribute must not exceed :max characters',
            'items.*.quantity.min' => ':attribute must be between :min and 999',
            'items.*.quantity.max' => ':attribute must be between 1 and :max',
        ]);

        // Validate and throw exception if validation fails
        if ($validator->fails()) {
            // Get all errors
            $errors = $validator->errors()->toArray();
            $firstError = $validator->errors()->first();

            // Extract field name from first error key
            $field = array_key_first($errors) ?? 'unknown';

            throw JntValidationException::fieldValidationFailed($field, $firstError, $errors);
        }
    }

    /**
     * Build the data array for validation.
     *
     * @return array<string, mixed>
     */
    protected function buildValidationData(): array
    {
        $data = [
            'order_id' => $this->orderId ?? null,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'items' => [],
            'package_info' => $this->packageInfo,
        ];

        // Add sender data if available
        if ($this->sender instanceof AddressData) {
            $data['sender_name'] = $this->sender->name;
            $data['sender_phone'] = $this->sender->phone;
            $data['sender_address'] = $this->sender->address;
            $data['sender_post_code'] = $this->sender->postCode;
        }

        // Add receiver data if available
        if ($this->receiver instanceof AddressData) {
            $data['receiver_name'] = $this->receiver->name;
            $data['receiver_phone'] = $this->receiver->phone;
            $data['receiver_address'] = $this->receiver->address;
            $data['receiver_post_code'] = $this->receiver->postCode;
        }

        // Add return address data if provided
        if ($this->returnAddress instanceof AddressData) {
            $data['return_address_name'] = $this->returnAddress->name;
            $data['return_address_phone'] = $this->returnAddress->phone;
            $data['return_address_address'] = $this->returnAddress->address;
            $data['return_address_post_code'] = $this->returnAddress->postCode;
        }

        // Add package info data if available
        if ($this->packageInfo instanceof PackageInfoData) {
            $data['package_weight'] = $this->packageInfo->weight;
            $data['package_quantity'] = $this->packageInfo->quantity;
            $data['package_value'] = $this->packageInfo->value;

            if ($this->packageInfo->length !== null) {
                $data['package_length'] = $this->packageInfo->length;
            }

            if ($this->packageInfo->width !== null) {
                $data['package_width'] = $this->packageInfo->width;
            }

            if ($this->packageInfo->height !== null) {
                $data['package_height'] = $this->packageInfo->height;
            }
        }

        // Add items data as nested arrays for proper Laravel validation
        foreach ($this->items as $item) {
            $itemData = [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'weight' => $item->weight,
                'price' => $item->price,
            ];

            if ($item->description !== null) {
                $itemData['description'] = $item->description;
            }

            $data['items'][] = $itemData;
        }

        // Add optional fields
        if ($this->remark !== null) {
            $data['remark'] = $this->remark;
        }

        if ($this->insuranceValue !== null) {
            $data['insurance_value'] = $this->insuranceValue;
        }

        if ($this->cashOnDeliveryAmount !== null) {
            $data['cod_amount'] = $this->cashOnDeliveryAmount;
        }

        return $data;
    }

    /**
     * Build the validation rules array.
     *
     * Uses Laravel's validation rules and custom Rule objects for domain-specific validation.
     *
     * @return array<string, mixed>
     */
    protected function buildValidationRules(): array
    {
        $rules = [
            // Required fields
            'order_id' => ['required', 'string', 'max:50'], // API limit: txlogisticId max 50 chars
            'sender' => ['required'],
            'receiver' => ['required'],
            'items' => ['required', 'array', 'min:1'],
            'package_info' => ['required'],
        ];

        // Only add detailed validation rules if the objects are not null
        if ($this->sender instanceof AddressData) {
            $rules['sender_name'] = ['required', 'string', 'max:200'];
            $rules['sender_phone'] = ['required', 'string', new PhoneNumber];
            $rules['sender_address'] = ['required', 'string', 'max:200'];
            $rules['sender_post_code'] = ['required', 'string', new MalaysianPostalCode];
        }

        if ($this->receiver instanceof AddressData) {
            $rules['receiver_name'] = ['required', 'string', 'max:200'];
            $rules['receiver_phone'] = ['required', 'string', new PhoneNumber];
            $rules['receiver_address'] = ['required', 'string', 'max:200'];
            $rules['receiver_post_code'] = ['required', 'string', new MalaysianPostalCode];
        }

        if ($this->packageInfo instanceof PackageInfoData) {
            $rules['package_weight'] = ['required', 'numeric', new WeightInKilograms];
            $rules['package_quantity'] = ['required', 'integer', 'min:1', 'max:999'];
            $rules['package_value'] = ['required', 'numeric', new MonetaryValue];
            $rules['package_length'] = ['nullable', 'numeric', new DimensionInCentimeters];
            $rules['package_width'] = ['nullable', 'numeric', new DimensionInCentimeters];
            $rules['package_height'] = ['nullable', 'numeric', new DimensionInCentimeters];
        }

        // Add return address validation if provided
        if ($this->returnAddress instanceof AddressData) {
            $rules['return_address_name'] = ['required', 'string', 'max:200'];
            $rules['return_address_phone'] = ['required', 'string', new PhoneNumber];
            $rules['return_address_address'] = ['required', 'string', 'max:200'];
            $rules['return_address_post_code'] = ['required', 'string', new MalaysianPostalCode];
        }

        // Add items validation rules using wildcard (only if items exist)
        if ($this->items !== []) {
            $rules['items.*.name'] = ['required', 'string', 'max:200'];
            $rules['items.*.quantity'] = ['required', 'integer', 'min:1', 'max:999'];
            $rules['items.*.weight'] = ['required', 'numeric', new WeightInGrams];
            $rules['items.*.price'] = ['required', 'numeric', new MonetaryValue];
            $rules['items.*.description'] = ['nullable', 'string', 'max:500'];
        }

        // Optional fields
        if ($this->remark !== null) {
            $rules['remark'] = ['string', 'max:300']; // API limit: max 300 chars
        }

        if ($this->insuranceValue !== null) {
            $rules['insurance_value'] = ['numeric', new MonetaryValue];
        }

        if ($this->cashOnDeliveryAmount !== null) {
            $rules['cod_amount'] = ['numeric', new MonetaryValue];
        }

        return $rules;
    }
}
