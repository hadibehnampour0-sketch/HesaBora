# Dakhl FieldOperations — Mobile & Hardware Architecture Specification

**Status: DESIGN DOCUMENT, NOT IMPLEMENTED.** This is a specification for a mobile team to build against, not code that exists or was verified in this repository. It was produced without an Android/Flutter toolchain, without a physical thermal printer, and without access to any payment-provider SDK — every claim about hardware behavior here should be treated as a starting hypothesis to validate on real devices, not a proven fact. See `Dakhl_DECISION_LOG_2026-09-12-r4.md`'s "P11" entry for why this was scoped as a document rather than code in this pass.

**Backend counterpart**: the Laravel side of this epic (`SalesRoute`, `RouteCustomer`, `VisitPlan`, `Promotion`, `DailySettlement`) is built, tested, and merged into the main HesaBora/Dakhl codebase — see `app/Models/SalesRoute.php` etc. and `tests/Feature/FieldOperationsTest.php`. This document's Room schema and sync protocol are designed to mirror those models field-for-field so a future backend sync-integration pass (see §6) has a clean target.

---

## 1. Scope and Non-Goals

**In scope for a mobile team to build from this document:**
- Offline-first Android client for Sales Reps (order-taking / Van Sales) and Delivery Drivers.
- Local Room database mirroring the server's route/visit/promotion/settlement data plus the catalog/customer/invoice data the existing P10 sync engine already syncs.
- ESC/POS thermal printer support (58mm/80mm) with correct Persian/RTL rendering.
- Barcode scanning (camera + hardware scanner).
- SmartPOS payment terminal integration.

**Explicitly out of scope / not addressed here:**
- Choice of framework (native Kotlin/Jetpack Compose vs. Flutter) — left to the mobile team; this document is framework-agnostic where possible and calls out Android-specific APIs (Bluetooth, USB host mode) where the mechanism is inherently platform-specific regardless of UI framework.
- iOS. The original request was Android-specific (SmartPOS terminals, portable thermal printers, and Iranian payment SDKs referenced are all Android-only in practice).
- Any payment-provider SDK integration details beyond "here is the integration point" — به‌پرداخت/سامان/ایران‌کیش SDKs require a signed business agreement with each provider to even obtain; this document cannot include their actual APIs.

---

## 2. Domain Model Recap (server-authoritative)

The server (this Laravel repo) is the source of truth. Mirror these fields exactly in Room; do not invent new field names on the client.

| Server model | Key fields | Notes |
|---|---|---|
| `SalesRoute` | `id, company_id, title, code, description, is_active` | |
| `RouteCustomer` (pivot) | `route_id, customer_id, visit_priority, preferred_visit_days` | Ordering within a route |
| `VisitPlan` | `id, company_id, user_id, route_id, plan_date, status` (planned/in_progress/completed) | One per (user, date) |
| `Promotion` | `id, company_id, title, type` (tiered_discount/bonus_goods) `, customer_group_id, rules (JSON), is_active, starts_at, ends_at` | Server is the ONLY place promotions are authored; client only reads and evaluates |
| `DailySettlement` | `id, company_id, user_id, settlement_date, total_invoiced_amount, cash_collected, pos_collected, cheque_collected, status` (draft/submitted/approved) `, submitted_at, approved_at, approved_by` | `cash/pos/cheque_collected` are manual entries from the rep, never auto-computed |

Already-existing server-side entities the mobile app also needs (already syncable via the P10 sync engine — see §6): `Customer`, `Product`, `Document`, `Invoice`, `Payment`, `PaymentAllocation`.

---

## 3. Local Database (Room) Schema

Mirror the server tables above as Room `@Entity` classes, plus these **client-only** tables that never sync to the server:

```kotlin
// Client-only: which physical devices are paired to this installation.
@Entity(tableName = "device_configs")
data class DeviceConfigEntity(
    @PrimaryKey val id: String,           // UUID, client-generated
    val deviceType: String,               // PRINTER | BARCODE_SCANNER | POS_TERMINAL
    val connectionType: String,           // BLUETOOTH | USB | NETWORK
    val addressOrIp: String,              // MAC address, USB device path, or IP:port
    val paperWidthMm: Int? = null,        // 58 or 80, printers only
    val printDensity: Int? = null,        // 0-100, printers only
    val settingsJson: String? = null,     // extra device-specific settings, opaque JSON
    val lastConnectedAt: Long? = null
)

// Client-only: print jobs queued when a printer was unreachable at
// invoice-creation time. See §5.5 (Fail-Safe / Print Queue).
@Entity(tableName = "print_queue")
data class PrintQueueEntity(
    @PrimaryKey val id: String,           // UUID
    val invoiceLocalId: String,           // FK to the local Invoice/Document draft
    val deviceConfigId: String,           // which printer this was meant for
    val createdAt: Long,
    val attempts: Int = 0,
    val lastError: String? = null
)
```

**Every synced entity table** (mirroring `SalesRoute`, `VisitPlan`, `Customer`, `Invoice`, etc.) needs the same sync-envelope columns the server's `HasSyncEnvelope` concern already puts on the corresponding server tables — this is not new design, it's matching what P10 already established:

```kotlin
val uuid: String,              // server's uuid, primary sync identity
val syncVersion: Int,          // optimistic-concurrency token
val syncStatus: String,        // SYNCED | PENDING | CONFLICT | REJECTED
val localId: String,           // Room's own PK, client-generated UUID, stable across a create-before-sync
```

Local writes (a rep creating an order/invoice offline) get a client-generated `operation_id` (UUID) per the existing P10 protocol (§6) — this is not new; the mobile app must follow the exact same idempotency contract `SyncPilotService::pushEntry()` already enforces server-side.

---

## 4. Hardware Abstraction Layer (HAL)

### 4.1 Interfaces

```kotlin
interface DeviceManager {
    fun scanForDevices(type: DeviceType, transport: TransportType): Flow<DiscoveredDevice>
    fun pair(device: DiscoveredDevice): Result<DeviceConfigEntity>
    fun forget(deviceConfigId: String)
    fun connectionState(deviceConfigId: String): Flow<ConnectionState> // CONNECTED | DISCONNECTED | CONNECTING
}

interface ThermalPrinterAdapter {
    suspend fun printInvoice(bitmap: Bitmap, paperWidthMm: Int, density: Int): Result<Unit>
    suspend fun testPrint(): Result<Unit>
}

interface BarcodeScannerAdapter {
    fun scans(): Flow<String>   // emits raw scanned codes regardless of source
}

interface PosTerminalAdapter {
    suspend fun chargeAmount(amountRials: Long, referenceId: String): Result<PosChargeResult>
}
```

`ThermalPrinterAdapter` and `PosTerminalAdapter` each need at least two concrete implementations initially:
- `BluetoothThermalPrinterAdapter` (portable printers carried by reps) and `UsbThermalPrinterAdapter` (fixed warehouse/counter printers).
- A `PosTerminalAdapter` per payment provider SDK actually contracted (this cannot be designed further without the provider's real SDK docs — treat as a plugin point, not a finished design).

`BarcodeScannerAdapter` needs two implementations merged behind one `Flow<String>`: a Google ML Kit camera-based scanner, and a Bluetooth/USB HID scanner (which types scanned codes as keyboard input — the standard integration is an `EditText` listener that detects rapid keystroke bursts terminated by Enter, since HID scanners have no dedicated Android API).

### 4.2 ESC/POS Canvas Rasterizer Pipeline (the Persian-printing solution)

**Problem this solves**: sending text + an ESC/POS code page command to a thermal printer relies on the printer's own firmware having a correct, complete Persian/Arabic glyph table for that code page (commonly CP1256). Cheap portable printers frequently have incomplete or buggy Persian glyph tables, producing dropped letters, disconnected Arabic-script joining forms, or mirrored/reversed text. This is a hardware/firmware limitation that cannot be fixed from the app side by choosing a different code page.

**Solution**: never send text to the printer. Render the entire receipt as a bitmap using Android's own font rendering (which correctly shapes Persian/Arabic script, unlike the printer's firmware), then send that bitmap as raw monochrome image data. The printer only ever receives pixels, never characters.

**Pipeline**:

1. **Layout.** Build the receipt as a normal Android `Canvas` drawing surface, width = target paper width in pixels (58mm ≈ 384px at 203dpi thermal print resolution; 80mm ≈ 576px — confirm the exact DPI for the target printer model, it varies). Use a bundled RTL-correct Persian font (Vazirmatn or IRANSans, embedded in the app, never relying on system fonts which may not be present/correct on all Android builds). Draw the invoice header, line items, totals, and footer using `Canvas.drawText()` with `Paint.setTextDirection` set for RTL where applicable, and Persian digits via the app's own existing digit-localization logic (already established server-side convention: `localizeNumber()` — mirror this exact digit-shaping logic on the client so printed and on-screen numerals always match).
2. **Rasterize.** Render the `Canvas` to a `Bitmap` at the target width, with height computed dynamically from actual content (variable number of line items).
3. **Binarize.** Convert the `Bitmap` to 1-bit-per-pixel monochrome via a dithering algorithm (Floyd–Steinberg dithering gives materially better legibility for text than simple threshold binarization — verify this empirically on the target printer, since dithering can also make small font sizes muddier; a hybrid approach — threshold for pure black/white regions like text, dithering only for logos/images — may be needed in practice).
4. **Chunk & encode.** ESC/POS raster printing has a maximum band height per command on most controllers (commonly 256 pixels rows per `GS v 0` call) — split the full bitmap into horizontal bands and issue one raster command per band, not one command for the whole receipt.
5. **Transmit.** Send via `GS v 0 m xL xH yL yH [data]` (the standard "print raster bit image" command) for printers that support it, with `ESC *` bit-image mode as a fallback for older controllers that don't. **Which command a given printer actually supports must be determined empirically per printer model** — there is no universal answer, hence the Device Center's own Test Print step (§5) exists specifically to catch this before a rep is in the field with a receipt that won't print.
6. **Density.** The Device Center's print-density slider (§5.3) maps to the dithering threshold and/or a printer-specific "print density" ESC/POS command sent once at connection time, compensating for how portable thermal printers print visibly lighter as their battery depletes.

**This entire pipeline must be validated against at least 2-3 real printer models before shipping** — ESC/POS command support (`GS v 0` vs `ESC *`), maximum raster band height, and effective print resolution all vary by controller chipset, and this document cannot verify any of it without physical hardware.

---

## 5. Device Center UX

### 5.1 Layout
A dedicated settings section, "تجهیزات و سخت‌افزارها" (Devices & Hardware), with one card per paired device grouped by type (🖨️ Printers, 📷 Scanners, 💳 POS Terminals). Each card shows a live connection-state indicator (green/red/yellow dot, bound to `DeviceManager.connectionState()`).

### 5.2 Pairing wizard (3 steps)
1. **Device type + transport.** User picks device type and connection method (Bluetooth / USB / Wi-Fi).
2. **Auto-discovery.** `DeviceManager.scanForDevices()` lists nearby/available devices by name; one tap pairs — no manual MAC address or IP entry for the common case.
3. **Test.** For a printer: fire `ThermalPrinterAdapter.testPrint()` with a small sample receipt; user confirms it printed correctly (or reports garbled output, which should surface a "try the alternate raster command" retry rather than a dead end). For a scanner: prompt "scan any barcode now" and confirm a scan was received. For a POS terminal: run the provider SDK's own connectivity check if one exists.

### 5.3 Paper/layout settings
Live preview of the receipt bitmap (§4.2's pipeline, rendered to an on-screen `ImageView` instead of sent to a printer) updates immediately when the user changes paper width (58mm / 80mm / A4-A5 for a full-page fallback printer) or the density slider — the user sees exactly what will print before committing to a physical test.

### 5.4 Fail-safe mechanisms
- **Print queue** (`PrintQueueEntity`, §3): if `ThermalPrinterAdapter.printInvoice()` fails (disconnected, out of paper — most ESC/POS printers report paper-out via a status byte if the adapter reads it), queue the job locally and retry automatically once `DeviceManager.connectionState()` reports `CONNECTED` again, rather than silently dropping the print or blocking the sale.
- **Digital fallback**: whenever a print fails (queued or exhausted retries), immediately surface an alternative — a shareable PDF/image of the same rendered bitmap via Android's share sheet (SMS/WhatsApp/etc.), or a QR code encoding a link to the invoice on the server (requires the invoice to already be synced — degrades gracefully to "PDF only" while offline).

---

## 6. Sync Protocol — Extending the Existing P10 Engine, Not a New One

**Do not build a separate `/api/v1/field-ops/sync/*` endpoint pair.** The backend already has a mature, tested, generic offline-sync engine (`app/Services/Sync/SyncPilotService.php`, 132+ passing tests as of this writing) built for exactly this kind of offline-mobile-client scenario — device pairing (`DevicePairingService`), idempotent command replay (`operation_id`), optimistic-concurrency conflict detection with a real review queue, and cursor-based incremental pull, all already proven for `Customer`/`Product`/`Document`/`Invoice`/`Payment`/`PaymentAllocation`. The mobile app should authenticate and sync through the SAME existing endpoints (`POST /api/sync/push`, `GET /api/sync/pull`, device pairing via `DevicePairingService`) that already exist, not a parallel mechanism.

**What is NOT yet done on the backend, and must happen before a mobile team can rely on offline sync for the new P11 entities**: `SalesRoute`, `VisitPlan`, `Promotion`, and `DailySettlement` are not yet in `SyncPilotService::SYNCABLE_ENTITIES`, and none of them yet carry the `uuid`/`sync_version`/`sync_status`/`origin_device_id` envelope columns the sync engine requires (see `HasSyncEnvelope`/`SyncsToOutbox` concerns already used by `Customer`, `Invoice`, etc.). This is real, scoped, well-precedented follow-up work — extending an existing, proven pattern to four more entities — not a new sync architecture. Recommended order, mirroring how `Customer`/`Product` (2.5) came before `Document`/`Invoice` (2.7) before `Payment`/`PaymentAllocation` (2.8) in the existing Decision Log:

1. `VisitPlan` and `SalesRoute`/`RouteCustomer` — read-mostly from the mobile side (server assigns routes/plans; the rep mostly just needs to *see* them offline), so CREATE/UPDATE sync scope can likely be narrow or even pull-only initially.
2. `DailySettlement` — CREATE-only from the mobile side (a rep submits their own settlement; approval always happens server-side by a cashier), mirroring the existing `Payment`/`PaymentAllocation` CREATE-only precedent exactly.
3. `Promotion` is server-authored only — the mobile app only ever needs to PULL active promotions (to run `PromotionEngineService`'s equivalent client-side, or fetch server-evaluated suggestions on demand if connectivity allows), never push changes to it.

Until this backend work is done, the mobile app can still function against `SalesRoute`/`VisitPlan`/`Promotion`/`DailySettlement` in an ONLINE-ONLY mode (plain authenticated REST calls to the new controllers already built — `SalesRouteController`, `VisitPlanController`, `PromotionController`, `DailySettlementController`, all under `/management/*` today) while the rest of the offline-sync-critical flow (order-taking, invoicing, payment) uses the existing, fully offline-capable P10 engine.

---

## 7. Suggested Build Order for the Mobile Team

1. Core offline shell: Room DB, the existing P10 sync protocol (Customer/Product/Invoice/Payment — already fully specified server-side, nothing new to design), device pairing flow.
2. Order-taking (Cold Sales) UI against the offline catalog.
3. Device Center + ESC/POS rasterizer pipeline (§4.2) — validate against real printer hardware before this is considered "done," not just "compiles."
4. Barcode scanning (camera first — no hardware dependency to unblock development; HID scanner support after).
5. Van Sales (Hot Sales) flow: on-the-spot invoice + immediate print + payment collection.
6. SmartPOS integration (blocked on obtaining a real provider SDK and test terminal).
7. Driver delivery queue + daily settlement submission UI (backend already supports settlement creation/submission — this is client-only work once §6 step 2 sync support lands, or online-only before that).
