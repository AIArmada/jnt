<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console;

use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Http\JntClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;

abstract class JntCommand extends Command
{
    private ?JntClient $resolvedClient = null;

    protected function client(): JntClient
    {
        if ($this->resolvedClient === null) {
            try {
                $client = app(JntClient::class);
            } catch (BindingResolutionException) {
                $config = config('jnt');
                $baseUrl = ($config['environment'] ?? 'production') === 'production'
                    ? ($config['base_urls']['production'] ?? '')
                    : ($config['base_urls']['testing'] ?? '');

                $client = new JntClient(
                    baseUrl: $baseUrl,
                    apiAccount: $config['api_account'] ?? '',
                    privateKey: $config['private_key'] ?? '',
                    config: $config,
                );
            }

            $this->resolvedClient = $client;
        }

        return $this->resolvedClient;
    }

    protected function withErrorHandling(callable $callback): int
    {
        try {
            $result = $callback();

            return is_int($result) ? $result : self::SUCCESS;
        } catch (JntApiException $e) {
            $this->error('API Error: ' . $e->getMessage());

            if ($e->errorCode !== null) {
                $this->warn('Error Code: ' . $e->errorCode);
            }

            return self::FAILURE;
        } catch (JntNetworkException $e) {
            $this->error('Network Error: ' . $e->getMessage());
            $this->warn('Please check your internet connection and try again.');

            return self::FAILURE;
        } catch (JntValidationException $e) {
            $this->error('Validation Error: ' . $e->getMessage());

            if ($e->errors !== []) {
                $this->warn('Validation Errors:');
                foreach ($e->errors as $field => $errors) {
                    foreach ($errors as $error) {
                        $this->line("  - {$field}: {$error}");
                    }
                }
            }

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    protected function infoWithLabel(string $label, string $message): void
    {
        $this->info(sprintf('[%s] %s', $label, $message));
    }

    protected function section(string $heading): void
    {
        $this->newLine();
        $this->info($heading);
        $this->line(str_repeat('─', mb_strlen($heading)));
    }

    protected function resultTable(array $rows): void
    {
        $this->table(
            ['Key', 'Value'],
            collect($rows)->map(fn (mixed $value, string | int $key): array => [is_string($key) ? $key : '', (string) $value])->values()->toArray(),
        );
    }

    protected function success(string $label, string $detail = ''): void
    {
        $message = $detail !== '' ? "{$label}: {$detail}" : $label;
        $this->info('✓ ' . $message);
    }

    protected function failure(string $label, string $detail = ''): void
    {
        $message = $detail !== '' ? "{$label}: {$detail}" : $label;
        $this->error('✗ ' . $message);
    }
}
