<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreFileManagerService;
use App\Services\StoreDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileManagerController extends Controller
{
    public function __construct(
        private StoreFileManagerService $fileManager,
        private StoreDeploymentService $deploymentService
    ) {}

    /**
     * List files and directories
     */
    public function index(Request $request, Store $store): JsonResponse
    {
        $directory = $request->input('directory', '');
        
        try {
            $result = $this->fileManager->listFiles($store, $directory);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload files
     */
    public function upload(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240', // 10MB max per file
            'directory' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $directory = $request->input('directory', '');
        $uploaded = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $uploaded[] = $this->fileManager->uploadFile($store, $file, $directory);
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($uploaded) . ' file(s) uploaded',
            'data' => [
                'uploaded' => $uploaded,
                'errors' => $errors,
            ],
        ], count($errors) > 0 && count($uploaded) === 0 ? 400 : 200);
    }

    /**
     * Delete a file
     */
    public function deleteFile(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->fileManager->deleteFile($store, $request->input('path'));
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a directory
     */
    public function createDirectory(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->fileManager->createDirectory($store, $request->input('path'));
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Directory already exists',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Directory created successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create directory: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->fileManager->deleteDirectory($store, $request->input('path'));
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Directory not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Directory deleted successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete directory: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rename a file or directory
     */
    public function rename(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'old_path' => 'required|string',
            'new_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->fileManager->rename(
                $store, 
                $request->input('old_path'), 
                $request->input('new_name')
            );
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'File or directory not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Renamed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rename: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get file content (for editable files)
     */
    public function getContent(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $content = $this->fileManager->getFileContent($store, $request->input('path'));
            
            if ($content === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'path' => $request->input('path'),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to read file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save file content
     */
    public function saveContent(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->fileManager->saveFileContent(
                $store, 
                $request->input('path'), 
                $request->input('content')
            );

            return response()->json([
                'success' => true,
                'message' => 'File saved successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get storage usage
     */
    public function storageUsage(Store $store): JsonResponse
    {
        try {
            $usage = $this->fileManager->getStorageUsage($store);
            
            return response()->json([
                'success' => true,
                'data' => $usage,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage usage: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deploy files to storefront
     */
    public function deploy(Store $store): JsonResponse
    {
        try {
            // Sync files to deployment folder
            $this->fileManager->syncToDeployment($store);
            
            // Update deployment config
            $this->deploymentService->updateStoreConfig($store);

            return response()->json([
                'success' => true,
                'message' => 'Files deployed successfully',
                'data' => [
                    'deployment_status' => $this->deploymentService->getDeploymentStatus($store),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deploy files: ' . $e->getMessage(),
            ], 500);
        }
    }
}
