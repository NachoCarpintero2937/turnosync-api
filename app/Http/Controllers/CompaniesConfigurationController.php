<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use App\Models\CompaniesConfiguration;
use App\Services\ApiService;
use Illuminate\Support\Facades\Auth;

class CompaniesConfigurationController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('auth:api');
    }

    public function index()
    {
        $data = [];
        try {
            $companyId = Auth::user()->company_id;
            $configurations = Companies::where('company_id', $companyId)->with('configurations')->get();
            $data = ['configurations' => $configurations];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    // Otros métodos del controlador según sea necesario...
}