<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class AutoUnknownDatabaseAdapter implements MigrationAdapterInterface
{
    /**
     * Persian & English keyword dictionaries for entity classification.
     */
    protected array $customerKeywords = [
        'table' => [
            'customer', 'customers', 'client', 'clients', 'person', 'persons', 'party', 'parties',
            'ashkhas', 'moshtari', 'moshtarian', 'taraf_hesab', 'tarafhesab', 'hesabha', 'hesab',
            'taraf', 'hesab_tafsili', 'tafsili', 'people', 'contacts', 'buyers', 'suppliers',
            'vendor', 'vendors', 'taminkonandeh', 'mosh_list', 'tbl_person', 'tbl_customer',
            'طرف_حساب', 'مشتریان', 'اشخاص', 'طرف‌حساب', 'تامین_کنندگان', 'حساب_ها',
        ],
        'fields' => [
            'name' => ['name', 'full_name', 'fullname', 'c_name', 'title', 'partyname', 'person_name', 'customer_name', 'sharh', 'nam', 'نام', 'عنوان', 'نام_خانوادگی', 'نام_شخص', 'نام_مشتری', 'طرف_حساب'],
            'mobile' => ['mobile', 'cell', 'phone', 'tell', 'tel', 'phone_number', 'c_mobile', 'contact', 'telephone', 'همراه', 'موبایل', 'تلفن', 'شماره_همراه', 'شماره_تماس'],
            'national_code' => ['national_code', 'national_id', 'melli', 'codemelli', 'c_melli', 'meli', 'economic_code', 'shenase_melli', 'کدملی', 'کد_ملی', 'شناسه_ملی', 'کد_اقتصادی'],
            'balance' => ['balance', 'remain', 'mandeh', 'mande', 'baghimandeh', 'bed_bes', 'hesab_balance', 'مانده', 'تراز', 'باقیمانده', 'مانده_حساب'],
            'debit' => ['debit', 'bed', 'c_bed', 'bedehkar', 'bedehkari', 'بدهکار', 'بدهکاری'],
            'credit' => ['credit', 'bes', 'c_bes', 'bestankar', 'bestankari', 'بستانکار', 'بستانکاری'],
            'address' => ['address', 'addr', 'neshani', 'address_full', 'آدرس', 'نشانی'],
            'group' => ['group', 'category', 'group_name', 'party_group', 'dasteh', 'گروه', 'دسته_بندی'],
        ],
    ];

    protected array $productKeywords = [
        'table' => [
            'product', 'products', 'item', 'items', 'goods', 'merchandise', 'article', 'articles',
            'kala', 'kalas', 'ajnas', 'anbar', 'store', 'inventory', 'stock', 'stock_items',
            'tbl_kala', 'tbl_products', 'tbl_goods', 'tbl_anbar', 'commodities', 'warehouse_stock',
            'کالا', 'کالاها', 'انبار', 'موجودی', 'اجناس', 'محصولات',
        ],
        'fields' => [
            'name' => ['name', 'item_name', 'product_name', 'k_name', 'title', 'sharh', 'description', 'kala_name', 'نام', 'عنوان', 'شرح_کالا', 'نام_کالا', 'عنوان_محصول'],
            'code' => ['code', 'item_code', 'product_code', 'k_code', 'sku', 'part_number', 'کد', 'کد_کالا', 'کد_محصول', 'شناسه_کالا'],
            'barcode' => ['barcode', 'bar_code', 'k_barcode', 'upc', 'ean', 'barcodes', 'بارکد', 'کد_میله_ای'],
            'unit' => ['unit', 'unit_name', 'vahed', 'k_vahed', 'measuring_unit', 'واحد', 'واحد_شمارش'],
            'purchase_price' => ['purchase_price', 'buy_price', 'cost_price', 'k_price_buy', 'cost', 'fi_kharid', 'gheymat_kharid', 'قیمت_خرید', 'فی_خرید', 'بهای_تمام_شده'],
            'sell_price' => ['sell_price', 'sale_price', 'price', 'k_price_sell', 'selling_price', 'fi_forosh', 'gheymat_forosh', 'قیمت_فروش', 'فی_فروش', 'نرخ_فروش'],
            'stock' => ['stock', 'initial_stock', 'quantity', 'qty', 'mojoodi', 'k_mojoodi', 'count', 'available_stock', 'موجودی', 'موجودی_اولیه', 'تعداد', 'موجودی_انبار'],
            'group' => ['group', 'category', 'group_name', 'item_group', 'dasteh', 'گروه', 'گروه_کالا', 'دسته_بندی'],
        ],
    ];

    protected array $chequeKeywords = [
        'table' => [
            'cheque', 'cheques', 'check', 'checks', 'asnad', 'sanad_chq', 'chq', 'promissory',
            'tbl_cheque', 'tbl_asnad', 'treasury_cheques', 'received_cheques', 'paid_cheques',
            'چک', 'چکها', 'چک_ها', 'اسناد', 'اسناد_دریافتنی', 'اسناد_پرداختنی', 'خزانه',
        ],
        'fields' => [
            'number' => ['cheque_number', 'number', 'serial', 'chq_number', 'shomare', 'شماره_چک', 'سریال_چک', 'شماره_سند', 'سریال'],
            'sayad' => ['sayad', 'sayad_number', 'chq_sayad', 'sayadi', 'shomare_sayad', 'صیاد', 'شماره_صیاد', 'کد_صیادی'],
            'amount' => ['amount', 'mablagh', 'chq_mablagh', 'price', 'value', 'مبلغ', 'مبلغ_چک', 'ارزش'],
            'due_date' => ['due_date', 'sarresid', 'tarikh_sarresid', 'chq_tarikh_sarresid', 'expire_date', 'تاریخ_سررسید', 'سررسید'],
            'issue_date' => ['write_date', 'issue_date', 'tarikh_sodor', 'chq_tarikh_sodor', 'date', 'تاریخ_صدور', 'تاریخ_ثبت'],
            'direction' => ['direction', 'type', 'noe', 'chq_noe', 'cheque_type', 'kind', 'نوع_چک', 'جهت', 'دریافتی_پرداختی'],
            'bank' => ['bank', 'bank_name', 'chq_bank', 'hesab_bank', 'بانک', 'نام_بانک'],
            'customer' => ['customer_name', 'payer', 'party_name', 'chq_tarafhesab', 'dar_vajh', 'صاحب_چک', 'در_وجه', 'طرف_حساب'],
        ],
    ];

    public function getName(): string
    {
        return 'auto_unknown';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        // Catches auto_unknown, unknown, or any unrecognized dataset format
        if (in_array($sourceType, ['auto_unknown', 'unknown', 'auto', 'smart_detect', 'sql_dump'])) {
            return true;
        }

        return true;
    }

    public function parse(array|string $data): MigrationPayload
    {
        $tables = $this->normalizeInputIntoTables($data);

        $classifiedTables = $this->classifyTables($tables);
        $inferredMappings = [];

        // 1. Process Customers
        $customers = [];
        if (! empty($classifiedTables['customer'])) {
            $tableData = $tables[$classifiedTables['customer']['table_name']];
            $fieldMap = $this->mapFields($tableData, $this->customerKeywords['fields']);
            $inferredMappings['customers'] = [
                'source_table' => $classifiedTables['customer']['table_name'],
                'confidence' => $classifiedTables['customer']['confidence'],
                'field_map' => $fieldMap,
            ];

            foreach ($tableData as $row) {
                $name = trim($this->extractFieldValue($row, $fieldMap['name'] ?? null) ?? '');
                if (empty($name)) {
                    continue;
                }

                $balance = 0.0;
                if (! empty($fieldMap['balance'])) {
                    $balance = (float) $this->extractFieldValue($row, $fieldMap['balance']);
                } elseif (! empty($fieldMap['debit']) || ! empty($fieldMap['credit'])) {
                    $bed = (float) ($this->extractFieldValue($row, $fieldMap['debit'] ?? null) ?? 0);
                    $bes = (float) ($this->extractFieldValue($row, $fieldMap['credit'] ?? null) ?? 0);
                    $balance = $bed - $bes;
                }

                $mobile = $this->extractFieldValue($row, $fieldMap['mobile'] ?? null);
                // Regex fallback for mobile in any string field if null
                if (empty($mobile)) {
                    $mobile = $this->findValueMatchingRegex($row, '/^(\+98|0)?9\d{9}$/');
                }

                $nationalCode = $this->extractFieldValue($row, $fieldMap['national_code'] ?? null);

                $customers[] = [
                    'name' => $name,
                    'mobile' => $mobile ? trim((string) $mobile) : null,
                    'national_code' => $nationalCode ? trim((string) $nationalCode) : null,
                    'balance' => $balance,
                    'is_creditor' => $balance < 0,
                    'group_name' => $this->extractFieldValue($row, $fieldMap['group'] ?? null) ?: 'مشتریان انتقالی هوشمند',
                    'address' => $this->extractFieldValue($row, $fieldMap['address'] ?? null),
                ];
            }
        }

        // 2. Process Products
        $products = [];
        if (! empty($classifiedTables['product'])) {
            $tableData = $tables[$classifiedTables['product']['table_name']];
            $fieldMap = $this->mapFields($tableData, $this->productKeywords['fields']);
            $inferredMappings['products'] = [
                'source_table' => $classifiedTables['product']['table_name'],
                'confidence' => $classifiedTables['product']['confidence'],
                'field_map' => $fieldMap,
            ];

            foreach ($tableData as $row) {
                $name = trim($this->extractFieldValue($row, $fieldMap['name'] ?? null) ?? '');
                if (empty($name)) {
                    continue;
                }

                $purchasePrice = (float) ($this->extractFieldValue($row, $fieldMap['purchase_price'] ?? null) ?? 0);
                $sellPrice = (float) ($this->extractFieldValue($row, $fieldMap['sell_price'] ?? null) ?? 0);
                $stock = (float) ($this->extractFieldValue($row, $fieldMap['stock'] ?? null) ?? 0);

                $products[] = [
                    'name' => $name,
                    'code' => trim((string) ($this->extractFieldValue($row, $fieldMap['code'] ?? null) ?? '')),
                    'barcode' => trim((string) ($this->extractFieldValue($row, $fieldMap['barcode'] ?? null) ?? '')),
                    'unit' => $this->extractFieldValue($row, $fieldMap['unit'] ?? null) ?: 'عدد',
                    'purchase_price' => $purchasePrice,
                    'sell_price' => $sellPrice,
                    'initial_stock' => $stock,
                    'group_name' => $this->extractFieldValue($row, $fieldMap['group'] ?? null) ?: 'کالاهای انتقالی هوشمند',
                ];
            }
        }

        // 3. Process Cheques
        $cheques = [];
        if (! empty($classifiedTables['cheque'])) {
            $tableData = $tables[$classifiedTables['cheque']['table_name']];
            $fieldMap = $this->mapFields($tableData, $this->chequeKeywords['fields']);
            $inferredMappings['cheques'] = [
                'source_table' => $classifiedTables['cheque']['table_name'],
                'confidence' => $classifiedTables['cheque']['confidence'],
                'field_map' => $fieldMap,
            ];

            foreach ($tableData as $row) {
                $amount = (float) ($this->extractFieldValue($row, $fieldMap['amount'] ?? null) ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $sayad = $this->extractFieldValue($row, $fieldMap['sayad'] ?? null);
                if (empty($sayad)) {
                    $sayad = $this->findValueMatchingRegex($row, '/^\d{16}$/');
                }

                $rawDirection = strtolower((string) ($this->extractFieldValue($row, $fieldMap['direction'] ?? null) ?? '1'));
                $direction = (in_array($rawDirection, ['2', 'payable', 'pardakhti', 'صادره', 'پرداختنی'])) ? 'payable' : 'receivable';

                $cheques[] = [
                    'cheque_number' => trim((string) ($this->extractFieldValue($row, $fieldMap['number'] ?? null) ?? '')),
                    'sayad_number' => $sayad ? trim((string) $sayad) : null,
                    'customer_name' => trim((string) ($this->extractFieldValue($row, $fieldMap['customer'] ?? null) ?? '')),
                    'amount' => $amount,
                    'write_date' => $this->extractFieldValue($row, $fieldMap['issue_date'] ?? null) ?: now()->toDateString(),
                    'due_date' => $this->extractFieldValue($row, $fieldMap['due_date'] ?? null) ?: now()->addDays(30)->toDateString(),
                    'direction' => $direction,
                    'bank_name' => $this->extractFieldValue($row, $fieldMap['bank'] ?? null) ?: 'بانک انتقالی',
                ];
            }
        }

        return new MigrationPayload(
            customers: $customers,
            products: $products,
            cheques: $cheques,
            chartOfAccounts: [],
            journalEntries: [],
            metadata: [
                'source' => 'auto_unknown_database',
                'adapter' => 'AutoUnknownDatabaseAdapter',
                'inferred_mappings' => $inferredMappings,
                'total_source_tables' => count($tables),
            ]
        );
    }

    /**
     * Normalizes any input format (JSON string, flat array, associative table array)
     * into a unified dictionary of tables: ['table_name' => [ [col => val], ... ]].
     */
    public function normalizeInputIntoTables(array|string $data): array
    {
        if (is_string($data)) {
            $trimmed = trim($data);
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                $data = $decoded;
            } elseif (stripos($trimmed, 'INSERT INTO') !== false || stripos($trimmed, 'CREATE TABLE') !== false) {
                // Raw SQL dump string
                return $this->parseSqlDump($trimmed);
            } elseif (str_contains($trimmed, "\n") && (str_contains($trimmed, ',') || str_contains($trimmed, ';') || str_contains($trimmed, "\t"))) {
                // CSV formatted string
                return ['csv_records' => $this->parseCsvString($trimmed)];
            } else {
                $data = [];
            }
        }

        if (! is_array($data)) {
            return [];
        }

        // Case A: Multi-table structure: ['table1' => [...], 'table2' => [...]]
        $isAssociative = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssociative) {
            $result = [];
            foreach ($data as $tableName => $rows) {
                if (is_array($rows)) {
                    // Check if it's a list of records
                    $isListOfRows = ! empty($rows) && is_array(reset($rows));
                    $result[$tableName] = $isListOfRows ? array_values($rows) : [$rows];
                }
            }

            return $result;
        }

        // Case B: Single flat list of records: [ [col1 => val1], [col1 => val2] ]
        return ['dataset_rows' => $data];
    }

    /**
     * Scores each table against entity types and assigns the best match.
     */
    protected function classifyTables(array $tables): array
    {
        $classified = [
            'customer' => null,
            'product' => null,
            'cheque' => null,
        ];

        $scores = [];
        foreach ($tables as $tableName => $rows) {
            if (empty($rows) || ! is_array($rows[0] ?? null)) {
                continue;
            }

            $sampleRow = $rows[0];
            $columnNames = array_keys($sampleRow);

            $scores[$tableName] = [
                'customer' => $this->calculateScore($tableName, $columnNames, $this->customerKeywords),
                'product' => $this->calculateScore($tableName, $columnNames, $this->productKeywords),
                'cheque' => $this->calculateScore($tableName, $columnNames, $this->chequeKeywords),
            ];
        }

        // Assign best distinct table per entity
        foreach (['customer', 'product', 'cheque'] as $entity) {
            $bestTable = null;
            $maxScore = 0.15; // Minimum confidence threshold

            foreach ($scores as $tName => $tScores) {
                if ($tScores[$entity] > $maxScore) {
                    $maxScore = $tScores[$entity];
                    $bestTable = $tName;
                }
            }

            if ($bestTable) {
                $classified[$entity] = [
                    'table_name' => $bestTable,
                    'confidence' => round(min($maxScore, 1.0) * 100),
                ];
            }
        }

        return $classified;
    }

    /**
     * Calculates score (0.0 to 1.0+) based on table name and column names.
     */
    protected function calculateScore(string $tableName, array $columnNames, array $keywords): float
    {
        $score = 0.0;
        $tSimplified = $this->simplifyString($tableName);

        // 1. Table name match
        foreach ($keywords['table'] as $kw) {
            $kwSimplified = $this->simplifyString($kw);
            if (str_contains($tSimplified, $kwSimplified)) {
                $score += 0.50;
                break;
            }
        }

        // 2. Column names match
        $matchedFields = 0;
        $totalFields = count($keywords['fields']);

        foreach ($keywords['fields'] as $fieldKey => $aliases) {
            foreach ($columnNames as $col) {
                $colSimplified = $this->simplifyString($col);
                foreach ($aliases as $alias) {
                    $aliasSimplified = $this->simplifyString($alias);
                    if ($colSimplified === $aliasSimplified || str_contains($colSimplified, $aliasSimplified)) {
                        $matchedFields++;
                        break 2;
                    }
                }
            }
        }

        $score += ($matchedFields / max($totalFields, 1)) * 0.70;

        return $score;
    }

    /**
     * Maps each canonical field to the best matching column name in the table.
     */
    protected function mapFields(array $tableRows, array $fieldKeywords): array
    {
        if (empty($tableRows) || ! is_array($tableRows[0] ?? null)) {
            return [];
        }

        $columns = array_keys($tableRows[0]);
        $mapping = [];

        foreach ($fieldKeywords as $canonical => $aliases) {
            foreach ($columns as $col) {
                $colSimplified = $this->simplifyString($col);
                foreach ($aliases as $alias) {
                    $aliasSimplified = $this->simplifyString($alias);
                    if ($colSimplified === $aliasSimplified) {
                        $mapping[$canonical] = $col;
                        break 2;
                    }
                }
            }

            // Fallback: partial containment
            if (! isset($mapping[$canonical])) {
                foreach ($columns as $col) {
                    $colSimplified = $this->simplifyString($col);
                    foreach ($aliases as $alias) {
                        $aliasSimplified = $this->simplifyString($alias);
                        if (str_contains($colSimplified, $aliasSimplified) || str_contains($aliasSimplified, $colSimplified)) {
                            $mapping[$canonical] = $col;
                            break 2;
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    protected function simplifyString(string $str): string
    {
        return preg_replace('/[\s_\-]+/u', '', mb_strtolower(trim($str)));
    }

    protected function extractFieldValue(array $row, ?string $column): mixed
    {
        if ($column === null || ! array_key_exists($column, $row)) {
            return null;
        }

        return $row[$column];
    }

    protected function findValueMatchingRegex(array $row, string $regex): ?string
    {
        foreach ($row as $val) {
            if (is_string($val) || is_numeric($val)) {
                $str = trim((string) $val);
                if (preg_match($regex, $str)) {
                    return $str;
                }
            }
        }

        return null;
    }

    /**
     * Built-in SQL dump parser: extracts table names, column names, and row data
     * from standard SQL INSERT statements.
     */
    public function parseSqlDump(string $sql): array
    {
        $tables = [];

        // Match INSERT INTO `table` (`col1`, `col2`) VALUES (...) or without backticks
        // Also supports INSERT INTO table VALUES (...)
        $pattern = '/INSERT\s+INTO\s+[`"\[]?([a-zA-Z0-9_\x7f-\xff]+)[`"\]]?\s*(?:\(([^)]+)\))?\s*VALUES\s*(.+?);/is';

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = trim($match[1]);
                $columnsString = $match[2] ?? '';
                $valuesBlob = $match[3] ?? '';

                $columns = [];
                if (! empty($columnsString)) {
                    $columns = array_map(function ($col) {
                        return trim($col, " `\"'[]\t\n\r");
                    }, explode(',', $columnsString));
                }

                // Parse value tuples: (val1, val2), (val3, val4)
                $tuples = $this->parseSqlValueTuples($valuesBlob);

                foreach ($tuples as $tuple) {
                    $row = [];
                    foreach ($tuple as $idx => $val) {
                        $colName = $columns[$idx] ?? ('col_'.$idx);
                        $row[$colName] = $val;
                    }
                    $tables[$table][] = $row;
                }
            }
        }

        return $tables;
    }

    /**
     * Splits SQL VALUES string into rows and values.
     */
    protected function parseSqlValueTuples(string $valuesString): array
    {
        $rows = [];
        $len = strlen($valuesString);
        $inTuple = false;
        $inString = false;
        $stringChar = null;
        $currentVal = '';
        $currentTuple = [];

        for ($i = 0; $i < $len; $i++) {
            $char = $valuesString[$i];
            $prevChar = $i > 0 ? $valuesString[$i - 1] : null;

            if ($char === "'" || $char === '"') {
                if ($inString && $char === $stringChar && $prevChar !== '\\') {
                    $inString = false;
                    $stringChar = null;
                } elseif (! $inString) {
                    $inString = true;
                    $stringChar = $char;
                } else {
                    $currentVal .= $char;
                }

                continue;
            }

            if ($inString) {
                $currentVal .= $char;

                continue;
            }

            if ($char === '(' && ! $inTuple) {
                $inTuple = true;
                $currentTuple = [];
                $currentVal = '';

                continue;
            }

            if ($char === ')' && $inTuple) {
                $currentTuple[] = $this->cleanSqlScalar($currentVal);
                $rows[] = $currentTuple;
                $inTuple = false;
                $currentTuple = [];
                $currentVal = '';

                continue;
            }

            if ($char === ',' && $inTuple) {
                $currentTuple[] = $this->cleanSqlScalar($currentVal);
                $currentVal = '';

                continue;
            }

            if ($inTuple) {
                $currentVal .= $char;
            }
        }

        return $rows;
    }

    protected function cleanSqlScalar(string $val): mixed
    {
        $trimmed = trim($val);
        if (strcasecmp($trimmed, 'NULL') === 0) {
            return null;
        }
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return stripslashes($trimmed);
    }

    /**
     * Built-in CSV parser: auto-detects delimiter and maps header to associative array.
     */
    public function parseCsvString(string $csvContent): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($csvContent)));
        if (empty($lines)) {
            return [];
        }

        $firstLine = $lines[0];
        $delimiter = ',';
        foreach ([',', ';', "\t", '|'] as $del) {
            if (substr_count($firstLine, $del) > substr_count($firstLine, $delimiter)) {
                $delimiter = $del;
            }
        }

        $header = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map(fn ($h) => trim($h, " \t\n\r\0\x0B\"'"), $header);

        $records = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $rowValues = str_getcsv($line, $delimiter);
            $record = [];
            foreach ($header as $i => $colName) {
                $record[$colName] = $rowValues[$i] ?? null;
            }
            $records[] = $record;
        }

        return $records;
    }
}
