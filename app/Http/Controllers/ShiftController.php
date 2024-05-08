<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Mail\Recordatory;
use App\Mail\TurnAssigned;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\Shift;
use App\Services\ApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('auth:api', ['except' => ['sendEmailsForNewShiftsInFiveMinutes']]);
        // sendEmailsForNewShiftsInFiveMinutes
    }

    public function index(Request $request)
    {
        $data = [];
        try {
            $query = Shift::with(['user', 'service', 'client', 'service.price']);
            $query->whereHas('client', function ($clientQuery) {
                $clientQuery->where('company_id', Auth::user()->company_id);
            });

            $userRoles = Auth::user()->getRoleNames();
            if ($userRoles->contains('user')) {
                // Si el usuario tiene el rol 'user', filtrar por su ID
                $query->where('user_id', Auth::id());
            }

            if ($request->has('id')) {
                $query->where('id', $request->id);
            }

            // Filtrar por client_id (utilizando la relación con clients y company_id)
            if ($request->has('client_id')) {
                $query->whereHas('client', function ($clientQuery) use ($request) {
                    $clientQuery->where('id', $request->client_id)
                        ->where('company_id', Auth::user()->company_id);
                });
            }

            // Filtrar por service_id
            if ($request->has('service_id')) {
                $query->where('service_id', $request->service_id);
            }

            // Filtrar por status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtrar por rango de fechas (date_shift)
            if ($request->has('start_date') && $request->has('end_date')) {
                $start_date = $request->start_date;
                $end_date = $request->end_date;
                $query->whereBetween('date_shift', [$start_date, $end_date]);
            }

            // Filtrar por fecha única (date_shift)
            if ($request->has('date_shift')) {
                $query->whereDate('date_shift', $request->date_shift);
            }

            // Ordenar por date_shift
            $query->orderBy('date_shift', 'desc');

            $shifts = $query->get();

            $data = [
                "shifts" => $shifts
            ];
            $statusCode = 200;
            return $this->apiService->sendResponse($data, '', $statusCode, true);
        } catch (Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function create(Request $request)
    {
        $data = [];

        try {
            $validatedData = $request->validate([
                'service_id' => 'required|exists:services,id',
                'client_id' => 'required|exists:clients,id',
                'user_id' => 'required|exists:users,id',
                'date_shift' => 'required|date',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'status' => 'required|integer',
            ]);

            // Obtén el nombre del servicio
            $serviceName = Service::find($validatedData['service_id'])->name;

            // Validación personalizada para verificar si el usuario tiene un turno en el rango de 15 minutos
            $userHasShiftWithinRange = Shift::where('user_id', $validatedData['user_id'])
                ->where('status', 0) // Agregar condición para el estado 0
                ->whereBetween('date_shift', [
                    Carbon::parse($validatedData['date_shift'])->subMinutes(15),
                    Carbon::parse($validatedData['date_shift'])->addMinutes(15),
                ])
                ->exists();

            if ($userHasShiftWithinRange) {
                throw new \Exception('El usuario ya tiene un turno asignado dentro del rango de 15 minutos.');
            }

            $shift = Shift::createShift($validatedData);
            $data = [
                'shift' => $shift,
                'serviceName' => $serviceName, // Agrega el nombre del servicio al array de datos
            ];

            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    public function createShiftToSelected(Request $request)
    {

        try {
            $validatedData = $request->validate([
                'ids' => 'required|string',
                'date_shift' => 'required|date',
                'user_id' => 'required|exists:users,id',
            ]);

            // Obtener los IDs de los turnos
            $ids = explode(',', $validatedData['ids']);

            $shiftsData = [];
            $shiftsNotCreated = [];


            // Iterar sobre los IDs de los turnos
            foreach ($ids as $id) {
                // Buscar el turno por su ID
                $shift = Shift::with('client')->find($id);
                if ($shift) {

                    $fecha = Carbon::parse($request->date_shift)->format('Y-m-d');
                    $hora = Carbon::parse($shift->date_shift)->format('H:i:s');
                    $newShiftData['date_shift'] = Carbon::parse("$fecha $hora");

                    $userHasShiftWithinRange = Shift::where('user_id', $request->user_id)
                        ->where('status', 0)
                        ->whereBetween('date_shift', [
                            Carbon::parse($newShiftData['date_shift'])->subMinutes(30),
                            Carbon::parse($newShiftData['date_shift'])->addMinutes(30),
                        ])
                        ->exists();

                    if ($userHasShiftWithinRange) {
                        $shiftsNotCreated[] = $shift->client->name;
                    } else {
                        // Clonar el turno actual y cambiar los datos necesarios
                        // $newShiftData = $shift->toArray();
                        $newShiftData['user_id'] = $request->user_id;
                        $newShiftData['service_id'] = $shift->service_id;
                        $newShiftData['client_id'] = $shift->client_id;
                        $newShiftData['price'] = $shift->price;
                        $newShiftData['status'] = 0;
                        $newShiftData['description'] = $shift->description;

                        // Agregar los datos del nuevo turno al array
                        $shiftsData[] = $newShiftData;
                    }
                }
            }
            $insert = Shift::insert($shiftsData);
            // Si no se crearon turnos, devolver los nombres de los clientes en lugar de los IDs
            if (!empty($shiftsNotCreated) && !empty($shiftsData)) {
                return $this->apiService->sendResponse(['message' => 'Turnos creados,algunos turnos existen dentro de los 30 minutos', 'data' => $shiftsNotCreated], '', 200, true);
            } else if (!empty($shiftsNotCreated) && empty($shiftsData)) {
                return $this->apiService->sendResponse([], 'Ningún turno fue creado ya que se superponen con otros', 400, false);
            }

            // Crear los nuevos turnos

            // Retornar la respuesta con los turnos creados
            return $this->apiService->sendResponse(['shifts' => $shiftsData], 'Turnos creados exitosamente', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }

    public function update(Request $request)
    {
        $data = [];
        if (!$request->id) {
            return $this->apiService->sendResponse([], 'El id del turno es requerido', 404, false);
        }
        $shift = Shift::find($request->id);

        if (!$shift) {
            return $this->apiService->sendResponse([], 'El turno no fue encontrado', 404, false);
        }

        try {
            $validatedData = $request->validate([
                'service_id' => 'required|exists:services,id',
                'price' => 'required',
                'client_id' => 'required|exists:clients,id',
                'user_id' => 'required|exists:users,id',
                'date_shift' => 'required|date',
                'description' => 'nullable|string',
                'status' => 'required'
            ]);
            $shiftUp = $shift->updateShift($validatedData);
            $data = [
                'shift' => $shiftUp
            ];
            return $this->apiService->sendResponse($data, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }

    public function updateStatus(Request $request)
    {
        try {

            if (!$request->id) {
                return $this->apiService->sendResponse([], 'El id del turno es requerido', 404, false);
            }

            $shift = Shift::find($request->id);

            if (!$shift) {
                return $this->apiService->sendResponse([], 'El turno no existe', 404, false);
            }

            $validatedData = $request->validate([
                'status' => 'required',
                'price' => 'required',
                'description' => 'nullable|string',
            ]);

            $shift->update($validatedData);

            return $this->apiService->sendResponse($shift, 'Status actualizado correctamente', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }

    public function destroy(Request $request)
    {
        try {

            if (!$request->id) {
                return $this->apiService->sendResponse([], 'El id del turno es requerido', 404, false);
            }

            $shift = Shift::find($request->id);

            if (!$shift) {
                return $this->apiService->sendResponse([], 'El turno no fue encontrado', 404, false);
            }

            $shift->deleteShift();
            return $this->apiService->sendResponse([], 'Turno eliminado con éxito', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }
    public function getShiftsForTomorrowAndSendReminderEmail()
    {
        $data = [];
        try {
            $tomorrowDate = Carbon::tomorrow()->toDateString();

            $query = Shift::with(['user', 'service', 'client', 'service.price'])
                ->whereDate('date_shift', $tomorrowDate)
                ->where('status', 0);

            $shiftsForTomorrow = $query->get();

            // Envía el correo electrónico de recordatorio a cada cliente
            $countEmail = 0;
            foreach ($shiftsForTomorrow as $shift) {
                $countEmail++;
                $clientEmail = $shift->client->email; // Asumiendo que la relación con el cliente está configurada correctamente
                $mailValidator = [
                    "name" => $shift->client->name,
                    "service" => $shift->service->name
                ];
                // Verifica si el cliente tiene una dirección de correo electrónico antes de enviar el correo
                if ($clientEmail) {
                    Mail::to($clientEmail)->send(new Recordatory($mailValidator));
                }
            }

            $data = [
                "countEmails" => $countEmail
            ];
            $statusCode = 200;
            return $this->apiService->sendResponse($data, '', $statusCode, true);
        } catch (Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse($data, $message, 400, false);
        }
    }

    // emails
    public function sendEmailsForNewShiftsInFiveMinutes()
    {
        // Extraer horas de inicio y fin
        $currentTime = Carbon::now()->subMinutes(5);
        $endTime = $currentTime->copy()->addMinutes(5);
        var_dump($currentTime->format('Y-m-d H:i:s'));
        var_dump($endTime->format('Y-m-d H:i:s'));

        // Consulta SQL directa con BETWEEN
        $consulta = "SELECT
        clients.name AS client_name,
        shifts.date_shift,
        clients.email,
        services.name AS service_name,
        companies.name AS company_name,
        companies.address AS address,
        GROUP_CONCAT(companies_configurations.configuration_key, ': ', companies_configurations.configuration_value SEPARATOR '; ') AS configurations
    FROM
        shifts
    INNER JOIN
        clients ON shifts.client_id = clients.id
    INNER JOIN
        services ON shifts.service_id = services.id
    INNER JOIN
        companies ON clients.company_id = companies.id
    INNER JOIN
        companies_configurations ON companies.id = companies_configurations.company_id
            WHERE shifts.created_at BETWEEN '" . $currentTime . "' AND '" . $endTime . "'
       GROUP BY
       clients.name,clients.email, shifts.date_shift, services.name, companies.name, companies.address; ";

        // Ejecuta la consulta SQL
        $newShifts = DB::select($consulta);
        foreach ($newShifts as $shift) {
            $this->sendEmailForNewShift($shift);
        }
    }

    public function sendEmailForNewShift($shift)
    {
        // Dividir las configuraciones concatenadas en un array
        $configurations = explode('; ', $shift->configurations);

        // Crear un array asociativo para las configuraciones
        $configArray = [];
        foreach ($configurations as $config) {
            list($key, $value) = explode(': ', $config, 2);
            $configArray[$key] = $value;
        }

        // Definir los datos del correo electrónico
        $mailData = [
            'clientName' => $shift->client_name,
            'shiftDate' => $shift->date_shift,
            'serviceName' => $shift->service_name,
            'companyName' => $shift->company_name,
            'address' => $shift->address,
            'configurations' => $configArray // Pasar el array de configuraciones
        ];
        // Verificar si el correo electrónico no está vacío
        if (!empty($shift->email)) {
            // Enviar el correo electrónico con los datos proporcionados
            Mail::to($shift->email)
                ->send(new TurnAssigned($mailData));
        }
    }

    public function reportsMonth()
    {
        $userInfo = Auth::user();
        try {
            // Obtener el total de precios agrupados por mes para registros con status 1
            $monthlyTotals = DB::table('shifts')
                ->select(DB::raw('YEAR(date_shift) as year'), DB::raw('MONTH(date_shift) as month'), DB::raw('SUM(price) as total'))
                ->where('shifts.status', 1)
                ->join('clients', 'shifts.client_id', '=', 'clients.id')
                ->where('clients.company_id', $userInfo->company_id);
            if ($userInfo->getRoleNames()->contains('user')) {
                $monthlyTotals->where('user_id', $userInfo->id);
            }
            $monthlyTotals =  $monthlyTotals->groupBy(DB::raw('YEAR(date_shift)'), DB::raw('MONTH(date_shift)'))->get();




            // Obtener la cantidad de reservas canceladas (estado 2) por mes
            $canceledCounts = DB::table('shifts')
                ->select(DB::raw('YEAR(date_shift) as year'), DB::raw('MONTH(date_shift) as month'), DB::raw('COUNT(*) as canceled_count'))
                ->where('shifts.status', 2)
                ->join('clients', 'shifts.client_id', '=', 'clients.id')
                ->where('clients.company_id', $userInfo->company_id);
            if ($userInfo->getRoleNames()->contains('user')) {
                $canceledCounts->where('user_id', $userInfo->id);
            }

            $canceledCounts = $canceledCounts->groupBy(DB::raw('YEAR(date_shift)'), DB::raw('MONTH(date_shift)'))->get();

            // Crear un array asociativo para almacenar los totales, reservas canceladas y totales por año
            $totalPrices = [];
            $cancelledShifts = [];
            $totalYears = [];
            $allMonths = [
                'january', 'february', 'march', 'april', 'may', 'june',
                'july', 'august', 'september', 'october', 'november', 'december'
            ];

            foreach ($allMonths as $month) {
                $monthName = trans('date.months.' . $month);
                $totalPrices[$monthName] = 0;
                $cancelledShifts[$monthName] = 0;
            }

            foreach ($monthlyTotals as $total) {
                $year = $total->year;
                $monthName = trans('date.months.' . strtolower(Carbon::create()->month($total->month)->format('F')));

                // Inicializar el contador de precios por año si aún no existe
                if (!isset($totalYears[$year])) {
                    $totalYears[$year] = 0;
                }

                $totalYears[$year] += (float) $total->total;

                $totalPrices[$monthName] = (float) $total->total;
            }

            foreach ($canceledCounts as $canceledCount) {
                $monthName = trans('date.months.' . strtolower(Carbon::create()->month($canceledCount->month)->format('F')));
                $cancelledShifts[$monthName] = (int) $canceledCount->canceled_count;
            }

            // Devolver la respuesta exitosa
            $data = [
                'totalPrices' => $totalPrices,
                'cancelled_shifts' => $cancelledShifts,
                'totalYears' => $totalYears,
            ];

            $statusCode = 200;
            return $this->apiService->sendResponse($data, '', $statusCode, true);
        } catch (\Exception $e) {
            // En caso de excepción, devolver la respuesta de error
            $data = ['error' => $e->getMessage()];
            $statusCode = 500;
            return $this->apiService->sendResponse($data, '', $statusCode, false);
        }
    }

    public function notifications()
    {
        try {
            $today = Carbon::now();
            $tenDaysAgo = $today->subDays(30);

            // Obtener el día de ayer
            $yesterday = Carbon::yesterday();

            // Obtener los turnos pendientes dentro del rango de fecha
            $notifications = Shift::with('client', 'service')
                ->where('status', 0)
                ->whereBetween('date_shift', [$tenDaysAgo, $yesterday])
                ->whereHas('client', function ($clientQuery) {
                    $clientQuery->where('company_id', Auth::user()->company_id);
                })
                ->get();

            return $this->apiService->sendResponse($notifications, '', 200, true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->apiService->sendResponse([], $message, 400, false);
        }
    }
}
