<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Wrapper;

use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use ReservationsRules;

/**
 * The booking rules between the backend form and the core: the form counts the
 * lead time in days or hours, the core stores minutes.
 */
final class ReservationsRulesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('WB_PATH')) {
            define('WB_PATH', '');
        }
        require_once __DIR__ . '/../../../wbce/rules.php';
    }

    /**
     * @param array<string, string> $values
     * @return callable(string): string
     */
    private function post(array $values): callable
    {
        return static fn (string $name): string => $values[$name] ?? '';
    }

    public function testDayModeCountsTheLeadInDaysAndKeepsPeriodFields(): void
    {
        $rules = ReservationsRules::fromPost($this->post(['lead_min' => '2', 'min_duration' => '3', 'checkin_time' => '15:00', 'slot_minutes' => '15', 'buffer_before' => '5']), true);
        self::assertNotNull($rules);
        self::assertSame(2880, $rules['leadMinMinutes']);
        self::assertSame(3, $rules['minDuration']);
        self::assertSame('15:00', $rules['checkinTime']);
        self::assertNull($rules['checkoutTime']);
        self::assertArrayNotHasKey('slotMinutes', $rules);
        self::assertArrayNotHasKey('bufferBefore', $rules);
    }

    public function testPeriodModeCountsTheLeadInHoursAndKeepsDayFields(): void
    {
        $rules = ReservationsRules::fromPost($this->post(['lead_min' => '3', 'slot_minutes' => '15', 'auto_confirm' => '1', 'checkin_time' => '15:00']), false);
        self::assertNotNull($rules);
        self::assertSame(180, $rules['leadMinMinutes']);
        self::assertSame(15, $rules['slotMinutes']);
        self::assertTrue($rules['autoConfirm']);
        self::assertSame(0, $rules['bufferBefore'], 'empty means no buffer');
        self::assertNull($rules['maxDuration'], 'empty means no limit');
        self::assertArrayNotHasKey('checkinTime', $rules);
    }

    public function testAFieldThatIsNoWholeNumberFailsTheForm(): void
    {
        self::assertNull(ReservationsRules::fromPost($this->post(['capacity' => 'vier']), true));
        self::assertNull(ReservationsRules::fromPost($this->post(['lead_min' => '1.5']), false));
        self::assertNull(ReservationsRules::fromPost($this->post(['min_duration' => '2147483648']), false));
    }

    public function testTheFormShowsStoredValuesInItsOwnUnitsRoundedUp(): void
    {
        $resource = new BookableResource(null, 1, 1, 'FeWo', leadMinMinutes: 2000, checkinTime: '15:00:00');

        $byDay = ReservationsRules::toForm($resource, true);
        self::assertSame(2, $byDay['lead_min']);
        self::assertSame('15:00', $byDay['checkin_time']);
        self::assertSame('', $byDay['capacity']);

        self::assertSame(34, ReservationsRules::toForm($resource, false)['lead_min']);
    }
}
