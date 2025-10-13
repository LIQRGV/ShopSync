<?php

namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

use TheDiamondBox\ShopSync\Models\ShopInfo;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $fileExt = $file->getClientOriginalExtension();

            if ($fileExt != 'webp') {
                $updateData[$originalField] = $this->getOriginalImagePath($imagePath);
            } else {
                $updateData[$originalField] = $this->convertWebpToJpeg($imagePath);
            }
            if ($fileExt == 'svg') {
                $this->convertSvgToPng($imagePath);
                if (isset($updateData[$originalField])) {
                    $this->convertSvgToPng($updateData[$originalField]);
                }
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
            if (in_array($field, ['logo', 'favicon'])) {
                $sourcePath = $shopDir . '/' . $name;
                $destPath = $originalDir . '/' . $name;

                if (file_exists($sourcePath)) {
                    copy($sourcePath, $destPath);
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

    protected function convertWebpToJpeg(string $imagePath): string
    {
        $jpegPath = str_replace('.webp', '.jpg', $imagePath);
        $jpegPath = str_replace('uploads/shop_images', 'uploads/original_shop_images', $jpegPath);

        $sourcePath = public_path($imagePath);
        $destPath = public_path($jpegPath);

        try {
            if (function_exists('imagecreatefromwebp')) {
                $image = imagecreatefromwebp($sourcePath);
                imagejpeg($image, $destPath, 90);
                imagedestroy($image);

                Log::info('Converted WebP to JPEG', [
                    'source' => $imagePath,
                    'destination' => $jpegPath,
                ]);
            } else {
                copy($sourcePath, $destPath);
                Log::warning('WebP conversion not available, copying as-is');
            }
        } catch (\Exception $e) {
            Log::error('Failed to convert WebP to JPEG', [
                'source' => $imagePath,
                'error' => $e->getMessage(),
            ]);
            return $imagePath;
        }

        return $jpegPath;
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
