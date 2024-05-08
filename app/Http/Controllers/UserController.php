<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use App\Models\Service;
use App\Models\User;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('auth:api', ['except' => ['create']]);
    }

    public function index(Request $request)
    {
        $data = [];
        try {
            $company_id = Auth::user()->company_id;
            $users = User::with('roles')->where('company_id', $company_id);
            if (!empty($request->id)) {
                $users->where('id', $request->id);
            }
            $users = $users->get(); // Ejecutar la consulta y almacenar los resultados
            $data = ['users' => $users];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function usersToShift(Request $request)
    {
        $data = [];
        try {
            // Obtener el company_id del usuario autenticado
            $userInfo = Auth::user();
            $companyId = $userInfo->company_id;
            // Consulta para obtener los usuarios
            $usersQuery = User::where('company_id', $companyId)
                ->where('status', 1);
            if ($userInfo) {
                if (!$userInfo->hasPermissionTo('BACKEND_ALL_USER_HOME_SHIFT')) {
                    $usersQuery->where('id', Auth::id());
                }
            }

            // Obtener los usuarios con sus turnos dentro del rango de fechas
            $users = $usersQuery->with(['shifts.service.price', 'shifts.client'])
                ->with(['shifts' => function ($query) use ($request) {
                    // Filtrar los turnos dentro del rango de fechas y por status
                    if ($request->start_date && $request->end_date) {
                        $query->whereBetween('date_shift', [$request->start_date, $request->end_date])
                            ->where('status', '!=', 2);
                    }
                    // Filtrar por company_id del cliente
                    $companyId = Auth::user()->company_id;
                    $query->whereHas('client', function ($clientQuery) use ($companyId) {
                        $clientQuery->where('company_id', $companyId);
                    });
                    $query->whereHas('service', function ($serviceQuery) use ($companyId) {
                        $serviceQuery->where('company_id', $companyId);
                    });
                    $query->orderBy('date_shift', 'asc');
                }])
                ->get();

            // Obtener la lista única de IDs de servicios para el rango de fechas
            $serviceIds = $users->flatMap->shifts->pluck('service_id')->unique();

            // Obtener la cantidad total de servicios únicos
            $allServicesCount = $serviceIds->count();

            // Obtener los detalles completos de los servicios
            $serviceObjects = Service::with('price')->whereIn('id', $serviceIds)->get();
            // Agregar la cuenta de turnos para cada servicio en today_service
            $serviceObjects->each(function ($service) use ($users, $request) {
                $service->count = $users->flatMap->shifts
                    ->where('service_id', $service->id)
                    ->whereBetween('date_shift', [$request->start_date, $request->end_date])
                    ->count();
            });
            // Construir el conjunto de datos de respuesta
            $data = [
                'users' => $users,
                'all_shifts_count' => $users->flatMap->shifts->count(),
                'all_clients_count' => $users->flatMap->shifts->pluck('client_id')->unique()->count(),
                'all_services_count' => $allServicesCount,
                'today_service' => $serviceObjects,
            ];

            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }


    public function create(Request $request)
    {
        $data = [];
        $company_id = null;
        try {
            // Obtener el ID de la empresa del usuario autenticado
            if (!empty(Auth::user())) {
                $company_id = Auth::user()->company_id;
            }


            // Validar los datos del formulario
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'password' => 'required|string|min:6', // Nuevo campo para el nombre de la empresa
                'address' => 'nullable|string|max:255', // Dirección opcional
                'latitud' => 'nullable|string', // Latitud opcional
                'longitud' => 'nullable|string', // Longitud opcional
            ]);

            // Hash de la contraseña
            $validatedData['password'] = Hash::make($validatedData['password']);
            if (User::where('email', $validatedData['email'])->exists()) {
                throw new \Exception("Ya tenemos registrado un usuario con este email");
            }
            // Crear la empresa
            if (empty($company_id)) {
                $company = Companies::create([
                    'name' => $validatedData['company_name'],
                    'address' => $validatedData['address'],
                    'latitud' => $validatedData['latitud'],
                    'longitud' => $validatedData['longitud'],
                    'status' => 1
                ]);

                // Asegurarse de que la empresa se haya creado correctamente
                if (!$company) {
                    throw new \Exception("Error al crear la empresa.");
                }
                $validatedData['company_id'] = $company->id;
                $validatedData['company_name'] = $request->company_name;
            } else {
                // Si está logueado entonces asignamos el company id del usuario logueado
                $company = Companies::findOrFail($company_id);
                $validatedData['status'] = 1;
                $validatedData['company_id'] = $company_id;
            }

            if ($request->role == 'master' && !empty($company_id)) {
                throw new \Exception("Rol no permitido");
            }

            // Crear el usuario
            $user = User::create($validatedData);
            // Si no llega el rol asignamos un rol MASTER (ACCESO A TODO) automáticamente

            $role = Role::where('name', $request->role ? $request->role : 'master')->first(); // Asignar el rol "master" por defecto
            $user->assignRole($role);


            // Preparar los datos de respuesta
            $data = [
                'user' => $user,
                'company' => $company,
            ];

            // Enviar una respuesta exitosa
            return $this->apiService->sendResponse($data, 'Usuario creado correctamente', 200, true);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y enviar una respuesta de error
            $message =  $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function getAllPermissions()
    {
        $data = [];
        try {
            // Obtener todos los permisos
            $permissions = Permission::all();

            return $this->apiService->sendResponse($permissions, 'Permisos obtenidos correctamente', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 500, false);
        }
    }

    public function createRole(Request $request)
    {
        $data = [];
        try {
            // Validar los datos del formulario
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:roles,name,' . $request->id,
            ]);

            // Comprobar si la validación falla
            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            // Buscar el rol por su ID si se proporciona
            $role = $request->id ? Role::find($request->id) : new Role;

            // Verificar si se encontró el rol y asignar los nuevos datos
            if (!$role) {
                throw new \Exception("Rol no encontrado");
            }

            $role->name = $request->name;
            $role->save();

            // Si se proporcionan permisos, asignarlos al rol
            if ($request->permissions) {
                $this->assignPermissionsToRole($request, $role);
            }

            // Preparar los datos de respuesta
            $data = ['role' => $role];

            // Enviar una respuesta exitosa
            return $this->apiService->sendResponse($data, 'Role ' . ($request->id ? 'updated' : 'created') . ' successfully.', 200, true);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y enviar una respuesta de error
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }
    public function getAllRolesWithPermissions(Request $request)
    {
        $data = [];
        try {
            $userRoles = Auth::user()->getRoleNames();
            if ($userRoles->contains('user')) {
                // Busca el rol específico junto con sus permisos asociados si se proporciona un nombre de rol
                if ($request->has('name')) {
                    $role = Role::where('name', $request->name)->with('permissions')->first();
                } else {
                    $role = Role::where('name', '!=', 'master')->where('name', '!=', 'admin')->with('permissions')->get();
                }

                // Verifica si se encontró el rol
                if ($role) {
                    $data['roles'] = $role;
                    return $this->apiService->sendResponse($data, '', 200, true);
                } else {
                    throw new \Exception("Rol no encontrado");
                }
            } else {
                // Si no se proporciona un nombre de rol, devuelve todos los roles con sus permisos
                $roles = Role::with('permissions')->get();
                $data['roles'] = $roles;
                return $this->apiService->sendResponse($data, '', 200, true);
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 500, false);
        }
    }


    private function assignPermissionsToRole(Request $request, Role $role)
    {
        $data = [];

        try {
            // Verifica si se proporciona una lista de permisos en la solicitud
            if (!$request->has('permissions') || !is_array($request->permissions)) {
                throw new \Exception("La lista de permisos no es válida o no se proporciona");
            }

            // Obtén los permisos proporcionados en la solicitud
            $permissionNames = $request->permissions;

            // Busca los objetos Permission correspondientes a los nombres de permisos proporcionados
            $permissions = Permission::whereIn('name', $permissionNames)->get();

            // Asigna los permisos al rol
            $role->syncPermissions($permissions);

            return $this->apiService->sendResponse($role, 'Permisos asignados al rol correctamente', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 500, false);
        }
    }

    public function assignRoleToUser(Request $request)
    {
        $data = [];
        try {
            $userId = Auth::user();
            // Buscar el usuario por su ID o cualquier otro identificador único
            $user = User::findOrFail($request->user_id ? $request->id : $userId);

            // Verificar si se proporciona el nombre del rol en la solicitud
            if (!$request->has('role_name')) {
                throw new \Exception("Nombre del rol no proporcionado");
            }

            // Buscar el rol por su nombre
            $role = Role::where('name', $request->role_name)->first();

            // Verificar si se encontró el rol
            if (!$role) {
                throw new \Exception("Rol no encontrado");
            }

            // Asignar el rol al usuario
            $user->assignRole($role);

            $data['user'] = $user;
            $data['role'] = $role;

            // Envía una respuesta exitosa
            return $this->apiService->sendResponse($data, 'Rol asignado al usuario exitosamente', 200, true);
        } catch (\Exception $e) {
            // Captura cualquier excepción y envía una respuesta de error
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 500, false);
        }
    }

    public function suspendUser(Request $request)
    {
        $data = [];
        $userInfo = Auth::user();
        try {
            if ($userInfo) {
                if (!$userInfo->hasPermissionTo('SUSPEND_USER')) {
                    throw new \Exception("No tienes permiso para realizar esta acción");
                }
            }


            // Verificar si se proporciona un user_id y un status válido en la solicitud
            if ($request->has('user_id') && in_array($request->status, [0, 1])) {
                // Obtener el usuario por ID
                $user = User::find($request->user_id);

                // Verificar si se encontró el usuario
                if ($user) {
                    // Actualizar el status del usuario
                    $user->status = $request->status;
                    $user->save();

                    $data['user'] = $user;
                    $message = $request->status == 0 ? "Usuario suspendido exitosamente" : "Usuario activado exitosamente";
                    return $this->apiService->sendResponse($data, $message, 200, true);
                } else {
                    throw new \Exception("Usuario no encontrado");
                }
            } else {
                throw new \Exception("Datos de usuario no válidos");
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 500, false);
        }
    }
}
