<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Clean-Room Specification: Format Registry Contract.
 */
interface ImportFormatRegistryInterface extends Countable, IteratorAggregate
{
    public function all(): array;

    public function keys(): array;

    public function options(): array;

    public function get(string $key): DocumentImportFormat;
}

/**
 * Clean-Room Implementation: Catalogue and Discovery Registry for Import Formats.
 *
 * Maintains registered DocumentImportFormat drivers and resolves format instances by key.
 *
 * License: MIT
 */
class DocumentImportFormatRegistry implements ImportFormatRegistryInterface
{
    /** Registered format driver class names */
    protected array $registeredClasses;

    /** Instantiated format driver singletons */
    protected array $instantiatedInstances = [];

    public function __construct(?array $customFormatClasses = null)
    {
        $this->registeredClasses = $customFormatClasses ?? [
            DakhlStandardImportFormat::class,
            FreeAmirImportFormat::class,
            ParsianImportFormat::class,
        ];
    }

    /**
     * Instantiate all registered format driver singletons.
     *
     * @return array<int, DocumentImportFormat>
     */
    public function all(): array
    {
        if (empty($this->instantiatedInstances)) {
            foreach ($this->registeredClasses as $driverClass) {
                $this->instantiatedInstances[] = new $driverClass;
            }
        }

        return $this->instantiatedInstances;
    }

    /**
     * Enumerate unique machine identifiers across drivers.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        $identifiedKeys = [];
        foreach ($this->all() as $driverInstance) {
            $identifiedKeys[] = $driverInstance->key();
        }

        return $identifiedKeys;
    }

    /**
     * Produce key-value mapping of format identifier to translated human label for UI selects.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $dropdownChoices = [];
        foreach ($this->all() as $driver) {
            $dropdownChoices[$driver->key()] = $driver->label();
        }

        return $dropdownChoices;
    }

    /**
     * Resolve format processor matching specified key string.
     *
     * @throws InvalidArgumentException When key cannot be resolved
     */
    public function get(string $requestedKey): DocumentImportFormat
    {
        $sanitizedKey = trim($requestedKey);

        foreach ($this->all() as $candidateDriver) {
            if ($candidateDriver->key() === $sanitizedKey) {
                return $candidateDriver;
            }
        }

        throw new InvalidArgumentException(sprintf('Unrecognized import format specification: [%s]', $requestedKey));
    }

    /**
     * Determine whether a driver is registered for the specified key.
     */
    public function has(string $formatKey): bool
    {
        return in_array(trim($formatKey), $this->keys(), true);
    }

    /**
     * Return default primary interchange format.
     */
    public function defaultFormat(): DocumentImportFormat
    {
        return $this->get(DakhlStandardImportFormat::FORMAT_KEY);
    }

    /**
     * Discover matching format driver automatically by header signature inspection.
     *
     * @param  array<int, string>  $headerRow
     */
    public function detectMatchingFormat(array $headerRow): ?DocumentImportFormat
    {
        foreach ($this->all() as $driver) {
            if ($driver->matches($headerRow)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Append an external format driver class at runtime.
     *
     * @param  class-string<DocumentImportFormat>  $driverClass
     */
    public function registerFormatClass(string $driverClass): void
    {
        if (! in_array($driverClass, $this->registeredClasses, true)) {
            $this->registeredClasses[] = $driverClass;
            $this->instantiatedInstances = [];
        }
    }

    /**
     * Clear cached instances and reset to defaults.
     */
    public function reset(): void
    {
        $this->instantiatedInstances = [];
    }

    /**
     * Total number of registered formats.
     */
    public function count(): int
    {
        return count($this->registeredClasses);
    }

    /**
     * Iterate over format drivers.
     *
     * @return Traversable<int, DocumentImportFormat>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    /**
     * Map of format keys to detailed descriptions.
     *
     * @return array<string, string>
     */
    public function descriptions(): array
    {
        return [
            DakhlStandardImportFormat::FORMAT_KEY => 'Dakhl standard double-entry journal format with 14 columns',
            FreeAmirImportFormat::FORMAT_KEY => 'Legacy FreeAmir backward compatible interchange format',
            ParsianImportFormat::FORMAT_KEY => 'Parsian accounting software transaction ledgers and trial balances',
        ];
    }

    /**
     * Inspect validity of all registered driver class strings.
     */
    public function validateDriverClasses(): bool
    {
        foreach ($this->registeredClasses as $className) {
            if (! class_exists($className) || ! is_subclass_of($className, DocumentImportFormat::class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Export complete manifest of available drivers.
     *
     * @return array<string, array{key: string, label: string, class: string}>
     */
    public function exportManifest(): array
    {
        $manifest = [];
        foreach ($this->all() as $driver) {
            $manifest[$driver->key()] = [
                'key' => $driver->key(),
                'label' => $driver->label(),
                'class' => get_class($driver),
            ];
        }

        return $manifest;
    }
}
