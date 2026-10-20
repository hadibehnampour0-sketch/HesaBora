<?php

namespace App\Services\Inquiry\Providers;

use App\Services\Inquiry\Contracts\InquiryProviderInterface;
use App\Services\Inquiry\Validators\IranianCardValidator;
use App\Services\Inquiry\Validators\IranianNationalCodeValidator;
use App\Services\Inquiry\Validators\IranianPostalCodeValidator;
use App\Services\Inquiry\Validators\IranianShebaValidator;
use InvalidArgumentException;

class SandboxInquiryProvider implements InquiryProviderInterface
{
    public function verifyShahkar(string $nationalCode, string $mobile): array
    {
        if (! IranianNationalCodeValidator::validate($nationalCode)) {
            throw new InvalidArgumentException(__('کد ملی وارد شده نامعتبر است.'));
        }

        $cleanMobile = preg_replace('/\D/', '', $mobile);
        if (! preg_match('/^09\d{9}$/', $cleanMobile)) {
            throw new InvalidArgumentException(__('شماره موبایل وارد شده نامعتبر است. شماره باید با 09 شروع شده و ۱۱ رقم باشد.'));
        }

        // Test hook: mobile ending with '0000' or matching test marker simulates non-matching ownership
        $matched = ! str_ends_with($cleanMobile, '0000');

        return [
            'matched' => $matched,
            'national_code' => $nationalCode,
            'mobile' => $cleanMobile,
            'message' => $matched
                ? 'مالکیت شماره موبایل با کدملی مطابقت دارد.'
                : 'شماره موبایل با کد ملی وارد شده تطابق ندارد.',
            'status' => $matched ? 'success' : 'mismatch',
        ];
    }

    public function verifySheba(string $sheba): array
    {
        $cleanSheba = IranianShebaValidator::clean($sheba);

        if (! IranianShebaValidator::validate($cleanSheba)) {
            throw new InvalidArgumentException(__('شماره شبا نامعتبر است.'));
        }

        $bankName = IranianShebaValidator::getBankName($cleanSheba);
        $accountNumber = substr($cleanSheba, 12);

        return [
            'sheba' => $cleanSheba,
            'account_owner_name' => 'شرکت توسعه دخل پایدار (آزمایشی)',
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'status' => 'active',
        ];
    }

    public function convertCardToSheba(string $cardNumber): array
    {
        $cleanCard = IranianCardValidator::clean($cardNumber);

        if (! IranianCardValidator::validate($cleanCard)) {
            throw new InvalidArgumentException(__('شماره کارت وارد شده طبق الگوریتم بانکی معتبر نیست.'));
        }

        $bankDetails = IranianCardValidator::getBankDetails($cleanCard);
        $bankCode = $bankDetails['sheba_code'];
        $bankName = $bankDetails['name'];

        // Mock Sheba derived from card digits
        $mockSheba = 'IR02' . $bankCode . '0000000' . substr($cleanCard, 6, 12);

        return [
            'card_number' => $cleanCard,
            'sheba' => $mockSheba,
            'bank_name' => $bankName,
            'account_owner_name' => 'دارنده کارت عضو شتاب',
            'status' => 'active',
        ];
    }

    public function verifyPostalCode(string $postalCode): array
    {
        if (! IranianPostalCodeValidator::validate($postalCode)) {
            throw new InvalidArgumentException(__('کد پستی ۱۰ رقمی نامعتبر است.'));
        }

        return [
            'postal_code' => $postalCode,
            'province' => 'تهران',
            'city' => 'تهران',
            'district' => 'منطقه ۶',
            'address' => 'خیابان ولیعصر، بالاتر از میدان ونک، پلاک آزمایشی ۱۰، واحد ۴',
            'status' => 'success',
        ];
    }
}
