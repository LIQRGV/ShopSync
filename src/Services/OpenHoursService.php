<?php

namespace TheDiamondBox\ShopSync\Services;

use TheDiamondBox\ShopSync\Services\OpenHoursFetchers\OpenHoursFetcherFactory;
use TheDiamondBox\ShopSync\Services\Contracts\OpenHoursFetcherInterface;
use Illuminate\Http\Request;

/**
 * OpenHoursService
 *
 * High-level service for open hours operations
 */
class OpenHoursService
{
    protected $openHoursFetcher;
    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
        // Delay fetcher creation until needed, or create in controller
        // This prevents issues with Request not being available during service provider binding
    }

    /**
     * Get or create the open hours fetcher
     *
     * @return OpenHoursFetcherInterface
     */
    protected function getFetcher()
    {
        if (!$this->openHoursFetcher) {
            $this->openHoursFetcher = OpenHoursFetcherFactory::makeFromConfig($this->request);
        }

        return $this->openHoursFetcher;
    }

    /**
     * Get all open hours
     *
     * @return array
     */
    public function getAllOpenHours()
    {
        $openHours = $this->getFetcher()->getAll();

        return $this->formatCollectionResponse($openHours);
    }

    /**
     * Get open hours for a specific day
     *
     * @param string $day
     * @return array|null
     */
    public function getOpenHoursByDay(string $day)
    {
        $openHour = $this->getFetcher()->getByDay($day);

        if (!$openHour) {
            return null;
        }

        return $this->formatSingleResponse($openHour);
    }

    /**
     * Update open hours for a specific day
     *
     * @param string $day
     * @param array $data
     * @return array|null
     */
    public function updateOpenHoursByDay(string $day, array $data)
    {
        $openHour = $this->getFetcher()->updateByDay($day, $data);

        if (!$openHour) {
            return null;
        }

        return $this->formatSingleResponse($openHour);
    }

    /**
     * Bulk update all open hours
     *
     * @param array $data Array of open hours data
     * @return array
     */
    public function bulkUpdateOpenHours(array $data)
    {
        $openHours = $this->getFetcher()->bulkUpdate($data);

        return $this->formatCollectionResponse($openHours);
    }

    /**
     * Initialize default open hours
     *
     * @return array
     */
    public function initializeDefaults()
    {
        $openHours = $this->getFetcher()->initializeDefaults();

        return $this->formatCollectionResponse($openHours);
    }

    /**
     * Format collection response (JSON API format)
     *
     * @param \Illuminate\Support\Collection $collection
     * @return array
     */
    protected function formatCollectionResponse($collection)
    {
        $data = $collection->map(function ($openHour) {
            return [
                'type' => 'open-hours',
                'id' => (string) $openHour->id,
                'attributes' => [
                    'day' => $openHour->day,
                    'is_open' => $openHour->is_open,
                    'open_at' => $openHour->open_at ? $openHour->open_at->format('H:i:s') : null,
                    'close_at' => $openHour->close_at ? $openHour->close_at->format('H:i:s') : null,
                    'created_at' => $openHour->created_at ? $openHour->created_at->toIso8601String() : null,
                    'updated_at' => $openHour->updated_at ? $openHour->updated_at->toIso8601String() : null,
                ]
            ];
        })->values()->all();

        return ['data' => $data];
    }

    /**
     * Format single response (JSON API format)
     *
     * @param mixed $openHour
     * @return array
     */
    protected function formatSingleResponse($openHour)
    {
        return [
            'data' => [
                'type' => 'open-hours',
                'id' => (string) $openHour->id,
                'attributes' => [
                    'day' => $openHour->day,
                    'is_open' => $openHour->is_open,
                    'open_at' => $openHour->open_at ? $openHour->open_at->format('H:i:s') : null,
                    'close_at' => $openHour->close_at ? $openHour->close_at->format('H:i:s') : null,
                    'created_at' => $openHour->created_at ? $openHour->created_at->toIso8601String() : null,
                    'updated_at' => $openHour->updated_at ? $openHour->updated_at->toIso8601String() : null,
                ]
            ]
        ];
    }
}
