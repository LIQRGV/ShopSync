<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use TheDiamondBox\ShopSync\Helpers\JsonApiErrorResponse;
use TheDiamondBox\ShopSync\Http\Requests\GetProductRequest;
use TheDiamondBox\ShopSync\Http\Requests\SearchProductRequest;
use TheDiamondBox\ShopSync\Http\Requests\StoreProductRequest;
use TheDiamondBox\ShopSync\Http\Requests\UpdateProductRequest;
use TheDiamondBox\ShopSync\Http\Requests\UploadProductImageRequest;
use TheDiamondBox\ShopSync\Services\ProductService;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of products with JSON API support
     *
     * @param GetProductRequest $request
     * @return JsonResponse
     */
    public function index(GetProductRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $filters = $request->getFilters();
            $pagination = $request->getPagination();

            $result = $this->productService->getProductsWithPagination($filters, $includes, $pagination);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to fetch products', [
                'error' => $e->getMessage(),
                'filters' => $request->getFilters(),
                'includes' => $request->getIncludes(),
                'trace' => $e->getTrace()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch products'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created Product with JSON API response
     *
     * @param StoreProductRequest $request
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $data = $request->getValidatedDataWithDefaults();
            $result = $this->productService->createProduct($data, $includes);

            if (!$result) {
                $error = JsonApiErrorResponse::internalError('Failed to create product');
                return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json($result, Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to create product', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to create product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified product with JSON API support
     *
     * @param GetProductRequest $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function show(GetProductRequest $request, $id): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $withTrashed = $request->includesTrashed();
            $result = $this->productService->findProduct($id, $includes, $withTrashed);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to fetch product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified Product with JSON API response
     *
     * @param UpdateProductRequest $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $data = $request->getValidatedDataWithFormatting();
            $result = $this->productService->updateProduct($id, $data, $includes);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to update product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $request->all(),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to update product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified Product (soft delete)
     *
     * @param mixed $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $success = $this->productService->deleteProduct($id);

            if (!$success) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error('Failed to delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            // Check if it's a not found error
            if (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'No query results') !== false) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to delete product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted Product with JSON API response
     *
     * @param GetProductRequest $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function restore(GetProductRequest $request, $id): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $result = $this->productService->restoreProduct($id, $includes);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to restore product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to restore product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Permanently delete the specified Product
     *
     * @param mixed $id
     * @return JsonResponse
     */
    public function forceDelete($id): JsonResponse
    {
        try {
            $success = $this->productService->forceDeleteProduct($id);

            if (!$success) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error('Failed to force delete product', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            // Check if it's a not found error
            if (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'No query results') !== false) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to permanently delete product'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search Products with JSON API support
     *
     * @param SearchProductRequest $request
     * @return JsonResponse
     */
    public function search(SearchProductRequest $request): JsonResponse
    {
        try {
            $includes = $request->getIncludes();

            // Validate includes
            $includeErrors = $this->productService->validateIncludes($includes);
            if (!empty($includeErrors)) {
                return response()->json(JsonApiErrorResponse::multiple($includeErrors), 400);
            }

            $searchTerm = $request->getSearchTerm();
            $filters = $request->getSearchFilters();
            $pagination = $request->getPaginationSettings();

            $result = $this->productService->searchProductsWithPagination(
                $searchTerm,
                $filters,
                $includes,
                $pagination
            );

            return response()->json($result);

        } catch (ValidationException $e) {
            $error = JsonApiErrorResponse::fromLaravelValidation($e->validator);
            return response()->json($error, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to search products', [
                'error' => $e->getMessage(),
                'query' => $request->get('q'),
                'filters' => $request->except('q'),
                'includes' => $includes ?? []
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Search failed'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export products to CSV
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|JsonResponse
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

            $csv = $this->productService->exportProducts($filters);
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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:2048',
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

            $result = $this->productService->importProducts($csvContent);

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
     * Get Product statistics and status with JSON API support
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $result = $this->productService->getProductStatus();
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to get product status', [
                'error' => $e->getMessage()
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to get product status'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload product image
     *
     * @param UploadProductImageRequest $request
     * @param mixed $id
     * @return JsonResponse
     */
    public function uploadImage(UploadProductImageRequest $request, $id): JsonResponse
    {
        try {
            // Get uploaded file
            $file = $request->file('image');

            // Upload image via service
            $result = $this->productService->uploadProductImage($id, $file);

            if (!$result) {
                $error = JsonApiErrorResponse::notFound('product', $id);
                return response()->json($error, Response::HTTP_NOT_FOUND);
            }

            return response()->json($result);

        } catch (ModelNotFoundException $e) {
            $error = JsonApiErrorResponse::notFound('product', $id);
            return response()->json($error, Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to upload product image', [
                'error' => $e->getMessage(),
                'id' => $id,
                'file' => $request->hasFile('image') ? $request->file('image')->getClientOriginalName() : 'no file'
            ]);

            $error = JsonApiErrorResponse::internalError(
                app()->environment('local') ? $e->getMessage() : 'Failed to upload product image'
            );
            return response()->json($error, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate uploaded file for security issues
     *
     * @param mixed $file
     * @return bool
     */
    protected function validateUploadedFile($file)
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
     *
     * @param string $content
     * @return bool
     */
    protected function validateCsvContent($content)
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
     *
     * @param string $content
     * @return bool
     */
    protected function containsMaliciousSignatures($content)
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