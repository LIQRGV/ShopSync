<?php

namespace Liqrgv\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Liqrgv\ShopSync\Services\ProductService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of products
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category',
                'is_active',
                'min_price',
                'max_price',
                'min_stock',
                'with_trashed',
                'only_trashed',
                'sort_by',
                'sort_order',
            ]);

            if ($request->has('page')) {
                $perPage = $request->get('per_page', config('products-package.per_page', 15));
                $result = $this->productService->paginate($perPage, $filters);
            } else {
                $result = $this->productService->getAll($filters);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to fetch products', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? []
            ]);

            return response()->json([
                'message' => 'Failed to fetch products',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'sku' => 'nullable|string|max:255|unique:products,sku',
                'category' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'is_active' => 'boolean',
            ]);

            $product = $this->productService->create($validated);

            return response()->json($product, Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to create product', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified product
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $withTrashed = $request->boolean('with_trashed');

            $product = $this->productService->find($id, $withTrashed);

            return response()->json($product);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'sometimes|required|integer|min:0',
                'sku' => 'nullable|string|max:255|unique:products,sku,' . $id,
                'category' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'is_active' => 'boolean',
            ]);

            $product = $this->productService->update($id, $validated);

            return response()->json($product);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to update product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to update product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified product (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->productService->delete($id);

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'message' => 'Failed to delete product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id): JsonResponse
    {
        try {
            $product = $this->productService->restore($id);

            return response()->json($product);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to restore product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'message' => 'Failed to restore product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Permanently delete the specified product
     */
    public function forceDelete($id): JsonResponse
    {
        try {
            $this->productService->forceDelete($id);

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to force delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'message' => 'Failed to permanently delete product',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search products
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'required|string|min:1|max:255',
                'category' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'with_trashed' => 'nullable|boolean',
                'sort_by' => 'nullable|string|in:id,name,price,stock,category,created_at,updated_at',
                'sort_order' => 'nullable|string|in:asc,desc',
            ]);

            $query = $validated['q'];
            unset($validated['q']);

            $products = $this->productService->search($query, $validated);

            return response()->json($products);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to search products', [
                'error' => $e->getMessage(),
                'query' => $request->get('q'),
                'filters' => $request->except('q')
            ]);

            return response()->json([
                'message' => 'Search failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->validate([
                'category' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'with_trashed' => 'nullable|boolean',
                'only_trashed' => 'nullable|boolean',
            ]);

            $csv = $this->productService->exportToCsv($filters);

            $filename = 'products-' . date('Y-m-d-H-i-s') . '.csv';

            return response($csv, Response::HTTP_OK)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to export products', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'message' => 'Export failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:2048', // Reduced to 2MB max for security
            ]);

            $file = $validated['file'];

            // Enhanced security validation
            if (!$this->validateUploadedFile($file)) {
                return response()->json([
                    'message' => 'Invalid file format or content detected'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $csvContent = file_get_contents($file->getRealPath());

            if (empty($csvContent)) {
                return response()->json([
                    'message' => 'The uploaded file is empty'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Additional content validation
            if (!$this->validateCsvContent($csvContent)) {
                return response()->json([
                    'message' => 'Invalid CSV content or potentially malicious file detected'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $result = $this->productService->importFromCsv($csvContent);

            return response()->json([
                'message' => 'Import completed',
                'imported' => $result['imported'],
                'errors' => $result['errors'],
                'total_errors' => count($result['errors'])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to import products', [
                'error' => $e->getMessage(),
                'file_info' => $request->hasFile('file') ? [
                    'name' => $request->file('file')->getClientOriginalName(),
                    'size' => $request->file('file')->getSize(),
                    'mime' => $request->file('file')->getMimeType()
                ] : 'No file'
            ]);

            return response()->json([
                'message' => 'Import failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get package status and configuration info
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->productService->getStatus();

            return response()->json([
                'status' => 'healthy',
                'package_info' => $status,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get package status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get package status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate uploaded file for security issues
     */
    protected function validateUploadedFile($file): bool
    {
        // Check if file exists and is readable
        if (!$file || !$file->isValid()) {
            return false;
        }

        // Verify file extension
        $allowedExtensions = ['csv', 'txt'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return false;
        }

        // Content-based MIME type detection for additional security
        $detectedMimeType = mime_content_type($file->getRealPath());
        $allowedMimeTypes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'text/comma-separated-values',
            'application/octet-stream', // Some systems might return this for CSV
        ];

        if (!in_array($detectedMimeType, $allowedMimeTypes)) {
            Log::warning('File upload blocked: Invalid MIME type detected', [
                'uploaded_mime' => $file->getMimeType(),
                'detected_mime' => $detectedMimeType,
                'filename' => $file->getClientOriginalName()
            ]);
            return false;
        }

        // Check file size (additional check beyond validation rule)
        $maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if ($file->getSize() > $maxSize) {
            return false;
        }

        // Check for common malicious file signatures
        $fileContent = file_get_contents($file->getRealPath());
        if ($this->containsMaliciousSignatures($fileContent)) {
            Log::warning('File upload blocked: Malicious signatures detected', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Validate CSV content for potential security issues
     */
    protected function validateCsvContent(string $content): bool
    {
        // Check for extremely long lines that might indicate malicious content
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strlen($line) > 10000) { // 10KB per line max
                return false;
            }
        }

        // Check for excessive number of lines
        if (count($lines) > 50000) { // 50K lines max
            return false;
        }

        // Check for suspicious patterns that might indicate code injection
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/=\s*CALL\s+/i', // Excel formula injection
            '/=\s*CMD\s*\|/i',
            '/=\s*SYSTEM\s*\(/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Log::warning('CSV content blocked: Suspicious pattern detected', [
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        // Validate CSV structure - should be parseable
        try {
            $lines = array_filter(explode("\n", $content));
            if (empty($lines)) {
                return false;
            }

            // Check if first line looks like a header
            $header = str_getcsv($lines[0]);
            if (empty($header) || count($header) < 2) {
                return false;
            }

            // Validate that at least some lines can be parsed as CSV
            $validLines = 0;
            $samplesToCheck = min(10, count($lines) - 1); // Check up to 10 lines after header

            for ($i = 1; $i <= $samplesToCheck; $i++) {
                if (isset($lines[$i])) {
                    $parsed = str_getcsv($lines[$i]);
                    if (is_array($parsed) && !empty($parsed)) {
                        $validLines++;
                    }
                }
            }

            // At least 50% of sampled lines should be valid CSV
            return $validLines >= ($samplesToCheck * 0.5);

        } catch (\Exception $e) {
            Log::warning('CSV validation failed during parsing', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for malicious file signatures
     */
    protected function containsMaliciousSignatures(string $content): bool
    {
        $maliciousSignatures = [
            "\x00", // Null bytes
            "\xFF\xFE", // UTF-16 LE BOM (unusual for CSV)
            "\xFE\xFF", // UTF-16 BE BOM (unusual for CSV)
            "\xFF\xFE\x00\x00", // UTF-32 LE BOM
            "\x00\x00\xFE\xFF", // UTF-32 BE BOM
            "\x7FELF", // ELF executable
            "\x4D\x5A", // Windows PE executable
            "\x50\x4B", // ZIP file signature (potential zip bomb)
            "%PDF", // PDF file
            "GIF8", // GIF image
            "\xFF\xD8\xFF", // JPEG image
            "\x89PNG", // PNG image
            "<?xml", // XML content
            "<!DOCTYPE", // HTML/XML DOCTYPE
        ];

        $contentStart = substr($content, 0, 256); // Check first 256 bytes
        foreach ($maliciousSignatures as $signature) {
            if (strpos($contentStart, $signature) !== false) {
                return true;
            }
        }

        return false;
    }
}