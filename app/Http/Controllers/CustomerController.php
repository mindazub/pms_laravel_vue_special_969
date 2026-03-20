<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:customers,name'],
            'description' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($customer, 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customers', 'name')->ignore($customer->id)],
            'description' => ['nullable', 'string'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }
}
