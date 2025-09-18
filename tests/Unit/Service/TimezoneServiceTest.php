<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TimezoneService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\TimezoneService
 */
class TimezoneServiceTest extends TestCase
{
    private TimezoneService $timezoneService;
    
    protected function setUp(): void
    {
        $this->timezoneService = new TimezoneService('Europe/Warsaw');
    }
    
    public function testNowReturnsCurrentTimeInCorrectTimezone(): void
    {
        $now = $this->timezoneService->now();
        
        $this->assertInstanceOf(DateTimeImmutable::class, $now);
        $this->assertSame('Europe/Warsaw', $now->getTimezone()->getName());
    }
    
    public function testTodayReturnsCurrentDateAtMidnight(): void
    {
        $today = $this->timezoneService->today();
        
        $this->assertInstanceOf(DateTimeImmutable::class, $today);
        $this->assertSame('Europe/Warsaw', $today->getTimezone()->getName());
        $this->assertSame('00:00:00', $today->format('H:i:s'));
    }
    
    public function testCreateFromDateString(): void
    {
        $date = $this->timezoneService->createFromDateString('2024-01-15');
        
        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2024-01-15', $date->format('Y-m-d'));
        $this->assertSame('Europe/Warsaw', $date->getTimezone()->getName());
    }
    
    public function testCreateFromDateStringWithInvalidDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: invalid-date');
        
        $this->timezoneService->createFromDateString('invalid-date');
    }
    
    public function testCreateFromFormat(): void
    {
        $date = $this->timezoneService->createFromFormat('d/m/Y', '15/01/2024');
        
        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2024-01-15', $date->format('Y-m-d'));
        $this->assertSame('Europe/Warsaw', $date->getTimezone()->getName());
    }
    
    public function testCreateFromFormatWithInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: 15/01/2024');
        
        $this->timezoneService->createFromFormat('Y-m-d', '15/01/2024');
    }
    
    public function testConvertToAppTimezone(): void
    {
        $utcDate = new DateTimeImmutable('2024-01-15 12:00:00', new DateTimeZone('UTC'));
        $convertedDate = $this->timezoneService->convertToAppTimezone($utcDate);
        
        $this->assertInstanceOf(DateTimeImmutable::class, $convertedDate);
        $this->assertSame('Europe/Warsaw', $convertedDate->getTimezone()->getName());
        $this->assertSame('2024-01-15', $convertedDate->format('Y-m-d'));
    }
    
    public function testGetTimezone(): void
    {
        $timezone = $this->timezoneService->getTimezone();
        
        $this->assertInstanceOf(DateTimeZone::class, $timezone);
        $this->assertSame('Europe/Warsaw', $timezone->getName());
    }
    
    public function testGetTimezoneName(): void
    {
        $timezoneName = $this->timezoneService->getTimezoneName();
        
        $this->assertSame('Europe/Warsaw', $timezoneName);
    }
    
    public function testDifferentTimezoneConfiguration(): void
    {
        $service = new TimezoneService('America/New_York');
        
        $this->assertSame('America/New_York', $service->getTimezoneName());
        
        $now = $service->now();
        $this->assertSame('America/New_York', $now->getTimezone()->getName());
    }
}
