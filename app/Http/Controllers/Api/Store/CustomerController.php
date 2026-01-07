<?php

namespace App\Http\Controllers\Api\Store;

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

        $customers = $query->latest()->paginate(20);

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        // Handle both 'name' (single field) and 'first_name/last_name' (separate fields)
        if ($request->has('name') && !$request->has('first_name')) {
            $nameParts = explode(' ', trim($request->name), 2);
            $request->merge([
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
            ]);
        }

        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? '',
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'notes' => $request->notes,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Customer created',
            'customer' => $customer,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $customer->load(['orders' => fn($q) => $q->latest()->limit(10)]);

        return response()->json(['customer' => $customer]);
    }

    public function update(Request $request, string $storeId, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $customer->update($request->all());

        return response()->json([
            'message' => 'Customer updated',
            'customer' => $customer->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}
