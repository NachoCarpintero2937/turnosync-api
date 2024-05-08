<?php

namespace App\Http\Controllers;

use App\Mail\BirthdayGreetings;
use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use App\Services\ApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;


class ClientController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('auth:api', ['except' => ['create', 'getBirthdayClient']]);
    }

    public function index(Request $request)
    {
        $data = [];
        
        try {
            // Obtener el company_id del usuario logueado
            $company_id = Auth::user()->company_id;
    
            // Filtrar clientes por company_id y status activo (0)
            $clients = Client::where('company_id', $company_id)
                        ->where('status', 0);
                        if ($request->has('id')) {
                            $clients->where('id', $request->id);
                        }
            // Filtrar por date_birthday si está presente en la solicitud
            if ($request->has('date_birthday')) {
                $date_birthday = $request->date_birthday;
                $clients->whereDay('date_birthday', '=', Carbon::parse($date_birthday)->day)
                        ->whereMonth('date_birthday', '=', Carbon::parse($date_birthday)->month);
            }
    
            // Resto del código de filtrado ...
    
            // Obtener clientes ordenados por nombre
            $clients = $clients->orderBy('name')->get();
    
            $data = ['clients' => $clients->toArray()];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }
    public function create(Request $request)
    {
        $userInfo = Auth::user();
        $data = [];
      if ($userInfo) {
            if (!$userInfo->hasPermissionTo('CREATE_CLIENT')) {
                throw new Exception("No tienes permiso para realizar esta acción");
            }
        }
        try {
            // Obtener el company_id del usuario logueado
            $company_id = $userInfo->company_id ?? $request->company_id;

        
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'cod_area' => 'required',
                'phone' => 'required|unique:clients,phone,NULL,id,company_id,'.$company_id,
                'date_birthday' => 'nullable|date',
                
            ], [
                'phone.unique' => 'Este cliente ya se encuentra registrado',
            ]);
            $validatedData['status'] = 0;
            $validatedData['company_id'] = $company_id;
           
            $client = Client::createClient($validatedData);

            $data = ['client' => $client];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Illuminate\Database\QueryException $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function update(Request $request)
    {
        
        $data = [];
        $userInfo =Auth::user();

      

        if (!$request->id) {
            return $this->apiService->sendResponse([], 'El id del cliente es requerido', 400, false);
        }

        $client = Client::find($request->id);

        if (!$client) {
            return $this->apiService->sendResponse([], 'El cliente no fue encontrado', 404, false);
        }

        try {
            if ($userInfo) {
                if (!$userInfo->hasPermissionTo('UPDATE_CLIENT')) {
                    throw new Exception("No tienes permiso para realizar esta acción");
                }
            }
            // Verificar que el cliente pertenece al usuario logueado
            if ($client->company_id !== Auth::user()->company_id) {
                return $this->apiService->sendResponse([], 'No tienes permisos para editar este cliente', 403, false);
            }

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'cod_area' => 'required',
                'phone' => 'required',
                'date_birthday' => 'nullable|date',
            ]);
            $validatedData['company_id'] = $userInfo->company_id ?? $request->company_id;
            $clientUp = $client->updateClient($validatedData);
            $data = [
                'client' => $clientUp
            ];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }

    public function destroy(Request $request)
    {
        try {
            if (!$request->id) {
                return $this->apiService->sendResponse([], 'El id del cliente es requerido', 400, false);
            }

            $client = Client::find($request->id);

            if (!$client) {
                return $this->apiService->sendResponse([], 'El cliente no fue encontrado', 404, false);
            }

            // Verificar que el cliente pertenece al usuario logueado
            if ($client->company_id !== Auth::user()->company_id) {
                return $this->apiService->sendResponse([], 'No tienes permisos para eliminar este cliente', 403, false);
            }

            // Intenta realizar el borrado lógico
            $client->softDeleteClient();

            return $this->apiService->sendResponse([], 'Cliente eliminado con éxito', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }

    public function getBirthdayClient()
    {
        $currentDate = Carbon::now();
        $company_id = Auth::user()->company_id;
        $clients = Client::whereMonth('date_birthday', $currentDate->month)
            ->whereDay('date_birthday', $currentDate->day)
            ->get();
        foreach ($clients as $client) {
            if ($client->email)
                Mail::to($client->email)->send(new BirthdayGreetings($client));
        }
    }
}
