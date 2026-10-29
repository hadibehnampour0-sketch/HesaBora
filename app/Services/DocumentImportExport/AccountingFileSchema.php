<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

/**
 * Clean-Room Specification for Standard 14-Column Double-Entry Accounting Interchange Schema.
 *
 * Defines the canonical column names, mandatory fields, and multilingual header aliases.
 *
 * License: MIT
 */
final class AccountingFileSchema
{
    public const COL_DOC_NUMBER = 'doc_number';

    public const COL_DOC_DATE = 'doc_date';

    public const COL_DOC_TITLE = 'doc_title';

    public const COL_DOC_TYPE = 'doc_type';

    public const COL_DOC_STATUS = 'doc_status';

    public const COL_SUBJECT_ROOT_CODE = 'subject_root_code';

    public const COL_SUBJECT_MOEIN_CODE = 'subject_moein_code';

    public const COL_SUBJECT_TAFSILI_CODE = 'subject_tafsili_code';

    public const COL_SUBJECT_NAME = 'subject_name';

    public const COL_SUBJECT_TYPE = 'subject_type';

    public const COL_SUBJECT_PERMANENT = 'subject_is_permanent';

    public const COL_TRANSACTION_DESC = 'transaction_desc';

    public const COL_DEBIT = 'debit';

    public const COL_CREDIT = 'credit';

    public const ALL_COLUMNS = [
        self::COL_DOC_NUMBER,
        self::COL_DOC_DATE,
        self::COL_DOC_TITLE,
        self::COL_DOC_TYPE,
        self::COL_DOC_STATUS,
        self::COL_SUBJECT_ROOT_CODE,
        self::COL_SUBJECT_MOEIN_CODE,
        self::COL_SUBJECT_TAFSILI_CODE,
        self::COL_SUBJECT_NAME,
        self::COL_SUBJECT_TYPE,
        self::COL_SUBJECT_PERMANENT,
        self::COL_TRANSACTION_DESC,
        self::COL_DEBIT,
        self::COL_CREDIT,
    ];

    public const MANDATORY_COLUMNS = [
        self::COL_DOC_NUMBER,
        self::COL_DOC_DATE,
        self::COL_SUBJECT_TAFSILI_CODE,
        self::COL_SUBJECT_NAME,
        self::COL_SUBJECT_TYPE,
        self::COL_SUBJECT_PERMANENT,
        self::COL_DEBIT,
        self::COL_CREDIT,
    ];

    public const HEADER_ALIASES = [
        self::COL_DOC_NUMBER => ['شماره سند', 'Document Number'],
        self::COL_DOC_DATE => ['تاریخ سند', 'Document Date'],
        self::COL_DOC_TITLE => ['عنوان سند', 'Document Title'],
        self::COL_DOC_TYPE => ['نوع سند', 'Document Type'],
        self::COL_DOC_STATUS => ['وضعیت سند', 'Document Status'],
        self::COL_SUBJECT_ROOT_CODE => ['کد ریشه', 'کد سرفصل اصلی', 'کد سرفصل کلی', 'Root Account Code'],
        self::COL_SUBJECT_MOEIN_CODE => ['کد معین', 'کد سرفصل معین', 'کد سرفصل والد', 'Moein Account Code'],
        self::COL_SUBJECT_TAFSILI_CODE => ['کد تفصیلی', 'کد سرفصل تفصیلی', 'کد سرفصل', 'Tafsili Account Code', 'Account Code'],
        self::COL_SUBJECT_NAME => ['نام سرفصل', 'Account Name'],
        self::COL_SUBJECT_TYPE => ['نوع حساب', 'ماهیت حساب', 'نوع سرفصل', 'Account Type'],
        self::COL_SUBJECT_PERMANENT => ['دائم/موقت', 'Permanent/Temporary'],
        self::COL_TRANSACTION_DESC => ['شرح ردیف', 'Transaction Description'],
        self::COL_DEBIT => ['بدهکار', 'Debit'],
        self::COL_CREDIT => ['بستانکار', 'Credit'],
    ];

    public static function matchAlias(string $header): string
    {
        $normalized = trim($header);
        foreach (self::HEADER_ALIASES as $canonicalKey => $synonyms) {
            if (in_array($normalized, $synonyms, true)) {
                return $canonicalKey;
            }
        }

        return $normalized;
    }
}
