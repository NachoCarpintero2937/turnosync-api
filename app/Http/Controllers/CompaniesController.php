<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use App\Models\CompaniesConfiguration;
use App\Services\ApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class CompaniesController extends Controller

{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('auth:api', ['except' => ['index']]);
    }

    public function index(Request $request)
    {
        $data = [];
        try {
            $companyId = Auth::user()->company_id?? $request->company_id;
            if(!$companyId){
                throw new Exception("Error al buscar la empresa");
            }
            $company = Companies::with('configurations')->findOrFail($companyId);
            $data = ['companies' => $company];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function create(Request $request)
    {
        $data = [];
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'status' => 'required|integer',
            ]);
    
            // Crear la compañía
            $company = Companies::create($validatedData);
    
            // Crear las configuraciones por defecto
            $defaultConfigurations = [
                'toolbar' => '#E1B6B6',
                'cardHome' => '#a58171',
                'icons' => '',
                'button-icons' => '',
                'imgLogo' => '',
                'imgPortada' => '',
                'originalTemplate' => 1 
            ];
    
            foreach ($defaultConfigurations as $key => $value) {
                CompaniesConfiguration::create([
                    'configuration_key' => $key,
                    'configuration_value' => $value,
                    'company_id' => $company->id
                ]);
            }
    
            $data = ['company' => $company];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function update(Request $request)
    {
        $data = [];
        $companyId = Auth::user()->company_id;
    
        try {
            // Validar los datos de la solicitud
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'configurations' => 'array' // Asumiendo que 'configurations' es un array con las nuevas configuraciones
            ]);
    
            // Obtener la lista de configuraciones por defecto
            $defaultConfigurations = [
                'toolbar',
                'cardHome',
                'icons',
                'button-icons',
                'imgLogo',
                'originalTemplate',
                'imgPortada'
            ];
    
            // Actualizar las configuraciones
            if (isset($validatedData['configurations']) && is_array($validatedData['configurations'])) {
                foreach ($validatedData['configurations'] as $config) {
                    foreach ($config as $key => $value) {
                        // Verificar si la clave existe en las configuraciones por defecto
                        if (in_array($key, $defaultConfigurations)) {
                            // Actualizar la configuración existente o crear una nueva
                            CompaniesConfiguration::updateOrCreate(
                                ['company_id' => $companyId, 'configuration_key' => $key],
                                ['configuration_value' => $value]
                            );
                        }
                    }
                }
            }
    
            // Actualizar la compañía con los datos proporcionados
            $company = Companies::where('id', $companyId)->first();
            $company->update(Arr::only($validatedData, ['name', 'address']));
    
            $data = ['company' => $company];
            return $this->apiService->sendResponse($data, 'Configuración guardada correctamente', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }


}