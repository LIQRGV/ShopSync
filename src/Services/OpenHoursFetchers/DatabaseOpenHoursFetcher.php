<?php

namespace TheDiamondBox\ShopSync\Services\OpenHoursFetchers;

use TheDiamondBox\ShopSync\Services\Contracts\OpenHoursFetcherInterface;
use TheDiamondBox\ShopSync\Models\OpenHours;
use Illuminate\Support\Collection;

/**
 * DatabaseOpenHoursFetcher - WL Mode
 *
 * Direct database access for open hours
 */
class DatabaseOpenHoursFetcher implements OpenHoursFetcherInterface
{
    /**
     * Get the OpenHours model class
     *
     * @return string
     */
    protected function getModelClass()
    {
        return class_exists('App\Models\OpenHours')
            ? 'App\Models\OpenHours'
            : (class_exists('App\OpenHours')
                ? 'App\OpenHours'
                : 'TheDiamondBox\ShopSync\Models\OpenHours');
    }

    /**
     * Get all open hours
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAll()
    {
        $modelClass = $this->getModelClass();

        $openHours = $modelClass::whereNull('shop_id')
            ->orWhere('shop_id', 0)
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        // Initialize if empty
        if ($openHours->isEmpty()) {
            return $this->initializeDefaults();
        }

        return $openHours;
    }

    /**
     * Get open hours for a specific day
     *
     * @param string $day
     * @return mixed
     */
    public function getByDay(string $day)
    {
        $modelClass = $this->getModelClass();

        $openHour = $modelClass::whereNull('shop_id')
            ->orWhere('shop_id', 0)
            ->where('day', strtolower($day))
            ->first();

        if (!$openHour) {
            // Create default closed day
            $openHour = $modelClass::create([
                'shop_id' => null,
                'day' => strtolower($day),
                'is_open' => false,
                'open_at' => null,
                'close_at' => null,
            ]);
        }

        return $openHour;
    }

    /**
     * Update open hours for a specific day
     *
     * @param string $day
     * @param array $data
     * @return mixed
     */
    public function updateByDay(string $day, array $data)
    {
        $modelClass = $this->getModelClass();

        $openHour = $modelClass::whereNull('shop_id')
            ->orWhere('shop_id', 0)
            ->where('day', strtolower($day))
            ->first();

        // Ensure day matches
        $data['day'] = strtolower($day);
        $data['shop_id'] = null;

        if ($openHour) {
            $openHour->update($data);
            return $openHour->fresh();
        }

        return $modelClass::create($data);
    }

    /**
     * Bulk update all open hours
     *
     * @param array $data Array of open hours data
     * @return \Illuminate\Support\Collection
     */
    public function bulkUpdate(array $data)
    {
        $modelClass = $this->getModelClass();
        $updated = collect();

        foreach ($data as $dayData) {
            if (!isset($dayData['day'])) {
                continue;
            }

            $day = strtolower($dayData['day']);
            $openHour = $this->updateByDay($day, $dayData);
            $updated->push($openHour);
        }

        return $updated;
    }

    /**
     * Initialize default open hours (all days closed)
     *
     * @return \Illuminate\Support\Collection
     */
    public function initializeDefaults()
    {
        $modelClass = $this->getModelClass();
        $openHours = collect();

        foreach (OpenHours::$days as $day) {
            $exists = $modelClass::whereNull('shop_id')
                ->orWhere('shop_id', 0)
                ->where('day', $day)
                ->exists();

            if (!$exists) {
                $openHour = $modelClass::create([
                    'shop_id' => null,
                    'day' => $day,
                    'is_open' => false,
                    'open_at' => null,
                    'close_at' => null,
                ]);
                $openHours->push($openHour);
            }
        }

        return $this->getAll();
    }
}
