<?php

namespace App\Services\Inquiry\Contracts;

interface InquiryProviderInterface
{
    /**
     * Verify National Code against Mobile phone (Shahkar inquiry).
     *
     * @return array{matched: bool, national_code: string, mobile: string, message: string, status: string}
     */
    public function verifyShahkar(string $nationalCode, string $mobile): array;

    /**
     * Verify Sheba and fetch account owner name.
     *
     * @return array{sheba: string, account_owner_name: string, bank_name: string, account_number: string, status: string}
     */
    public function verifySheba(string $sheba): array;

    /**
     * Convert Card number to Sheba and fetch cardholder name.
     *
     * @return array{card_number: string, sheba: string, bank_name: string, account_owner_name: string, status: string}
     */
    public function convertCardToSheba(string $cardNumber): array;

    /**
     * Verify Postal code and retrieve geographical address.
     *
     * @return array{postal_code: string, province: string, city: string, district: string, address: string, status: string}
     */
    public function verifyPostalCode(string $postalCode): array;
}
