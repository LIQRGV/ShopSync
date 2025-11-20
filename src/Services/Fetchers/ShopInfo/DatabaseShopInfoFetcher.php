<?php

namespace TheDiamondBox\ShopSync\Services\Fetchers\ShopInfo;

use TheDiamondBox\ShopSync\Models\ShopInfo;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Image;

class DatabaseShopInfoFetcher implements ShopInfoFetcherInterface
{
    protected function getModelClass()
    {
        return class_exists('App\ShopInfo')
            ? 'App\ShopInfo'
            : 'TheDiamondBox\ShopSync\Models\ShopInfo';
    }

    protected function getOpenHoursModelClass()
    {
        return class_exists('App\Models\OpenHours')
            ? 'App\Models\OpenHours'
            : (class_exists('App\OpenHours')
                ? 'App\OpenHours'
                : 'TheDiamondBox\ShopSync\Models\OpenHours');
    }

    public function get()
    {
        $modelClass = $this->getModelClass();
        $openHoursClass = $this->getOpenHoursModelClass();

        $shopInfo = $modelClass::first();

        if ($shopInfo) {
            $openHours = $openHoursClass::where(function ($query) {
                    $query->whereNull('shop_id')
                          ->orWhere('shop_id', 0);
                })
                ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
                ->get();

            $shopInfo->setRelation('openHours', $openHours);
        }

        return $shopInfo;
    }

    public function update(array $data)
    {
        $modelClass = $this->getModelClass();

        $openHoursData = $data['open_hours'] ?? null;
        unset($data['open_hours']);

        $shopInfo = $modelClass::query()->first();

        if ($shopInfo) {
            $shopInfo->update($data);
        } else {
            $modelClass::create($data);
        }

        if ($openHoursData && is_array($openHoursData)) {
            $this->updateOpenHours($openHoursData);
        }

        return $this->get();
    }

    protected function updateOpenHours(array $openHoursData)
    {
        $openHoursClass = $this->getOpenHoursModelClass();

        foreach ($openHoursData as $dayData) {
            if (!isset($dayData['day'])) {
                continue;
            }

            $day = strtolower($dayData['day']);

            $openHour = $openHoursClass::where(function ($query) {
                    $query->whereNull('shop_id')
                          ->orWhere('shop_id', 0);
                })
                ->where('day', $day)
                ->first();

            $updateData = [
                'day' => $day,
                'is_open' => $dayData['is_open'] ?? false,
                'open_at' => $dayData['open_at'] ?? null,
                'close_at' => $dayData['close_at'] ?? null,
            ];

            if ($openHour) {
                $openHour->update($updateData);
            } else {
                $openHoursClass::create($updateData);
            }
        }
    }

    public function updatePartial(array $data)
    {
        $openHoursData = $data['open_hours'] ?? null;
        unset($data['open_hours']);

        $filtered = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $filtered[$key] = $value;
            }
        }

        if ($openHoursData !== null) {
            $filtered['open_hours'] = $openHoursData;
        }

        if (empty($filtered)) {
            return $this->get();
        }

        return $this->update($filtered);
    }

    public function uploadImage(string $field, $file)
    {
        $modelClass = $this->getModelClass();
        $shopInfo = $modelClass::first();

        if (!$shopInfo) {
            throw new \Exception('Shop info not found');
        }

        $imagePath = $this->storeShopImage($file, $field);

        if (!$imagePath) {
            throw new \Exception('Failed to store image');
        }

        $updateData = [$field => $imagePath];

        if (in_array($field, ['logo', 'favicon'])) {
            $originalField = 'original_' . $field;
            $resultExt = pathinfo($imagePath, PATHINFO_EXTENSION);
            $inputExt = $file->getClientOriginalExtension();

            if ($resultExt == 'webp') {
                if ($inputExt == 'webp') {
                    $updateData[$originalField] = $this->convertWebpToPng($imagePath);
                } else {
                    $originalImagePath = str_replace('.webp', '', $imagePath);
                    $updateData[$originalField] = $this->getOriginalImagePath($originalImagePath);
                }
            } else if ($resultExt == 'svg') {
                $this->convertSvgToPng($imagePath);
                $updateData[$originalField] = $this->getOriginalImagePath($imagePath);
                if (isset($updateData[$originalField])) {
                    $this->convertSvgToPng($updateData[$originalField]);
                }
            } else {
                $updateData[$originalField] = $this->getOriginalImagePath($imagePath);
            }
        }

        $shopInfo->update($updateData);

        Log::info('Shop info image uploaded successfully', [
            'field' => $field,
            'path' => $imagePath,
            'update_data' => $updateData,
        ]);

        return $this->get();
    }

    protected function storeShopImage($file, string $field): ?string
    {
        try {
            $shopImagePath = 'uploads/shop_images';
            $shopDir = public_path($shopImagePath);
            $originalDir = public_path('uploads/original_shop_images');

            if (!file_exists($shopDir)) {
                mkdir($shopDir, 0755, true);
            }

            if (!file_exists($originalDir)) {
                mkdir($originalDir, 0755, true);
            }

            $timestamp = time();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $name = $timestamp . '_' . Str::slug($originalName) . '.' . $extension;

            $file->move($shopDir, $name);
            $uploadedPath = $shopDir . '/' . $name;

            if (in_array($field, ['logo', 'favicon']) && !in_array($extension, ['webp', 'svg'])) {
                $originalCopy = $originalDir . '/' . $name;
                if (file_exists($uploadedPath)) {
                    copy($uploadedPath, $originalCopy);
                }
            }

            if (!in_array($extension, ['webp', 'svg'])) {
                $webpName = $name . '.webp';
                $webpPath = $shopDir . '/' . $webpName;

                try {
                    $image = Image::make($uploadedPath);
                    $image->encode('webp');
                    $image->save($webpPath);

                    if (file_exists($uploadedPath)) {
                        unlink($uploadedPath);
                    }

                    $name = $webpName;
                    $uploadedPath = $webpPath;

                    Log::info('Converted image to WebP', [
                        'original' => $extension,
                        'converted' => $webpName,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('WebP conversion failed, using original format', [
                        'error' => $e->getMessage(),
                    ]);
                }
            } else if ($extension == 'svg' && in_array($field, ['logo', 'favicon'])) {
                $destPath = $originalDir . '/' . $name;
                if (file_exists($uploadedPath)) {
                    copy($uploadedPath, $destPath);
                }
            } else if ($extension == 'webp' && in_array($field, ['logo', 'favicon'])) {
                $destPath = $originalDir . '/' . $name;
                if (file_exists($uploadedPath)) {
                    copy($uploadedPath, $destPath);
                }
            }

            return $shopImagePath . '/' . $name;
        } catch (\Exception $e) {
            Log::error('Failed to store shop image', [
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function getOriginalImagePath(string $imagePath): string
    {
        return str_replace('uploads/shop_images', 'uploads/original_shop_images', $imagePath);
    }

    protected function convertWebpToPng(string $imagePath): string
    {
        $pngPath = str_replace('.webp', '.png', $imagePath);
        $pngPath = str_replace('uploads/shop_images', 'uploads/original_shop_images', $pngPath);

        $sourcePath = public_path($imagePath);
        $destPath = public_path($pngPath);

        try {
            $image = Image::make($sourcePath);
            $image->encode('png');
            $image->save($destPath);

            Log::info('Converted WebP to PNG for original', [
                'source' => $imagePath,
                'destination' => $pngPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to convert WebP to PNG', [
                'source' => $imagePath,
                'error' => $e->getMessage(),
            ]);
            return $imagePath;
        }

        return $pngPath;
    }

    protected function convertSvgToPng(string $imagePath): void
    {
        if (!str_ends_with($imagePath, '.svg')) {
            return;
        }

        $pngPath = str_replace('.svg', '.png', $imagePath);
        $inputSvg = public_path($imagePath);
        $outputPng = public_path($pngPath);

        try {
            if (app()->environment('local')) {
                $cmd = "magick " . escapeshellarg($inputSvg) . " " . escapeshellarg($outputPng);
            } else {
                $cmd = escapeshellcmd("rsvg-convert -o " . escapeshellarg($outputPng) . " " . escapeshellarg($inputSvg));
            }

            exec($cmd, $output, $returnVar);

            if ($returnVar === 0 && file_exists($outputPng)) {
                Log::info('Converted SVG to PNG', [
                    'source' => $imagePath,
                    'destination' => $pngPath,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('SVG to PNG conversion failed (tools may not be installed)', [
                'source' => $imagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
