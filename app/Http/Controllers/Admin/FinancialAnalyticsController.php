<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view.financial_dashboard');
    }

    public function getWeeklyStats(Request $request): JsonResponse
    {
        // Default to current week (Saturday to Friday)
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::SATURDAY);
        $endOfWeek = $now->copy()->endOfWeek(Carbon::FRIDAY);

        $startOfLastWeek = $startOfWeek->copy()->subWeek();
        $endOfLastWeek = $endOfWeek->copy()->subWeek();

        // Current Week Totals
        $currentWeekIncome = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('amount');

        $currentWeekExpense = Expense::where('status', 'completed')
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->sum('amount');

        $currentWeekProfit = $currentWeekIncome - $currentWeekExpense;

        // Last Week Totals (for comparison)
        $lastWeekIncome = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])
            ->sum('amount');

        // Calculate Percentage Change
        $percentageChange = 0;
        if ($lastWeekIncome > 0) {
            $percentageChange = (($currentWeekIncome - $lastWeekIncome) / $lastWeekIncome) * 100;
        } elseif ($currentWeekIncome > 0) {
            $percentageChange = 100; // If last week was 0 and this week is > 0, it's a 100% increase (or technically infinite)
        }

        // Daily Breakdown for Chart
        $dailyIncome = Payment::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $chartData = [];
        $days = ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr'];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
            $chartData[] = round($dailyIncome[$date] ?? 0, 2);
        }

        // Calculate progress percentages (example logic: relative to total income + expense)
        // You might want to adjust this based on what "progress" means in your UI context.
        // Here I'll just use a simple ratio for demonstration, or you can keep it as visual flair.
        // Let's make it relative to the highest value among the three to show scale.
        $maxVal = max($currentWeekIncome, $currentWeekProfit, $currentWeekExpense);
        $incomeProgress = $maxVal > 0 ? ($currentWeekIncome / $maxVal) * 100 : 0;
        $profitProgress = $maxVal > 0 ? ($currentWeekProfit / $maxVal) * 100 : 0;
        $expenseProgress = $maxVal > 0 ? ($currentWeekExpense / $maxVal) * 100 : 0;

        return response()->json([
            'total_earnings' => round($currentWeekIncome, 2),
            'percentage_change' => round($percentageChange, 1),
            'chart_data' => $chartData,
            'breakdown' => [
                [
                    'title' => 'Earnings',
                    'amount' => round($currentWeekIncome, 2),
                    'progress' => round($incomeProgress),
                    'color' => 'primary',
                    'icon' => 'tabler-currency-dollar',
                ],
                [
                    'title' => 'Profit',
                    'amount' => round($currentWeekProfit, 2),
                    'progress' => round($profitProgress),
                    'color' => 'info',
                    'icon' => 'tabler-chart-pie-2',
                ],
                [
                    'title' => 'Expense',
                    'amount' => round($currentWeekExpense, 2),
                    'progress' => round($expenseProgress),
                    'color' => 'error',
                    'icon' => 'tabler-brand-paypal',
                ],
            ],
            'currency' => config('services.payment.default_currency', 'USD'),
        ]);
    }

    public function getStats(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $incomeQuery = Payment::where('status', 'completed');
        $expenseQuery = Expense::where('status', 'completed');

        if ($fromDate) {
            $incomeQuery->whereDate('created_at', '>=', $fromDate);
            $expenseQuery->whereDate('date', '>=', $fromDate);
        }

        if ($toDate) {
            $incomeQuery->whereDate('created_at', '<=', $toDate);
            $expenseQuery->whereDate('date', '<=', $toDate);
        }

        $totalIncome = $incomeQuery->sum('amount');
        $totalExpenses = $expenseQuery->sum('amount');
        $netProfit = $totalIncome - $totalExpenses;

        return response()->json([
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'currency' => config('services.payment.default_currency', 'USD'),
        ]);
    }

    public function getChartData(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date', Carbon::now()->subMonths(11)->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', Carbon::now()->endOfMonth()->toDateString());

        // Helper to generate months array
        $start = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        $months = [];

        while ($start->lte($end)) {
            $months[] = $start->format('Y-m');
            $start->addMonth();
        }

        // Aggregate Income
        $incomeData = Payment::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Aggregate Expenses
        $expenseData = Expense::select(
            DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill data for all months
        $labels = [];
        $incomeDataset = [];
        $expenseDataset = [];
        $profitDataset = [];

        foreach ($months as $month) {
            $labels[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');

            $inc = floatval($incomeData[$month] ?? 0);
            $exp = floatval($expenseData[$month] ?? 0);

            $incomeDataset[] = $inc;
            $expenseDataset[] = $exp;
            $profitDataset[] = $inc - $exp;
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $incomeDataset,
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expenseDataset,
                    'borderColor' => '#dc3545',
                    'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
                ],
                [
                    'label' => 'Net Profit',
                    'data' => $profitDataset,
                    'borderColor' => '#007bff',
                    'backgroundColor' => 'rgba(0, 123, 255, 0.1)',
                ],
            ]
        ]);
    }
}
