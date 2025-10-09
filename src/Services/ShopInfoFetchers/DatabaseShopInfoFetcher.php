<?php

namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

use TheDiamondBox\ShopSync\Models\OpenHours;
use TheDiamondBox\ShopSync\Models\ShopInfo;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;

/**
 * DatabaseShopInfoFetcher - WL Mode
 *
 * Direct database access for shop info
 * Uses App\ShopInfo for compatibility with existing WL application
 */
class DatabaseShopInfoFetcher implements ShopInfoFetcherInterface
{
    /**
     * Get the ShopInfo model class
     * Uses App\ShopInfo if available (WL mode), otherwise falls back to package model
     *
     * @return string
     */
    protected function getModelClass()
    {
        return class_exists('App\ShopInfo')
            ? 'App\ShopInfo'
            : 'TheDiamondBox\ShopSync\Models\ShopInfo';
    }

    /**
     * Get the OpenHours model class
     *
     * @return string
     */
    protected function getOpenHoursModelClass()
    {
        return class_exists('App\Models\OpenHours')
            ? 'App\Models\OpenHours'
            : (class_exists('App\OpenHours')
                ? 'App\OpenHours'
                : 'TheDiamondBox\ShopSync\Models\OpenHours');
    }

    /**
     * Get shop info with open hours
     *
     * @return \TheDiamondBox\ShopSync\Models\ShopInfo|null
     */
    public function get()
    {
        $shopInfo = ShopInfo::first();

        if ($shopInfo) {
            // Load open hours (shop_id is NULL for singleton shop_info)
            $openHours = OpenHours::where(function ($query) {
                    $query->whereNull('shop_id')
                          ->orWhere('shop_id', 0);
                })
                ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
                ->get();

            // Manually set the relationship
            $shopInfo->setRelation('openHours', $openHours);
        }

        return $shopInfo;
    }

    /**
     * Update shop info (full replace)
     *
     * @param array $data
     * @return \App\ShopInfo|\TheDiamondBox\ShopSync\Models\ShopInfo
     */
    public function update(array $data)
    {
        // Extract open_hours from data
        $openHoursData = $data['open_hours'] ?? null;
        unset($data['open_hours']);

        $shopInfo = ShopInfo::query()->first();

        if ($shopInfo) {
            $shopInfo->update($data);
        } else {
            ShopInfo::create($data);
        }

        // Update open hours if provided
        if ($openHoursData && is_array($openHoursData)) {
            $this->updateOpenHours($openHoursData);
        }

        // Reload with relationships
        return $this->get();
    }

    /**
     * Update open hours data
     *
     * @param array $openHoursData
     * @return void
     */
    protected function updateOpenHours(array $openHoursData)
    {
        foreach ($openHoursData as $dayData) {
            if (!isset($dayData['day'])) {
                continue;
            }

            $day = strtolower($dayData['day']);

            // Properly group WHERE conditions to avoid matching wrong records
            $openHour = OpenHours::where(function ($query) {
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
                OpenHours::create($updateData);
            }
        }
    }

    /**
     * Update shop info (partial - only non-empty values)
     * This prevents empty data from overriding existing values
     *
     * @param array $data
     * @return ShopInfo|null
     */
    public function updatePartial(array $data)
    {
        // Extract open_hours before filtering
        $openHoursData = $data['open_hours'] ?? null;
        unset($data['open_hours']);

        // Filter out null and empty string values
        $filtered = array_filter($data, function ($value) {
            return !is_null($value) && $value !== '';
        });

        // Re-add open_hours if provided
        if ($openHoursData !== null) {
            $filtered['open_hours'] = $openHoursData;
        }

        if (empty($filtered)) {
            return $this->get();
        }

        return $this->update($filtered);
    }
}
