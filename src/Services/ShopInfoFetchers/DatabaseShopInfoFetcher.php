<?php

namespace TheDiamondBox\ShopSync\Services\ShopInfoFetchers;

use TheDiamondBox\ShopSync\Models\ShopInfo;
use TheDiamondBox\ShopSync\Services\Contracts\ShopInfoFetcherInterface;

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
}
