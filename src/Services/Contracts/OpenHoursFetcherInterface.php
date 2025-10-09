<?php

namespace TheDiamondBox\ShopSync\Services\Contracts;

/**
 * OpenHoursFetcherInterface
 *
 * Contract for open hours fetchers (Database for WL, API for WTM)
 */
interface OpenHoursFetcherInterface
{
    /**
     * Get all open hours
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAll();

    /**
     * Get open hours for a specific day
     *
     * @param string $day
     * @return mixed
     */
    public function getByDay(string $day);

    /**
     * Update open hours for a specific day
     *
     * @param string $day
     * @param array $data
     * @return mixed
     */
    public function updateByDay(string $day, array $data);

    /**
     * Bulk update all open hours
     *
     * @param array $data Array of open hours data (day => data)
     * @return \Illuminate\Support\Collection
     */
    public function bulkUpdate(array $data);

    /**
     * Initialize default open hours (all days closed)
     *
     * @return \Illuminate\Support\Collection
     */
    public function initializeDefaults();
}
