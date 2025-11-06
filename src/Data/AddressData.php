<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Deprecated;

class AddressData
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly string $address,
        public readonly string $postCode,
        public readonly string $countryCode = 'MYS',
        public readonly ?string $state = null,
        public readonly ?string $city = null,
        public readonly ?string $area = null,
        public readonly ?string $email = null,
        public readonly ?string $idCard = null,
    ) {}

    /**
     * Create from API response array
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            name: $data['name'],
            phone: $data['phone'],
            address: $data['address'],
            postCode: $data['postCode'],
            countryCode: $data['countryCode'] ?? 'MYS',
            state: $data['prov'] ?? null,
            city: $data['city'] ?? null,
            area: $data['area'] ?? null,
            email: $data['email'] ?? null,
            idCard: $data['idCard'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Deprecated(message: 'Use fromApiArray() instead')]
    public static function fromArray(array $data): self
    {
        return self::fromApiArray($data);
    }

    /**
     * Convert to API request array
     *
     * @return array<string, string>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'postCode' => $this->postCode,
            'countryCode' => $this->countryCode,
            'prov' => $this->state,
            'city' => $this->city,
            'area' => $this->area,
            'email' => $this->email,
            'idCard' => $this->idCard,
        ], fn (?string $value): bool => $value !== null);
    }

    /** @phpstan-ignore missingType.return */
    #[Deprecated(message: 'Use toApiArray() instead')]
    public function toArray()
    {
        return $this->toApiArray();
    }
}
