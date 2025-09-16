<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Timezone Service
 * 
 * Centralizes timezone handling for the application.
 * Ensures all dates are handled in Europe/Warsaw timezone as required by business.
 */
class TimezoneService
{
    private DateTimeZone $timezone;
    
    public function __construct(string $timezone)
    {
        $this->timezone = new DateTimeZone($timezone);
    }
    
    /**
     * Get current date/time in application timezone
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
    
    /**
     * Get today's date (midnight) in application timezone
     */
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', $this->timezone);
    }
    
    /**
     * Create date from string in application timezone
     */
    public function createFromFormat(string $format, string $datetime): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($format, $datetime, $this->timezone);
        
        if ($date === false) {
            throw new \InvalidArgumentException("Invalid date format: {$datetime}");
        }
        
        return $date;
    }
    
    /**
     * Create date from Y-m-d string in application timezone
     */
    public function createFromDateString(string $dateString): DateTimeImmutable
    {
        return $this->createFromFormat('Y-m-d', $dateString);
    }
    
    /**
     * Convert any DateTimeImmutable to application timezone
     */
    public function convertToAppTimezone(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone($this->timezone);
    }
    
    /**
     * Get application timezone
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }
    
    /**
     * Get timezone name (e.g., "Europe/Warsaw")
     */
    public function getTimezoneName(): string
    {
        return $this->timezone->getName();
    }
}
