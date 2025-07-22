<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Models\Product;
use App\Models\Factory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class ProformaInvoiceController extends Controller
{
    public function SummaryPIandProduct(Request $request)
    {
        $viewBy = $request->get('viewBy', 'pi'); // default is 'pi'
        $selectedMonth = $request->get('month');

        $proformaInvoicesQuery = ProformaInvoice::with('products.jobControls');

        if ($selectedMonth) {
            $parsedMonth = Carbon::parse($selectedMonth);
            $proformaInvoicesQuery->whereMonth('OrderDate', $parsedMonth->month)
                                ->whereYear('OrderDate', $parsedMonth->year);
            // Log::info("📅 Filtering for month: " . $parsedMonth->format('F Y'));
        }

        $proformaInvoices = $proformaInvoicesQuery->get();
        // Log::info("📦 Total PIs fetched: " . $proformaInvoices->count());

        if ($viewBy === 'product') {
            $total = $proformaInvoices->flatMap->products->filter(fn($p) => $p->Status !== 'Finish')->count();
        } else {
            $total = $proformaInvoices->count();
        }
        //Log::info("📊 Total to display: $total");
        $today = \Carbon\Carbon::today();
        $processOrder = ['Casting', 'Stamping', 'Trimming', 'Polishing', 'Setting', 'Plating'];

        $onTime = 0;
        $lateYellow = 0;
        $lateRed = 0;
        $lateDarkRed = 0;
        
        foreach ($proformaInvoices as $pi) {
            $maxDaysLate = 0;
            $isLate = false;

            foreach ($pi->products as $product) {
                if ($product->Status === 'Finish') continue;

                $jobControls = $product->jobControls->keyBy('Process');

                $latestProcess = null;
                foreach ($processOrder as $process) {
                    if (!empty($jobControls[$process]?->AssignDate)) {
                        $latestProcess = $process;
                    }
                }

                if ($latestProcess && isset($jobControls[$latestProcess])) {
                    $job = $jobControls[$latestProcess];
                    $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                    $receiveDate = $job->ReceiveDate;
      
                    // Log::info("🔍 PI #{$pi->id} | Product #{$product->id} | Process: $latestProcess | Schedule: $scheduleDate | Receive: " . ($receiveDate ?? 'null'));

                    if ($scheduleDate->lt($today) && $receiveDate === null) {
                        $isLate = true;
                        $daysLate = $scheduleDate->diffInDays($today);
                        $maxDaysLate = max($maxDaysLate, $daysLate); // Track latest overdue

                        // Log::warning("⛔ LATE → PI #{$pi->id} | Product #{$product->id} | Days late: $daysLate");
                    }
                }
            }

            if ($viewBy === 'product') {
                foreach ($pi->products as $product) {
                    if ($product->Status === 'Finish') continue;

                    $jobControls = $product->jobControls->keyBy('Process');
                    $latestProcess = null;

                    foreach ($processOrder as $process) {
                        if (!empty($jobControls[$process]?->AssignDate)) {
                            $latestProcess = $process;
                        }
                    }

                    if ($latestProcess && isset($jobControls[$latestProcess])) {
                        $job = $jobControls[$latestProcess];
                        $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                        $receiveDate = $job->ReceiveDate;

                        if ($scheduleDate->lt($today) && $receiveDate === null) {
                            $daysLate = $scheduleDate->diffInDays($today);

                            if ($daysLate >= 15) $lateDarkRed++;
                            elseif ($daysLate >= 8) $lateRed++;
                            elseif ($daysLate >= 1) $lateYellow++;
                        } else {
                            $onTime++;
                        }
                    } else {
                        // No process assigned yet → assume on time
                        $onTime++;
                    }
                }
            } else {
                if ($isLate) {
                    if ($maxDaysLate >= 15) $lateDarkRed++;
                    elseif ($maxDaysLate >= 8) $lateRed++;
                    elseif ($maxDaysLate >= 1) $lateYellow++;
                } else {
                    $onTime++;
                }
            }

        }
        // Log::info("✅ countItems: $countItems");
        $late = $lateYellow + $lateRed + $lateDarkRed;
        //Log::info("📊 Totals: $total | ✅ On-time: $onTime | ⛔ Lates: $lateYellow (1-7 days), $lateRed (8-14 days), $lateDarkRed (15+ days)");
        // Log::info("✅ On-time PIs: $onTime");
        // Log::info("⛔ Lates: {$lateYellow} (1-7 days), {$lateRed} (8-14 days), {$lateDarkRed} (15+ days)");

        $groupBy = $request->get('groupBy', 'factory');
        // Log::info("📊 Grouping by: $groupBy");

        $grouped = $groupBy === 'production'
            ? User::where('role', 'Production')->pluck('name', 'id')
            : Factory::pluck('FactoryName', 'id');

        $barChartLabels = [];
        $barChartOnTime = [];
        $barChartLateYellow = [];
        $barChartLateRed = [];
        $barChartLateDarkRed = [];

        if ($groupBy === 'production') {
            $users = User::where('role', 'Production')->get();
            foreach ($users as $user) {
                $label = $user->name;

                $pisForUser = $proformaInvoices->filter(fn($pi) => $pi->user_id === $user->id);

                $onTimeCount = 0;
                $yellow = 0;
                $red = 0;
                $darkRed = 0;

                foreach ($pisForUser as $pi) {
                    $maxDaysLate = 0;
                    $isLate = false;

                    foreach ($pi->products as $product) {
                        if ($product->Status === 'Finish') continue;

                        $jobControls = $product->jobControls->keyBy('Process');
                        $latestProcess = null;

                        foreach ($processOrder as $process) {
                            if (!empty($jobControls[$process]?->AssignDate)) {
                                $latestProcess = $process;
                            }
                        }

                        if ($latestProcess && isset($jobControls[$latestProcess])) {
                            $job = $jobControls[$latestProcess];
                            $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                            $receiveDate = $job->ReceiveDate;

                            if ($scheduleDate->lt($today) && $receiveDate === null) {
                                $isLate = true;
                                $daysLate = $scheduleDate->diffInDays($today);
                                $maxDaysLate = max($maxDaysLate, $daysLate);
                            }
                        }
                    }

                    if ($isLate) {
                        if ($maxDaysLate >= 15) $darkRed++;
                        elseif ($maxDaysLate >= 8) $red++;
                        elseif ($maxDaysLate >= 1) $yellow++;
                    } else {
                        $onTimeCount++;
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;

                // Log::info("👤 $label → ✅ On-time: $onTimeCount | 🟡 Yellow: $yellow | 🔴 Red: $red | 🔥 DarkRed: $darkRed");
            }
        } else {
            $factories = Factory::all();
            foreach ($factories as $factory) {
                $label = $factory->FactoryName;

                $pisForFactory = $proformaInvoices->filter(function ($pi) use ($factory) {
                    foreach ($pi->products as $product) {
                        foreach ($product->jobControls as $job) {
                            if ($job->factory_id == $factory->id) return true;
                        }
                    }
                    return false;
                });

                $onTimeCount = 0;
                $yellow = 0;
                $red = 0;
                $darkRed = 0;

                foreach ($pisForFactory as $pi) {
                    $maxDaysLate = 0;
                    $isLate = false;

                    foreach ($pi->products as $product) {
                        if ($product->Status === 'Finish') continue;

                        $jobControls = $product->jobControls->keyBy('Process');
                        $latestProcess = null;

                        foreach ($processOrder as $process) {
                            if (!empty($jobControls[$process]?->AssignDate)) {
                                $latestProcess = $process;
                            }
                        }

                        if ($latestProcess && isset($jobControls[$latestProcess])) {
                            $job = $jobControls[$latestProcess];
                            $scheduleDate = \Carbon\Carbon::parse($job->ScheduleDate);
                            $receiveDate = $job->ReceiveDate;

                            if ($scheduleDate->lt($today) && $receiveDate === null) {
                                $isLate = true;
                                $daysLate = $scheduleDate->diffInDays($today);
                                $maxDaysLate = max($maxDaysLate, $daysLate);
                            }
                        }
                    }

                    if ($isLate) {
                        if ($maxDaysLate >= 15) $darkRed++;
                        elseif ($maxDaysLate >= 8) $red++;
                        elseif ($maxDaysLate >= 1) $yellow++;
                    } else {
                        $onTimeCount++;
                    }
                }

                $barChartLabels[] = $label;
                $barChartOnTime[] = $onTimeCount;
                $barChartLateYellow[] = $yellow;
                $barChartLateRed[] = $red;
                $barChartLateDarkRed[] = $darkRed;

                // Log::info("🏭 $label → ✅ On-time: $onTimeCount | 🟡 Yellow: $yellow | 🔴 Red: $red | 🔥 DarkRed: $darkRed");
            }
        }

        // Log::info("📊 Final bar chart labels: " . implode(', ', $barChartLabels));
        $page = $request->get('barPage', 1);
        $perPage = 10;

        $totalEntities = count($barChartLabels);
        $start = ($page - 1) * $perPage;

        $barChartLabels = array_slice($barChartLabels, $start, $perPage);
        $barChartOnTime = array_slice($barChartOnTime, $start, $perPage);
        $barChartLateYellow = array_slice($barChartLateYellow, $start, $perPage);
        $barChartLateRed = array_slice($barChartLateRed, $start, $perPage);
        $barChartLateDarkRed = array_slice($barChartLateDarkRed, $start, $perPage);

        $totalPages = ceil($totalEntities / $perPage);


        return view('dashboard.index', compact(
            'total', 'onTime', 'late',
            'lateYellow', 'lateRed', 'lateDarkRed',
            'selectedMonth', 'groupBy', 'grouped',
            'barChartLabels', 'barChartOnTime',
            'barChartLateYellow', 'barChartLateRed', 'barChartLateDarkRed',
            'page', 'totalPages', 'viewBy'
        ));

    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = ProformaInvoice::with(['user', 'products', 'salesPerson']);

        if ($user->role !== 'Admin') {
            $query->where('user_id', $user->id);
        }

        $pis = $query->latest()->get();

        return view('proformaInvoice.index', compact('pis'));
    }

    public function show($id)
    {
        $pi = ProformaInvoice::with(['products'])->findOrFail($id);

        return view('proformaInvoice.detail', compact('pi'));
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $header = $rows[1]; // Row 1 contains column headers
        $groupedByOrderId = [];

        // Group all rows by OrderID (column A)
        foreach ($rows as $index => $row) {
            if ($index === 1) continue; // Skip header
            $orderId = $row['A'] ?? null;
            if ($orderId) {
                $groupedByOrderId[$orderId][] = $row;
            }
        }

        foreach ($groupedByOrderId as $orderId => $orderRows) {
            $firstRow = $orderRows[0];
            $piNumber = trim($firstRow['B']); // OrderCode

            // 🔍 Skip if PI already exists
            if (ProformaInvoice::where('PInumber', $piNumber)->exists()) {
                Log::info("⏩ Skipped duplicate PI: $piNumber");
                return redirect()->back()->with('excel_error', "รหัส PI ซ้ำ: $piNumber");
            }

            // Extract salesID and productionID from CustomerID (e.g., NES-WR/MT)
            $customerIdParts = explode('-', trim($firstRow['D']));
            $suffix = $customerIdParts[1] ?? ''; // "WR/MT"
            [$salesID, $productionID] = explode('/', $suffix . '/'); // default to prevent explode error

            // 🔍 Lookup Sale and Production Users
            $salesUser = User::where('salesID', $salesID)->first();
            $productionUser = User::where('productionID', $productionID)->first();


            $pi = ProformaInvoice::create([
                'PInumber'             => $piNumber,
                'byOrder'              => trim($firstRow['C']),
                'CustomerID'           => trim($firstRow['D']),
                'CustomerPO'           => trim($firstRow['I']),
                'CustomerInstruction'  => trim($firstRow['M']),
                'FOB'                  => floatval($firstRow['X']),
                'FreightPrepaid'       => floatval($firstRow['N']),
                'InsurancePrepaid'     => floatval($firstRow['O']),
                'Deposit'              => floatval($firstRow['P']),
                'OrderDate'            => $this->parseExcelDate($firstRow['F']),
                'SalesPerson'          => $salesUser?->id,
                'user_id'              => $productionUser?->id,
            ]);

            foreach ($orderRows as $row) {
                $quantity = trim($row['U']) . ' ' . trim($row['V']);

                Product::updateOrCreate(
                    ['ProductNumber' => trim($row['Q'])],
                    [
                        'Description'           => trim($row['R']),
                        'ProductCustomerNumber' => trim($row['S']),
                        'Weight'                => floatval($row['T']),
                        'Quantity'              => $quantity,
                        'UnitPrice'             => floatval($row['W']),
                        'proforma_invoice_id'   => $pi->id,
                    ]
                );
            }

            Log::info("✅ Imported PI: {$pi->PInumber} with " . count($orderRows) . " products.");
        }


        return redirect()->back()->with('excel_success', '📥 นำเข้าข้อมูล Excel สำเร็จแล้ว');
    }

    private function parseExcelDate($excelDate)
    {
        if (!$excelDate) return null;

        try {
            if (is_numeric($excelDate)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate);
            } else {
                return Carbon::parse($excelDate);
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Date parse failed: " . $excelDate);
            return null;
        }
    }


}
