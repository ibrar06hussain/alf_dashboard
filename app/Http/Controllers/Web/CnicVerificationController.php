<?php

namespace App\Http\Controllers\Web;

use App\Services\SurveyExternalApi;
use App\Support\Cnic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CnicVerificationController
{
    public function verify(Request $request): JsonResponse
    {
        $request->merge([
            'cnic' => preg_replace('/\D+/', '', (string) $request->input('cnic')),
        ]);

        $data = $request->validate([
            'cnic' => ['required', 'digits:13'],
        ], [
            'cnic.required' => 'CNIC is required.',
            'cnic.digits' => 'CNIC must be exactly 13 digits.',
        ]);

        $resp = SurveyExternalApi::make()->lookup(Cnic::dashed($data['cnic']));

        if (! ($resp['success'] ?? false)) {
            session()->forget('verified_cnic');
            $msg = strtolower((string) ($resp['message'] ?? ''));
            if (empty($msg) || ! (str_contains($msg, 'unreachable') || str_contains($msg, 'unavailable') || str_contains($msg, 'connection'))) {
                $resp['message'] = __('portal.cnic_not_found');
            }
        } else {
            session(['verified_cnic' => $data['cnic']]);
        }

        return response()->json($resp);
    }
}
