# Full Test Suite Failure Analysis

Total failed test blocks: 18

```
   FAILED  Tests\Feature\AttendanceLogTest > index filters by date range                                                
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

Carbon\Exceptions\InvalidFormatException: Not enough data available to satisfy format in C:\Projects\HesaBora\vendor\nesbot\carbon\src\Carbon\Traits\Creator.php:625
Stack trace:
#0 C:\Projects\HesaBora\vendor\nesbot\carbon\src\Carbon\Traits\Creator.php(661): Carbon\Carbon::rawCreateFromFormat('Y/m/d', '', NULL)
#1 C:\Projects\HesaBora\app\Http\Controllers\AttendanceLogController.php(37): Carbon\Carbon::createFromFormat('Y/m/d', '')
#2 C:\Projects\HesaBora\vendor\laravel\framework\src\Illuminate\Routing\Controller.php(54): App\Http\Controllers\AttendanceLogController->index(Object(Illuminate\Http\Request))
#3 C:\Projects\HesaBora\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(43): Illuminate\Routing\Controller->callAction('index', Array)
#4 C:\Projects\HesaBora\vendor\laravel\framework\src\Illuminate\Routing\Route.php(265): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Http\Controllers\AttendanceLogController), 'index')
#5 C:\Projects\HesaBora\vendor\laravel\framework\src\Illuminate\Routing\Route.php(211): Illuminate\Routing\Route->runController()
#6 C:\Projects\HesaBora\vendor\laravel\framework\src\Illuminate\Routing\Router
```

---

```
   FAILED  Tests\Feature\DeepSystemLifecycleStressTest > invoice creation approval and posting invariants               
  Invoice must be created
Failed asserting that null is not null.

  at tests\Feature\DeepSystemLifecycleStressTest.php:119
    115▕ 
    116▕         $res->assertSessionHasNoErrors();
    117▕ 
    118▕         $invoice = Invoice::where('customer_id', $customer->id)->latest()->first();
  ➜ 119▕         $this->assertNotNull($invoice, 'Invoice must be created');
    120▕         $this->assertEquals(InvoiceType::SELL, $invoice->invoice_type);
    121▕ 
    122▕         // Approve invoice via changeStatus
    123▕         $approveRes = $this->actingAs($this->adminUser)

  1   tests\Feature\DeepSystemLifecycleStressTest.php:119

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\DeepSystemLifecycleStressTest > manual accounting document balance invariant                   
  Failed asserting that a row in the table [documents] matches the attributes {
    "company_id": 1,
    "number": 55
}.

The table is empty.

  at tests\Feature\DeepSystemLifecycleStressTest.php:175
    171▕                 ],
    172▕             ]);
    173▕ 
    174▕         $res->assertSessionHasNoErrors();
  ➜ 175▕         $this->assertDatabaseHas('documents', [
    176▕             'company_id' => $this->companyId,
    177▕             'number' => 55,
    178▕         ]);
    179▕     }

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\DeepSystemLifecycleStressTest > cheque lifecycle transitions                                   
  Failed asserting that two values of enumeration App\Enums\ChequeType are equal, REGISTERED does not match expected DEPOSITED.

  at tests\Feature\DeepSystemLifecycleStressTest.php:227
    223▕             ]);
    224▕ 
    225▕         $depositRes->assertSessionHasNoErrors();
    226▕         $cheque->refresh();
  ➜ 227▕         $this->assertEquals(ChequeType::DEPOSITED, $cheque->status);
    228▕ 
    229▕         // Transition: Clear Cheque
    230▕         $clearRes = $this->actingAs($this->adminUser)
    231▕             ->withSession(['company_id' => $this->companyId, 'fiscal_year' => 1405])

  1   tests\Feature\DeepSystemLifecycleStressTest.php:227

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\DeepSystemLifecycleStressTest > warehouse transfer integrity                                   
  Failed asserting that a row in the table [warehouse_transfers] matches the attributes {
    "company_id": 1,
    "from_warehouse_id": 1,
    "to_warehouse_id": 2,
    "product_id": 1,
    "quantity": 10
}.

The table is empty.

  at tests\Feature\DeepSystemLifecycleStressTest.php:272
    268▕                 'description' => 'حواله انتقال ۱۰ عدد کالا به انبار مقصد',
    269▕             ]);
    270▕ 
    271▕         $transferRes->assertSessionHasNoErrors();
  ➜ 272▕         $this->assertDatabaseHas('warehouse_transfers', [
    273▕             'company_id' => $this->companyId,
    274▕             'from_warehouse_id' => $whSource->id,
    275▕             'to_warehouse_id' => $whDest->id,
    276▕             'product_id' => $product->id,

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\DeepSystemLifecycleStressTest > quick payment receive and pay                                  
  Failed asserting that null is not null.

  at tests\Feature\DeepSystemLifecycleStressTest.php:316
    312▕         $resReceive->assertSessionHasNoErrors();
    313▕ 
    314▕         // Check Payment created
    315▕         $payment = Payment::where('customer_id', $customer->id)->latest()->first();
  ➜ 316▕         $this->assertNotNull($payment);
    317▕         $this->assertEquals(12500000, $payment->amount);
    318▕     }
    319▕ }
    320▕

  1   tests\Feature\DeepSystemLifecycleStressTest.php:316

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\FinancialCopilotTest > user can save and clear company gemini api key                          
  Failed asserting that a row in the table [configs] matches the attributes {
    "company_id": 1,
    "key": "gemini_api_key",
    "value": "AIzaSyTestApiKeyForCompany12345"
}.

Found similar results: [
    {
        "company_id": 1,
        "key": "gemini_api_key",
        "value": "eyJpdiI6IkZNWWVSVVZJdGdMSjIwdTFsWEhOMGc9PSIsInZhbHVlIjoiT3NkNlM0OWxtTTVocUp4RkJ6aWVkK0JrOWl5WmNVZVNUTG01bDZYaEoxaz0iLCJtYWMiOiI4ODM1ZWFjYzBhZDBmOTkwYjAyZWEyNDdmMGQyMmVkOTE2MzQwNzc0MDE4MDc4MDM5ZDViOWJiM2U0NTU3ODI1IiwidGFnIjoiIn0="
    }
].

  at tests\Feature\FinancialCopilotTest.php:252
    248▕ 
    249▕         $response->assertOk()
    250▕             ->assertJson(['success' => true, 'is_configured' => true]);
    251▕ 
  ➜ 252▕         $this->assertDatabaseHas('configs', [
    253▕             'company_id' => $this->company->id,
    254▕             'key' => 'gemini_api_key',
    255▕             'value' => 'AIzaSyTestApiKeyForCompany12345',
    256▕         ]);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > seller sees only role specific quick links                              
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="fpVaQMWD3nOPrLwJrqbSLKFzrJxJhpVfdvT0fm4x" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > home order is today title then action links then financial boxes the…   
  Failed asserting that an array does not contain false.

  at tests\Feature\HomeServiceChartTest.php:263
    259▕             'id="private-summary-title"',
    260▕             'class="today-kpi',
    261▕         ]);
    262▕ 
  ➜ 263▕         $this->assertNotContains(false, $positions);
    264▕         $sorted = $positions;
    265▕         sort($sorted);
    266▕         $this->assertSame($sorted, $positions);
    267▕         $this->assertSame(1, substr_count($html, 'data-home-area="create-product-link"'));

  1   tests\Feature\HomeServiceChartTest.php:263

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > warehousekeeper does not receive sales content from product access      
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="gOWParpfbsSEFxenHCPnwu7ERDA9wQhqp62BipRv" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > services only user receives service quick links                         
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="6tcCzvnlOtPCCwlMtB3FRJ3Bqgxf5H8NA1VvGPBq" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > accounting user does not see removed catalog cards                      
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="kvUuxCWlMm0T59ruXmptrR6VfNN82sjS4pRUM5z2" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > mixed business permissions are combined but personal portal is hidde…   
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="rYjgYbbemP9J2LJ0H2jfvATkBzhTMHlRBLwiDWbp" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\HomeServiceChartTest > employee only user gets the employee variant                            
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="laT9lhVdqGAtms5oTJyG3sgmROeqspXyX9qYfW4V" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

```
   FAILED  Tests\Feature\OfficialInquiryTest > shahkar inquiry endpoint logs and returns success                        
  Failed asserting that a row in the table [official_inquiry_logs] matches the attributes {
    "company_id": 1,
    "inquiry_type": "shahkar",
    "query_term": "0010359877",
    "is_matched": true,
    "status": "success"
}.

Found similar results: [
    {
        "company_id": 1,
        "inquiry_type": "shahkar",
        "query_term": "001****877",
        "is_matched": 1,
        "status": "success"
    }
].

  at tests\Feature\OfficialInquiryTest.php:126
    122▕                 'mobile' => '09121234567',
    123▕             ],
    124▕         ]);
    125▕ 
  ➜ 126▕         $this->assertDatabaseHas('official_inquiry_logs', [
    127▕             'company_id' => $this->company->id,
    128▕             'inquiry_type' => 'shahkar',
    129▕             'query_term' => '0010359877',
    130▕             'is_matched' => true,

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\OfficialInquiryTest > card to sheba inquiry endpoint                                           
  Failed asserting that a row in the table [official_inquiry_logs] matches the attributes {
    "company_id": 1,
    "inquiry_type": "card",
    "query_term": "6037991122334455",
    "is_matched": true
}.

Found similar results: [
    {
        "company_id": 1,
        "inquiry_type": "card",
        "query_term": "603****455",
        "is_matched": 1
    }
].

  at tests\Feature\OfficialInquiryTest.php:153
    149▕                 'status' => 'active',
    150▕             ],
    151▕         ]);
    152▕ 
  ➜ 153▕         $this->assertDatabaseHas('official_inquiry_logs', [
    154▕             'company_id' => $this->company->id,
    155▕             'inquiry_type' => 'card',
    156▕             'query_term' => $card,
    157▕             'is_matched' => true,

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\OfficialInquiryTest > postal code inquiry endpoint                                             
  Failed asserting that a row in the table [official_inquiry_logs] matches the attributes {
    "company_id": 1,
    "inquiry_type": "postal_code",
    "query_term": "1456789123"
}.

Found similar results: [
    {
        "company_id": 1,
        "inquiry_type": "postal_code",
        "query_term": "145****123"
    }
].

  at tests\Feature\OfficialInquiryTest.php:179
    175▕                 'city' => 'تهران',
    176▕             ],
    177▕         ]);
    178▕ 
  ➜ 179▕         $this->assertDatabaseHas('official_inquiry_logs', [
    180▕             'company_id' => $this->company->id,
    181▕             'inquiry_type' => 'postal_code',
    182▕             'query_term' => $postalCode,
    183▕         ]);

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
```

---

```
   FAILED  Tests\Feature\VoidSellInvoiceTest > sell index can filter only voided sell invoices                          
  Expected: <!DOCTYPE html>\n
<html lang="fa" dir="rtl" data-theme="light">\n
\n
<head>\n
    <meta charset="utf-8">\n
    <meta name="viewport" content="width=device-width, initial-scale=1">\n
    <meta name="csrf_token" content="p9KQWtaJmS18qbN8XmAsHrmaGzkYISSTXXYrmlwK" />\n
    <script>\n
        (function() {\n
            try {\n
                // 1. Theme mode (light vs dark)\n
                var savedThemeMode = localStorage.getItem('theme');\n
                var activeThemeMode = savedThemeMode ? (savedThemeMode === 'dark' ? 'dark' : 'light') : 'light';\n
                document.documentElement.setAttribute('data-theme', activeThemeMode);\n
\n
                // 2. Core UI Theme (flow, linear, stripe)\n
                var serverUiTheme = "flow";\n
                var localUiTheme = localStorage.getItem('ui_theme');\n
                var activeUiTheme = localUiTheme || serverUiTheme || 'flow';\n
                document.documentElement.setAttribute('data-ui-theme', activeUiTheme);\n
                if (!localUiTheme) {\n
                    localStorage.setItem('ui_theme', activeUiTheme);\n
                }\n
\n
                // 3. Accent Color (cyan, emerald, indigo, violet, slate)\n
                var serverAccent = "cyan";\n
                var localAccent = localStorage.getItem('dakhl_accent_color');\n
                var activeAccent = 
```

---

