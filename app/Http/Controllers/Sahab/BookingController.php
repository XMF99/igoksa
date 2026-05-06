<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Booking;
use App\Models\Sahab\BookableResource;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * BookingController — إدارة الحجوزات
 *  للمطاعم، الصالونات، العيادات، الفنادق، تأجير المعدات
 */
class BookingController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('bookings')) {
            return $this->upgradePrompt();
        }

        $bookings = Booking::where('tenant_id', $tenant->id)
            ->with(['resource:id,name,type', 'customer:id,name,mobile'])
            ->when($request->date, fn($q, $d) => $q->whereDate('start_at', $d))
            ->orderBy('start_at')
            ->paginate(30);

        return view('sahab.bookings.index', compact('bookings'));
    }

    /**
     * عرض التقويم اليومي/الأسبوعي
     */
    public function calendar(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('bookings')) return $this->upgradePrompt();

        $start = Carbon::parse($request->start ?? now()->startOfWeek());
        $end = Carbon::parse($request->end ?? now()->endOfWeek());

        $bookings = Booking::where('tenant_id', $tenant->id)
            ->whereBetween('start_at', [$start, $end])
            ->with(['resource', 'customer:id,name,mobile'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get()
            ->map(fn($b) => [
                'id'    => $b->id,
                'title' => "{$b->customer?->name} — {$b->resource?->name}",
                'start' => $b->start_at,
                'end'   => $b->end_at,
                'color' => match($b->status) {
                    'pending'    => '#fbbf24',
                    'confirmed'  => '#0F6E56',
                    'checked_in' => '#3b82f6',
                    'completed'  => '#10b981',
                    default      => '#6b7280',
                },
            ]);

        return response()->json($bookings);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('bookings')) return $this->upgradePrompt();

        $validated = $request->validate([
            'resource_id'      => 'required|exists:sahab_bookable_resources,id',
            'customer_id'      => 'nullable|exists:sahab_customers,id',
            'start_at'         => 'required|date|after:now',
            'end_at'           => 'required|date|after:start_at',
            'guest_count'      => 'nullable|integer|min:1',
            'special_requests' => 'nullable|string',
            'total_amount'     => 'nullable|numeric|min:0',
        ]);

        $start = Carbon::parse($validated['start_at']);
        $end = Carbon::parse($validated['end_at']);

        // التحقّق من عدم تعارض الموعد
        $conflict = Booking::where('resource_id', $validated['resource_id'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(fn($q) => $q->whereBetween('start_at', [$start, $end])
                ->orWhereBetween('end_at', [$start, $end])
                ->orWhere(fn($q2) => $q2->where('start_at', '<=', $start)->where('end_at', '>=', $end)))
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'هذا الموعد محجوز بالفعل'], 422);
        }

        $resource = BookableResource::findOrFail($validated['resource_id']);

        $booking = Booking::create($validated + [
            'tenant_id'        => $tenant->id,
            'booking_number'   => 'BK-' . date('Y') . '-' . str_pad(Booking::count() + 1, 5, '0', STR_PAD_LEFT),
            'duration_minutes' => $start->diffInMinutes($end),
            'status'           => $resource->requires_approval ? 'pending' : 'confirmed',
        ]);

        // إشعار العميل
        if ($booking->customer && $booking->customer->mobile) {
            $this->whatsapp->sendBookingConfirmation($booking->customer->mobile, $booking->load('resource'));
        }

        return response()->json(['success' => true, 'booking' => $booking]);
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $this->authorizeTenant($request, $booking);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,checked_in,checked_out,completed,cancelled,no_show',
        ]);

        $booking->update($validated);
        return response()->json(['success' => true, 'booking' => $booking]);
    }

    protected function authorizeTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant_id) abort(403);
    }

    protected function upgradePrompt()
    {
        return response()->json([
            'error'   => 'feature_not_available',
            'message' => 'إدارة الحجوزات متاحة في الباقة الاحترافيّة والمؤسّسيّة.',
            'upgrade_url' => '/app/subscription',
        ], 403);
    }
}
