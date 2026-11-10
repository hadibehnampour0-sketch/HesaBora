<?php

namespace App\Services\Payroll;

/**
 * Pure PHP dBase III (.DBF) Binary File Generator
 * Compliant with Iranian Social Security Organization (Tamin Ejtemaei) diskette specification.
 */
class DbfFileWriter
{
    /** @var array<array{name: string, type: string, length: int, decimals: int}> */
    protected array $fields = [];

    /** @var array<array<string, mixed>> */
    protected array $records = [];

    /**
     * Add a field descriptor to the DBF table.
     *
     * @param  string  $name  Field name (up to 10 chars ASCII)
     * @param  string  $type  Field type ('C' for Character, 'N' for Numeric, 'D' for Date)
     * @param  int  $length  Total length in bytes
     * @param  int  $decimals  Decimal places for Numeric
     */
    public function addField(string $name, string $type, int $length, int $decimals = 0): self
    {
        $cleanName = strtoupper(substr(preg_replace('/[^a-zA-Z0-9_]/', '', $name), 0, 10));
        $this->fields[] = [
            'name' => $cleanName,
            'type' => strtoupper($type),
            'length' => $length,
            'decimals' => $decimals,
        ];

        return $this;
    }

    /**
     * Add a record row to the table.
     */
    public function addRecord(array $row): self
    {
        $this->records[] = $row;

        return $this;
    }

    /**
     * Calculate record length (1 byte delete flag + sum of all field lengths).
     */
    public function getRecordLength(): int
    {
        $length = 1;
        foreach ($this->fields as $field) {
            $length += $field['length'];
        }

        return $length;
    }

    /**
     * Calculate header length (32 bytes header + 32 bytes per field descriptor + 1 byte 0x0D terminator).
     */
    public function getHeaderLength(): int
    {
        return 32 + (count($this->fields) * 32) + 1;
    }

    /**
     * Build the raw binary DBF content.
     */
    public function build(): string
    {
        $recordCount = count($this->records);
        $headerLength = $this->getHeaderLength();
        $recordLength = $this->getRecordLength();

        $now = getdate();
        $yy = $now['year'] >= 2000 ? $now['year'] - 2000 : $now['year'] - 1900;
        $mm = $now['mon'];
        $dd = $now['mday'];

        // 1. Write 32-byte DBF File Header
        $buffer = pack(
            'CCCCVvv',
            0x03,          // Version: dBASE III without memo
            $yy,           // Last update YY
            $mm,           // Last update MM
            $dd,           // Last update DD
            $recordCount,  // Number of records (32-bit little-endian)
            $headerLength, // Header length (16-bit little-endian)
            $recordLength  // Record length (16-bit little-endian)
        );
        $buffer .= str_repeat("\x00", 20); // 20 bytes reserved

        // 2. Write 32-byte Field Descriptors
        foreach ($this->fields as $field) {
            $namePadded = str_pad($field['name'], 11, "\x00"); // 11 bytes null-padded
            $buffer .= $namePadded;
            $buffer .= pack('C', ord($field['type']));         // 1 byte Type ('C', 'N', 'D')
            $buffer .= str_repeat("\x00", 4);                 // 4 bytes reserved
            $buffer .= pack('C', $field['length']);           // 1 byte Field length
            $buffer .= pack('C', $field['decimals']);         // 1 byte Decimal count
            $buffer .= str_repeat("\x00", 14);                // 14 bytes reserved
        }

        // 3. Header Terminator
        $buffer .= "\x0D";

        // 4. Data Records
        foreach ($this->records as $record) {
            // Deletion flag: 0x20 = space (active record)
            $buffer .= ' ';

            foreach ($this->fields as $field) {
                $fieldName = $field['name'];
                $val = $record[$fieldName] ?? ($record[strtolower($fieldName)] ?? '');
                $type = $field['type'];
                $targetLen = $field['length'];

                if ($type === 'N') {
                    // Numeric: Right-aligned, space-padded
                    $numStr = '';
                    if ($field['decimals'] > 0) {
                        $numStr = number_format((float) $val, $field['decimals'], '.', '');
                    } else {
                        $numStr = (string) (int) round((float) $val);
                    }
                    if (strlen($numStr) > $targetLen) {
                        $numStr = substr($numStr, 0, $targetLen);
                    }
                    $buffer .= str_pad($numStr, $targetLen, ' ', STR_PAD_LEFT);
                } else {
                    // Character ('C') or Date ('D')
                    $strVal = (string) $val;

                    // Convert UTF-8 to Windows-1256 (standard Persian encoding for Tamin Ejtemaei DBF)
                    if (function_exists('iconv')) {
                        $converted = @iconv('UTF-8', 'Windows-1256//IGNORE', $strVal);
                        if ($converted !== false) {
                            $strVal = $converted;
                        }
                    }

                    $currentLen = strlen($strVal);
                    if ($currentLen > $targetLen) {
                        $strVal = substr($strVal, 0, $targetLen);
                    } else {
                        $strVal = str_pad($strVal, $targetLen, ' ', STR_PAD_RIGHT);
                    }

                    $buffer .= $strVal;
                }
            }
        }

        // 5. End of File (EOF) Terminator
        $buffer .= "\x1A";

        return $buffer;
    }

    /**
     * Save DBF directly to a disk path.
     */
    public function save(string $filePath): bool
    {
        return file_put_contents($filePath, $this->build()) !== false;
    }
}
