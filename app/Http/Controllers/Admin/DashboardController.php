<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // 1. Financial Stats
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $totalExpenses = Expense::where('status', 'completed')->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses;

        // 2. User Stats
        $totalStudents = User::role('student')->count();
        if ($totalStudents === 0) {
            // Fallback if roles are not strictly used or seeded
            $totalStudents = User::count();
        }

        // Active Students (accessed a course in the last 30 days)
        $activeStudents = CourseEnrollment::where('last_accessed_at', '>=', Carbon::now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        // 3. Course Stats
        $totalCourses = Course::count();
        $activeCourses = Course::where('status', 'published')->count(); // Assuming 'published' status exists

        // Course Completion Rate
        $totalEnrollments = CourseEnrollment::count();
        $completedEnrollments = CourseEnrollment::where('is_completed', true)->count();
        $completionRate = $totalEnrollments > 0 ? ($completedEnrollments / $totalEnrollments) * 100 : 0;

        // 4. Revenue Breakdown by Category (Simplified)
        // Linking Payment -> UserEntitlement -> BillingPlan -> Course -> Category
        // This is complex due to indirect relationships. 
        // Alternative: Group by Course Category directly if possible.
        // For now, let's group by Course if we can link Payments to Courses.
        // Payment -> Receipt -> Course (via item_id if item_type is course)
        // Or Payment -> Entitlement -> BillingPlan -> Course

        // Let's try to get revenue by course via Receipts for simplicity if Receipts link to Courses
        $revenueByCourse = DB::table('receipts')
            ->join('courses', 'receipts.item_id', '=', 'courses.id') // Assuming item_id is course_id when item_type is course
            ->where('receipts.item_type', 'course') // Adjust based on actual item_type values
            ->select('courses.title', DB::raw('SUM(receipts.amount) as total'))
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // If receipts don't link directly, we might need another approach.
        // Let's check if 'revenueByCourse' is empty and fallback or just return empty for now.

        // 5. Enrollment Trends (Last 30 days)
        $enrollmentsTrend = CourseEnrollment::select(
            DB::raw("DATE_FORMAT(enrolled_at, '%Y-%m-%d') as date"),
            DB::raw('count(*) as count')
        )
            ->where('enrolled_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 6. Top Performing Courses (by Enrollments)
        $topCourses = Course::withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title, // Translatable field handled by model accessor/array conversion?
                    'enrollments_count' => $course->enrollments_count,
                    'category' => $course->category ? $course->category->name : 'Uncategorized',
                ];
            });

        // 7. Recent Enrollments
        $recentEnrollments = CourseEnrollment::with(['user', 'course'])
            ->latest('enrolled_at')
            ->limit(5)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'student_name' => $enrollment->user ? $enrollment->user->full_name : 'Unknown',
                    'course_title' => $enrollment->course ? $enrollment->course->title : 'Unknown',
                    'enrolled_at' => $enrollment->enrolled_at,
                    'status' => $enrollment->is_completed ? 'Completed' : 'In Progress',
                ];
            });

        return response()->json([
            'financials' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'currency' => config('services.payment.default_currency', 'EGP'),
            ],
            'users' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
            ],
            'courses' => [
                'total' => $totalCourses,
                'active' => $activeCourses,
                'completion_rate' => round($completionRate, 1),
                'top_performing' => $topCourses,
                'recent_enrollments' => $recentEnrollments,
            ],
            'charts' => [
                'enrollments' => $enrollmentsTrend,
                'revenue_by_course' => $revenueByCourse,
            ],
        ]);
    }
}
