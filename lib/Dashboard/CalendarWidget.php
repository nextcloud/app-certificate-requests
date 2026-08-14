<?php
/**
 *
 * CompactCalendarWidget APP (Nextcloud)
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

namespace OCA\CompactCalendarWidget\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IInitialStateService;
use OCP\Util;

class CalendarWidget implements IWidget {
    private IL10N $l;
    private IInitialStateService $initialStateService;

    public function __construct(IL10N $l, IInitialStateService $initialStateService) {
        $this->l = $l;
        $this->initialStateService = $initialStateService;
    }

    public function getId(): string {
        return 'compact_calendar_widget';
    }

    public function getName(): string {
        return $this->l->t('Upcoming events');
    }

    public function getTitle(): string {
        return $this->l->t('Upcoming events');
    }

    public function getDescription(): string {
        return $this->l->t('Zeigt Kalendertermine in einer kompakten Übersicht an.');
    }

    public function getIconUrl(): string {
        return Util::imagePath('core', 'places/calendar.svg');
    }

    public function getIconClass(): string {
        return 'icon-calendar';
    }

    public function getOrder(): int {
        return 10;
    }

    public function getUrl(): ?string {
        return null;
    }

    public function load(): void {
        Util::addScript('compact_calendar_widget', 'compact_calendar_widget-main');
        Util::addStyle('compact_calendar_widget', 'compact_calendar_widget');
    }
}
