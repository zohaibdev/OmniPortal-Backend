<?php

namespace App\Services;

use App\Models\Tenant\AiTestCase;
use App\Models\Tenant\AiTestResult;
use Illuminate\Support\Facades\Log;

class AITestRunnerService
{
    public function __construct(
        protected AIAgentService $aiAgent,
        protected OpenAIService $openai
    ) {}

    /**
     * Run all active test cases for a business type
     */
    public function runTestsForBusinessType(string $businessType): array
    {
        $testCases = AiTestCase::active()
            ->forBusinessType($businessType)
            ->get();

        $results = [
            'total' => $testCases->count(),
            'passed' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->runSingleTest($testCase);
            
            if ($result['status'] === AiTestResult::STATUS_PASS) {
                $results['passed']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = $result;
        }

        return $results;
    }

    /**
     * Run a single test case
     */
    public function runSingleTest(AiTestCase $testCase): array
    {
        try {
            // Build test messages
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are analyzing a customer message to determine intent and extract information. Return JSON with: intent, extracted_fields',
                ],
                [
                    'role' => 'user',
                    'content' => "Customer message: {$testCase->user_message}\n\nAnalyze this message and extract:\n- Intent (what customer wants)\n- Relevant fields based on intent\n\nReturn as JSON.",
                ],
            ];

            $response = $this->openai->chat($messages);

            if (!$response) {
                return $this->createFailedResult($testCase, 'AI response failed', null);
            }

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return $this->createFailedResult($testCase, 'Empty AI response', null);
            }

            // Parse AI response
            $aiData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFailedResult($testCase, 'Invalid JSON response', $content);
            }

            // Validate intent
            $actualIntent = $aiData['intent'] ?? null;
            $expectedIntent = $testCase->expected_intent;

            $intentMatches = $this->compareIntents($expectedIntent, $actualIntent);

            // Validate fields
            $actualFields = $aiData['extracted_fields'] ?? [];
            $expectedFields = $testCase->expected_fields ?? [];

            $fieldsMatch = $this->compareFields($expectedFields, $actualFields);

            // Determine pass/fail
            $passed = $intentMatches && $fieldsMatch;

            // Create result
            $result = AiTestResult::create([
                'ai_test_case_id' => $testCase->id,
                'status' => $passed ? AiTestResult::STATUS_PASS : AiTestResult::STATUS_FAIL,
                'actual_intent' => $actualIntent,
                'actual_fields' => $actualFields,
                'ai_response' => $content,
                'error_details' => $passed ? null : [
                    'intent_match' => $intentMatches,
                    'fields_match' => $fieldsMatch,
                    'expected_intent' => $expectedIntent,
                    'expected_fields' => $expectedFields,
                ],
                'tested_at' => now(),
            ]);

            return [
                'test_case_id' => $testCase->id,
                'test_case_name' => $testCase->name,
                'status' => $result->status,
                'intent_match' => $intentMatches,
                'fields_match' => $fieldsMatch,
                'actual_intent' => $actualIntent,
                'expected_intent' => $expectedIntent,
                'actual_fields' => $actualFields,
                'expected_fields' => $expectedFields,
            ];
        } catch (\Exception $e) {
            Log::error('AI test failed', [
                'test_case_id' => $testCase->id,
                'message' => $e->getMessage(),
            ]);

            return $this->createFailedResult($testCase, $e->getMessage(), null);
        }
    }

    /**
     * Compare intents (flexible matching)
     */
    protected function compareIntents(?string $expected, ?string $actual): bool
    {
        if (!$expected || !$actual) {
            return false;
        }

        // Normalize strings
        $expected = strtolower(trim($expected));
        $actual = strtolower(trim($actual));

        // Exact match
        if ($expected === $actual) {
            return true;
        }

        // Partial match (actual contains expected or vice versa)
        return str_contains($actual, $expected) || str_contains($expected, $actual);
    }

    /**
     * Compare extracted fields
     */
    protected function compareFields(array $expected, array $actual): bool
    {
        if (empty($expected)) {
            return true; // No fields expected
        }

        // Check if all expected fields are present
        foreach ($expected as $field => $expectedValue) {
            if (!isset($actual[$field])) {
                return false;
            }

            // If expected value is specified, compare it
            if ($expectedValue !== null) {
                $actualValue = $actual[$field];

                // Flexible comparison
                if (is_numeric($expectedValue) && is_numeric($actualValue)) {
                    if ((float) $expectedValue !== (float) $actualValue) {
                        return false;
                    }
                } elseif (strtolower(trim($expectedValue)) !== strtolower(trim($actualValue))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create failed result
     */
    protected function createFailedResult(AiTestCase $testCase, string $error, ?string $aiResponse): array
    {
        $result = AiTestResult::create([
            'ai_test_case_id' => $testCase->id,
            'status' => AiTestResult::STATUS_FAIL,
            'actual_intent' => null,
            'actual_fields' => null,
            'ai_response' => $aiResponse,
            'error_details' => ['error' => $error],
            'tested_at' => now(),
        ]);

        return [
            'test_case_id' => $testCase->id,
            'test_case_name' => $testCase->name,
            'status' => AiTestResult::STATUS_FAIL,
            'error' => $error,
        ];
    }

    /**
     * Get test summary
     */
    public function getTestSummary(?string $businessType = null): array
    {
        $query = AiTestCase::query();

        if ($businessType) {
            $query->forBusinessType($businessType);
        }

        $testCases = $query->with('latestResult')->get();

        $summary = [
            'total_tests' => $testCases->count(),
            'passed' => 0,
            'failed' => 0,
            'not_run' => 0,
            'last_run' => null,
        ];

        foreach ($testCases as $testCase) {
            $latestResult = $testCase->latestResult;

            if (!$latestResult) {
                $summary['not_run']++;
            } elseif ($latestResult->status === AiTestResult::STATUS_PASS) {
                $summary['passed']++;
                
                if (!$summary['last_run'] || $latestResult->tested_at > $summary['last_run']) {
                    $summary['last_run'] = $latestResult->tested_at;
                }
            } else {
                $summary['failed']++;
                
                if (!$summary['last_run'] || $latestResult->tested_at > $summary['last_run']) {
                    $summary['last_run'] = $latestResult->tested_at;
                }
            }
        }

        return $summary;
    }
}
