<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AiTestCase;
use App\Services\AITestRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AITestController extends Controller
{
    public function __construct(
        protected AITestRunnerService $testRunner
    ) {}

    /**
     * Get all test cases
     */
    public function index(): JsonResponse
    {
        $testCases = AiTestCase::with('latestResult')->get();

        return response()->json($testCases);
    }

    /**
     * Get test summary
     */
    public function summary(Request $request): JsonResponse
    {
        $businessType = $request->query('business_type');

        $summary = $this->testRunner->getTestSummary($businessType);

        return response()->json($summary);
    }

    /**
     * Create test case
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'business_type' => 'nullable|string|max:50',
            'user_message' => 'required|string',
            'expected_intent' => 'required|string|max:50',
            'expected_fields' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $testCase = AiTestCase::create($validator->validated());

        return response()->json($testCase, 201);
    }

    /**
     * Update test case
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $testCase = AiTestCase::find($id);

        if (!$testCase) {
            return response()->json(['message' => 'Test case not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'business_type' => 'nullable|string|max:50',
            'user_message' => 'sometimes|string',
            'expected_intent' => 'sometimes|string|max:50',
            'expected_fields' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $testCase->update($validator->validated());

        return response()->json($testCase);
    }

    /**
     * Delete test case
     */
    public function destroy(int $id): JsonResponse
    {
        $testCase = AiTestCase::find($id);

        if (!$testCase) {
            return response()->json(['message' => 'Test case not found'], 404);
        }

        $testCase->delete();

        return response()->json(['message' => 'Test case deleted']);
    }

    /**
     * Run single test
     */
    public function runTest(int $id): JsonResponse
    {
        $testCase = AiTestCase::find($id);

        if (!$testCase) {
            return response()->json(['message' => 'Test case not found'], 404);
        }

        $result = $this->testRunner->runSingleTest($testCase);

        return response()->json($result);
    }

    /**
     * Run all tests for business type
     */
    public function runAllTests(Request $request): JsonResponse
    {
        $businessType = $request->input('business_type', 'general');

        $results = $this->testRunner->runTestsForBusinessType($businessType);

        return response()->json($results);
    }
}
