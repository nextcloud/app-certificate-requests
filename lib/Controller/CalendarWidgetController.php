<?php
/**
 *
 * Compact Calendar Widget APP (Nextcloud)
 *
 * @author Wolfgang Tödt <wtoedt@gmail.com>
 *
 * @copyright Copyright (c) 2026 Wolfgang Tödt
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\CompactCalendarWidget\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IDBConnection;
use DateTime;
use DateTimeZone;
use Sabre\VObject\Reader;
use OCP\Config\IUserConfig;

class CalendarWidgetController extends Controller {
    private IUserSession $userSession;
    private IDBConnection $db;
    private IUserConfig $userConfig;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IDBConnection $db,
        IUserConfig $userConfig,
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->db = $db;
        $this->userConfig = $userConfig;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEvents(string $range = 'week'): DataResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new DataResponse([], 401);
        }

        $userId = $user->getUID();
        $principalUri = 'principals/users/' . $userId;

        $tz = new DateTimeZone(date_default_timezone_get());

        $startParam = $this->request->getParam('start');
        $endParam   = $this->request->getParam('end');
        $dateParam  = $this->request->getParam('date');

        try {
            if ($startParam && $endParam) {
                $start = new DateTime($startParam . ' 00:00:00', $tz);
                $end   = new DateTime($endParam . ' 23:59:59', $tz);
            } else {
               $baseDate = $dateParam ? new DateTime($dateParam, $tz) : new DateTime('now', $tz);
                $start    = clone $baseDate;
                $end      = clone $baseDate;

                switch ($range) {
                    case 'day':
                        $start->setTime(0, 0, 0);
                        $end->setTime(23, 59, 59);
                        break;
                    case 'month':
                        $start->modify('first day of this month')->setTime(0, 0, 0);
                        $end->modify('last day of this month')->setTime(23, 59, 59);
                        break;
                    case 'year':
                        $start->modify('first day of January')->setTime(0, 0, 0);
                        $end->modify('last day of December')->setTime(23, 59, 59);
                        break;
                    case 'week':
                    default:
                        if ($start->format('N') === '1') {
                            $start->setTime(0, 0, 0);
                        } else {
                            $start->modify('last monday')->setTime(0, 0, 0);
                        }
                        $end = clone $start;
                        $end->modify('+6 days')->setTime(23, 59, 59);
                        break;
                }
            }
        } catch (\Throwable $e) {
            $start = new DateTime('now', $tz);
            $end   = clone $start;
            $start->modify('first day of this month')->setTime(0, 0, 0);
            $end->modify('last day of this month')->setTime(23, 59, 59);
        }

        $events = [];

        try {
            $sql = 'SELECT co.calendardata
                    FROM `*PREFIX*calendarobjects` co
                    JOIN `*PREFIX*calendars` c ON co.calendarid = c.id
                    WHERE c.principaluri = :principalUri
                      AND co.componenttype = \'VEVENT\'';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue('principalUri', $principalUri);
            $result = $stmt->execute();

            while ($row = $result->fetch()) {
                $calendarData = $row['calendardata'] ?? null;
                if (!$calendarData || !is_string($calendarData)) {
                    continue;
                }

                try {
                    $vObject = Reader::read($calendarData);
                    if (!isset($vObject->VEVENT)) {
                        continue;
                    }

                    foreach ($vObject->VEVENT as $vevent) {
                        $summary  = (string)($vevent->SUMMARY ?? 'Kein Titel');
                        $location = (string)($vevent->LOCATION ?? '');

                        $dtStart = isset($vevent->DTSTART) ? $vevent->DTSTART->getDateTime() : null;
                        $dtEnd   = isset($vevent->DTEND)   ? $vevent->DTEND->getDateTime()   : null;

                        if ($dtStart && $dtStart <= $end && ($dtEnd === null || $dtEnd >= $start)) {
                            $events[] = [
                                'id'       => (string)($vevent->UID ?? uniqid()),
                                'title'    => $summary,
                                'start'    => $dtStart ? $dtStart->format('c') : null,
                                'end'      => $dtEnd ? $dtEnd->format('c') : null,
                                'location' => $location,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
            $result->closeCursor();

        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }

        $uniqueEvents = [];
        foreach ($events as $ev) {
            $uniqueEvents[$ev['id']] = $ev;
        }
        $events = array_values($uniqueEvents);

        usort($events, function($a, $b) {
            if (!$a['start']) return -1;
            if (!$b['start']) return 1;
            return strcmp($a['start'], $b['start']);
        });

        return new DataResponse($events);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getSetting(?string $key = null): DataResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new DataResponse(['error' => 'Unauthorized'], 401);
        }

        // Falls $key nicht direkt gemappt wurde, aus den Query-Parametern holen
        $key = $key ?? $this->request->getParam('key', 'defaultView');

        $value = $this->userConfig->getValueString(
            $user->getUID(),
            $this->appName,
            $key,
            'month' // Sinnvoller Standard-Fallback
        );

        return new DataResponse([
            'key' => $key,
            'value' => $value
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveSetting(?string $key = null, ?string $value = null): DataResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new DataResponse(['error' => 'Unauthorized'], 401);
        }

        // Liest verlässlich sowohl JSON-Payload als auch Formulardaten & URL-Parameter
        $params = $this->request->getParams();

        $key = $key ?? $params['key'] ?? null;
        $value = $value ?? $params['value'] ?? null;

        if (empty($key)) {
            return new DataResponse(['error' => 'Missing parameter: key'], 400);
        }

        if ($value === null) {
            return new DataResponse(['error' => 'Missing parameter: value'], 400);
        }

        $this->userConfig->setValueString(
            $user->getUID(),
            $this->appName,
            (string)$key,
            (string)$value
        );

        return new DataResponse(['success' => true]);
    }
}
