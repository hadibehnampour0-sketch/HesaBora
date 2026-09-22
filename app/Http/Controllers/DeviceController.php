<?php

namespace App\Http\Controllers;

use App\Models\DeviceInstallation;
use App\Services\DevicePairingService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private readonly DevicePairingService $pairingService) {}

    public function index(Request $request)
    {
        $installations = DeviceInstallation::where('company_id', getActiveCompany())->latest()->get();

        return view('devices.index', ['installations' => $installations]);
    }

    public function storeActivationCode(Request $request)
    {
        $company = \App\Models\Company::findOrFail(getActiveCompany());
        $code = $this->pairingService->issueActivationCode($company, $request->user());

        return redirect()->route('devices.index')
            ->with('success', __('Activation code generated: :code (expires in 15 minutes).', ['code' => $code]));
    }

    public function revoke(DeviceInstallation $installation)
    {
        abort_unless((int) $installation->company_id === (int) getActiveCompany(), 404);

        $this->pairingService->revoke($installation);

        return redirect()->route('devices.index')->with('success', __('Device revoked successfully.'));
    }
}
