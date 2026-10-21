<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Services\Attendance\AttendanceDeviceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceDeviceController extends Controller
{
    public function __construct(
        protected AttendanceDeviceSyncService $syncService
    ) {}

    /**
     * Display listing of attendance hardware devices.
     */
    public function index(Request $request): View
    {
        $devices = AttendanceDevice::latest('id')->paginate(15);
        $totalActive = AttendanceDevice::where('is_active', true)->count();
        $totalSyncedToday = AttendanceDevice::whereDate('last_sync_at', today())->count();

        return view('attendance.devices.index', [
            'devices' => $devices,
            'totalActive' => $totalActive,
            'totalSyncedToday' => $totalSyncedToday,
        ]);
    }

    /**
     * Register a new biometric clock device.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'brand' => 'required|string|max:32',
            'protocol' => 'required|string|max:32',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer|min:1|max:65535',
            'comm_key' => 'nullable|string|max:32',
            'location' => 'nullable|string|max:150',
            'auto_sync' => 'nullable|boolean',
        ], [
            'name.required' => 'نام دستگاه الزامی است.',
            'ip_address.required' => 'آدرس IP دستگاه الزامی است.',
            'port.required' => 'پورت ارتباطی الزامی است.',
        ]);

        $validated['company_id'] = getActiveCompany();
        $validated['auto_sync'] = $request->boolean('auto_sync', true);
        $validated['is_active'] = true;
        $validated['comm_key'] = $validated['comm_key'] ?? '0';

        $device = AttendanceDevice::create($validated);

        // Run initial connection test in background/sync
        $this->syncService->pingDevice($device);

        return redirect()->route('attendance.devices.index')
            ->with('success', "دستگاه «{$device->name}» با موفقیت ثبت شد.");
    }

    /**
     * Update existing device settings.
     */
    public function update(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'brand' => 'required|string|max:32',
            'protocol' => 'required|string|max:32',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|integer|min:1|max:65535',
            'comm_key' => 'nullable|string|max:32',
            'location' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
            'auto_sync' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['auto_sync'] = $request->boolean('auto_sync', true);

        $device->update($validated);

        return redirect()->route('attendance.devices.index')
            ->with('success', "مشخصات دستگاه «{$device->name}» به‌روزرسانی شد.");
    }

    /**
     * Delete device registration.
     */
    public function destroy(AttendanceDevice $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()->route('attendance.devices.index')
            ->with('success', "دستگاه «{$name}» از سامانه حذف گردید.");
    }

    /**
     * Ping test network connection.
     */
    public function ping(Request $request, AttendanceDevice $device): JsonResponse|RedirectResponse
    {
        $forceMock = $request->boolean('force_mock', false);
        $result = $this->syncService->pingDevice($device, $forceMock);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()->route('attendance.devices.index')->with('success', $result['message']);
        }

        return redirect()->route('attendance.devices.index')->with('error', $result['message']);
    }

    /**
     * Live sync attendance records directly from the clock device.
     */
    public function sync(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $duplicateMode = $request->input('duplicate_mode', 'replace');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $forceMock = $request->boolean('force_mock', false);

        $result = $this->syncService->syncDevice($device, $dateFrom, $dateTo, $duplicateMode, $forceMock);

        if (! $result['success']) {
            return redirect()->route('attendance.devices.index')
                ->with('error', "خطا در همگام‌سازی از دستگاه: {$result['message']}");
        }

        $msg = "همگام‌سازی با موفقیت انجام شد: {$result['imported']} رکورد تردد ثبت و در کارکرد پرسنل محاسبه شد.";
        if (! empty($result['unknown_pins'])) {
            $msg .= " (تعداد " . count($result['unknown_pins']) . " شناسه در دستگاه برای هیچ کارمندی تعریف نشده بود).";
        }

        return redirect()->route('attendance.devices.index')->with('success', $msg);
    }

    /**
     * Demo / Simulation sync for instant testing without physical hardware.
     */
    public function simulate(AttendanceDevice $device): RedirectResponse
    {
        $result = $this->syncService->syncDevice($device, null, null, 'replace', true);

        return redirect()->route('attendance.devices.index')
            ->with('success', "شبیه‌سازی تردد دستگاه با موفقیت انجام شد: {$result['imported']} لاگ تردد ثبت و محاسبه گردید.");
    }
}
