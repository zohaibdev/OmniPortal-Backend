<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreFileManagerService
{
    protected string $storageBasePath;
    protected string $publicBasePath;

    /**
     * Allowed file extensions by category
     */
    protected array $allowedExtensions = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'],
        'documents' => ['pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx'],
        'code' => ['css', 'js', 'json', 'html'],
        'fonts' => ['woff', 'woff2', 'ttf', 'otf', 'eot'],
    ];

    public function __construct()
    {
        $this->storageBasePath = storage_path('app/public/stores');
        $this->publicBasePath = base_path('../storefront/public/stores');
    }

    /**
     * Get store file manager path
     */
    protected function getStorePath(Store $store, string $base = 'storage'): string
    {
        $basePath = $base === 'storage' ? $this->storageBasePath : $this->publicBasePath;
        return $basePath . '/' . $store->slug;
    }

    /**
     * List files in a directory
     */
    public function listFiles(Store $store, string $directory = ''): array
    {
        // Ensure base store directory exists
        $basePath = $this->getStorePath($store);
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }
        
        $path = $directory ? $basePath . '/' . ltrim($directory, '/') : $basePath;
        
        if (!File::exists($path)) {
            return ['current_path' => $directory ?: '/', 'files' => [], 'directories' => []];
        }

        $files = [];
        $directories = [];

        foreach (File::files($path) as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $directory . '/' . $file->getFilename(),
                'size' => $file->getSize(),
                'type' => $file->getExtension(),
                'mime_type' => mime_content_type($file->getPathname()),
                'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                'url' => $this->getFileUrl($store, $directory . '/' . $file->getFilename()),
            ];
        }

        foreach (File::directories($path) as $dir) {
            $dirName = basename($dir);
            $directories[] = [
                'name' => $dirName,
                'path' => $directory . '/' . $dirName,
                'type' => 'directory',
            ];
        }

        return [
            'current_path' => $directory ?: '/',
            'files' => $files,
            'directories' => $directories,
        ];
    }

    /**
     * Upload a file
     */
    public function uploadFile(Store $store, UploadedFile $file, string $directory = ''): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Validate file type
        if (!$this->isAllowedExtension($extension)) {
            throw new \InvalidArgumentException('File type not allowed: ' . $extension);
        }

        // Ensure base store directory exists
        $basePath = $this->getStorePath($store);
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        $path = $directory ? $basePath . '/' . ltrim($directory, '/') : $basePath;
        
        // Ensure target directory exists
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Generate safe filename
        $filename = $this->generateSafeFilename($file->getClientOriginalName());
        
        // Move file
        $file->move($path, $filename);
        
        $fullPath = $path . '/' . $filename;
        $relativePath = $directory ? $directory . '/' . $filename : $filename;

        Log::info('File uploaded to store', [
            'store_id' => $store->id,
            'path' => $relativePath,
        ]);

        return [
            'name' => $filename,
            'path' => $relativePath,
            'size' => filesize($fullPath),
            'type' => $extension,
            'mime_type' => mime_content_type($fullPath),
            'url' => $this->getFileUrl($store, $relativePath),
        ];
    }

    /**
     * Delete a file
     */
    public function deleteFile(Store $store, string $filePath): bool
    {
        $path = $this->getStorePath($store) . '/' . ltrim($filePath, '/');
        
        if (!File::exists($path) || File::isDirectory($path)) {
            return false;
        }

        File::delete($path);

        Log::info('File deleted from store', [
            'store_id' => $store->id,
            'path' => $filePath,
        ]);

        return true;
    }

    /**
     * Create a directory
     */
    public function createDirectory(Store $store, string $directory): bool
    {
        // Ensure base store directory exists
        $basePath = $this->getStorePath($store);
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }
        
        $path = $basePath . '/' . ltrim($directory, '/');
        
        if (File::exists($path)) {
            return false;
        }

        File::makeDirectory($path, 0755, true);

        return true;
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(Store $store, string $directory): bool
    {
        $path = $this->getStorePath($store) . '/' . ltrim($directory, '/');
        
        if (!File::exists($path) || !File::isDirectory($path)) {
            return false;
        }

        // Prevent deleting root directories
        $protectedDirs = ['images', 'css', 'icons', 'branding'];
        $dirName = basename($directory);
        
        if (in_array($dirName, $protectedDirs) && dirname($directory) === '/') {
            throw new \InvalidArgumentException('Cannot delete protected directory');
        }

        File::deleteDirectory($path);

        return true;
    }

    /**
     * Rename a file or directory
     */
    public function rename(Store $store, string $oldPath, string $newName): bool
    {
        $basePath = $this->getStorePath($store);
        $oldFullPath = $basePath . '/' . ltrim($oldPath, '/');
        $newFullPath = dirname($oldFullPath) . '/' . $newName;

        if (!File::exists($oldFullPath)) {
            return false;
        }

        File::move($oldFullPath, $newFullPath);

        return true;
    }

    /**
     * Copy a file
     */
    public function copyFile(Store $store, string $sourcePath, string $destPath): bool
    {
        $basePath = $this->getStorePath($store);
        $sourceFullPath = $basePath . '/' . ltrim($sourcePath, '/');
        $destFullPath = $basePath . '/' . ltrim($destPath, '/');

        if (!File::exists($sourceFullPath) || File::isDirectory($sourceFullPath)) {
            return false;
        }

        // Ensure destination directory exists
        $destDir = dirname($destFullPath);
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::copy($sourceFullPath, $destFullPath);

        return true;
    }

    /**
     * Move a file
     */
    public function moveFile(Store $store, string $sourcePath, string $destPath): bool
    {
        $basePath = $this->getStorePath($store);
        $sourceFullPath = $basePath . '/' . ltrim($sourcePath, '/');
        $destFullPath = $basePath . '/' . ltrim($destPath, '/');

        if (!File::exists($sourceFullPath)) {
            return false;
        }

        // Ensure destination directory exists
        $destDir = dirname($destFullPath);
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::move($sourceFullPath, $destFullPath);

        return true;
    }

    /**
     * Get file content (for editable files like CSS, JS, JSON)
     */
    public function getFileContent(Store $store, string $filePath): ?string
    {
        $path = $this->getStorePath($store) . '/' . ltrim($filePath, '/');
        
        if (!File::exists($path) || File::isDirectory($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $editableExtensions = ['css', 'js', 'json', 'html', 'txt'];
        
        if (!in_array($extension, $editableExtensions)) {
            throw new \InvalidArgumentException('File type is not editable');
        }

        return File::get($path);
    }

    /**
     * Save file content
     */
    public function saveFileContent(Store $store, string $filePath, string $content): bool
    {
        $path = $this->getStorePath($store) . '/' . ltrim($filePath, '/');
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $editableExtensions = ['css', 'js', 'json', 'html', 'txt'];
        
        if (!in_array($extension, $editableExtensions)) {
            throw new \InvalidArgumentException('File type is not editable');
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, $content);

        Log::info('File content saved', [
            'store_id' => $store->id,
            'path' => $filePath,
        ]);

        return true;
    }

    /**
     * Get storage usage for a store
     */
    public function getStorageUsage(Store $store): array
    {
        $path = $this->getStorePath($store);
        
        if (!File::exists($path)) {
            return [
                'total_size' => 0,
                'file_count' => 0,
                'breakdown' => [],
            ];
        }

        $totalSize = 0;
        $fileCount = 0;
        $breakdown = [
            'images' => 0,
            'documents' => 0,
            'code' => 0,
            'fonts' => 0,
            'other' => 0,
        ];

        $this->calculateDirectorySize($path, $totalSize, $fileCount, $breakdown);

        return [
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'file_count' => $fileCount,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Sync files to deployment folder
     */
    public function syncToDeployment(Store $store): bool
    {
        $storagePath = $this->getStorePath($store, 'storage');
        $deployPath = config('deployment.stores_path', base_path('../stores')) . '/' . $store->slug;

        if (!File::exists($storagePath)) {
            return false;
        }

        if (!File::exists($deployPath)) {
            File::makeDirectory($deployPath, 0755, true);
        }

        // Copy relevant files (images, CSS, branding)
        $syncDirs = ['images', 'css', 'icons', 'branding'];
        
        foreach ($syncDirs as $dir) {
            $sourceDir = $storagePath . '/' . $dir;
            $destDir = $deployPath . '/' . $dir;
            
            if (File::exists($sourceDir)) {
                if (File::exists($destDir)) {
                    File::deleteDirectory($destDir);
                }
                File::copyDirectory($sourceDir, $destDir);
            }
        }

        Log::info('Store files synced to deployment', [
            'store_id' => $store->id,
        ]);

        return true;
    }

    /**
     * Get file URL for public access
     */
    protected function getFileUrl(Store $store, string $filePath): string
    {
        return '/storage/stores/' . $store->slug . '/' . ltrim($filePath, '/');
    }

    /**
     * Generate safe filename
     */
    protected function generateSafeFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Sanitize filename
        $name = Str::slug($name);
        
        // Add timestamp to prevent collisions
        return $name . '-' . time() . '.' . $extension;
    }

    /**
     * Check if extension is allowed
     */
    protected function isAllowedExtension(string $extension): bool
    {
        foreach ($this->allowedExtensions as $extensions) {
            if (in_array($extension, $extensions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate directory size recursively
     */
    protected function calculateDirectorySize(string $path, int &$totalSize, int &$fileCount, array &$breakdown): void
    {
        foreach (File::allFiles($path) as $file) {
            $size = $file->getSize();
            $totalSize += $size;
            $fileCount++;

            $extension = strtolower($file->getExtension());
            $category = $this->getFileCategory($extension);
            $breakdown[$category] += $size;
        }
    }

    /**
     * Get file category from extension
     */
    protected function getFileCategory(string $extension): string
    {
        foreach ($this->allowedExtensions as $category => $extensions) {
            if (in_array($extension, $extensions)) {
                return $category;
            }
        }
        return 'other';
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
