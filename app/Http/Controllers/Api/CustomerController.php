<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::withCount('orders')
            ->withSum('orders', 'total');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $customers = $query->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string',
        ]);

        // Check if customer exists with this email
        $existing = Customer::where('email', $request->email)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Customer with this email already exists',
            ], 422);
        }

        $customer = Customer::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => $customer,
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $customer->load(['orders' => function ($q) {
            $q->latest()->limit(10);
        }]);

        $customer->loadCount('orders');
        $customer->loadSum('orders', 'total');

        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'addresses' => 'nullable|array',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($request->has('email') && $request->email !== $customer->email) {
            $existing = Customer::where('email', $request->email)
                ->where('id', '!=', $customer->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Customer with this email already exists',
                ], 422);
            }
        }

        $data = $request->except('password');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $customer->update($data);

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer->fresh(),
        ]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    public function orders(Request $request, Customer $customer): JsonResponse
    {
        $orders = $customer->orders()
            ->with('items')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    public function addAddress(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:50',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ]);

        $addresses = $customer->addresses ?? [];

        if ($request->boolean('is_default')) {
            $addresses = array_map(function ($addr) {
                $addr['is_default'] = false;
                return $addr;
            }, $addresses);
        }

        $addresses[] = [
            'id' => uniqid(),
            'label' => $request->label,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'is_default' => $request->boolean('is_default'),
        ];

        $customer->update(['addresses' => $addresses]);

        return response()->json([
            'message' => 'Address added',
            'customer' => $customer->fresh(),
        ]);
    }

    public function removeAddress(Request $request, Customer $customer, string $addressId): JsonResponse
    {
        $addresses = collect($customer->addresses ?? [])
            ->filter(fn($addr) => $addr['id'] !== $addressId)
            ->values()
            ->all();

        $customer->update(['addresses' => $addresses]);

        return response()->json([
            'message' => 'Address removed',
            'customer' => $customer->fresh(),
        ]);
    }
}
