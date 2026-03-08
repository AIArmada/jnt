<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Address data for JNT Express shipments.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AddressData extends Data
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
     * Create from JNT API response array.
     *
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
     * Convert to JNT API request array.
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

    public function getFullAddress(): string
    {
        return collect([
            $this->address,
            $this->area,
            $this->city,
            $this->state,
            $this->postCode,
            $this->countryCode,
        ])->filter()->implode(', ');
    }
}
