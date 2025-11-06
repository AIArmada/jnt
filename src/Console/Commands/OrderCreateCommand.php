<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class OrderCreateCommand extends Command
{
    protected $signature = 'jnt:order:create
                          {--order-id= : Order ID}
                          {--sender-name= : Sender name}
                          {--sender-mobile= : Sender mobile}
                          {--receiver-name= : Receiver name}
                          {--receiver-mobile= : Receiver mobile}
                          {--receiver-address= : Receiver address}
                          {--item-name= : Item name}
                          {--item-qty=1 : Item quantity}
                          {--weight=1 : Package weight in kg}';

    protected $description = 'Create a new J&T Express order';

    public function handle(JntExpressService $jntExpress): int
    {
        try {
            // Get order details interactively
            $orderId = $this->option('order-id') ?? text('Enter Order ID', required: true);
            $senderName = $this->option('sender-name') ?? text('Enter Sender Name', required: true);
            $senderMobile = $this->option('sender-mobile') ?? text('Enter Sender Mobile', required: true);
            $receiverName = $this->option('receiver-name') ?? text('Enter Receiver Name', required: true);
            $receiverMobile = $this->option('receiver-mobile') ?? text('Enter Receiver Mobile', required: true);
            $receiverAddress = $this->option('receiver-address') ?? text('Enter Receiver Address', required: true);
            $itemName = $this->option('item-name') ?? text('Enter Item Name', required: true);
            $itemQty = (int) ($this->option('item-qty') ?? text('Enter Item Quantity', default: '1', required: true));
            $weight = (float) ($this->option('weight') ?? text('Enter Weight (kg)', default: '1.0', required: true));

            // Create data objects
            $sender = new AddressData(
                name: $senderName,
                phone: $senderMobile,
                address: 'Default Sender Address',
                postCode: '50000'
            );

            $receiver = new AddressData(
                name: $receiverName,
                phone: $receiverMobile,
                address: $receiverAddress,
                postCode: '50000'
            );

            $items = [
                new ItemData(
                    name: $itemName,
                    quantity: $itemQty,
                    weight: 100, // 100 grams default
                    price: 0
                ),
            ];

            $packageInfo = new PackageInfoData(
                quantity: 1,
                weight: $weight,
                value: 0,
                goodsType: GoodsType::PACKAGE
            );

            $this->line('Creating order...');
            $order = $jntExpress->createOrder($sender, $receiver, $items, $packageInfo, $orderId);

            $this->info('✓ Order created successfully!');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Order ID', $order->orderId],
                    ['Tracking Number', $order->txlogisticId ?? 'N/A'],
                    ['Status', 'Created'],
                ]
            );

            return self::SUCCESS;
        } catch (JntValidationException $e) {
            $this->error('Validation Error: '.$e->getMessage());

            if ($e->errors) {
                $this->warn('Validation Errors:');
                foreach ($e->errors as $field => $errors) {
                    foreach ($errors as $error) {
                        $this->line("  - {$field}: {$error}");
                    }
                }
            }

            return self::FAILURE;
        } catch (JntNetworkException $e) {
            $this->error('Network Error: '.$e->getMessage());
            $this->warn('Please check your internet connection and try again.');

            return self::FAILURE;
        } catch (JntApiException $e) {
            $this->error('API Error: '.$e->getMessage());

            if ($e->errorCode) {
                $this->warn('Error Code: '.$e->errorCode);
            }

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Unexpected Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
