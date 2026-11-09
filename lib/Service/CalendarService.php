<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Absence\Service;

use OCA\Absence\Db\LeaveRequest;
use OCA\Absence\Db\LeaveTypeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use OCP\IL10N;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VCalendar;

/**
 * Writes approved leave into Nextcloud Calendar via the CalDAV backend (spec §10).
 *
 * Everything here is best-effort and lazily resolved: the dav app's CalDavBackend is
 * fetched from the container inside try/catch so a calendar problem can never block
 * an approval or cancellation.
 */
class CalendarService {
	private const PERSONAL_CALENDAR_URI = 'absence';
	private const SHARED_CALENDAR_URI = 'absence-team';
	private const SHARED_PRINCIPAL = 'principals/system/absence';

	public function __construct(
		private ContainerInterface $container,
		private ConfigService $config,
		private LeaveTypeMapper $leaveTypeMapper,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private IL10N $l,
	) {
	}

	/**
	 * Create calendar events for an approved request and return the stored reference
	 * ("calendarId:objectUri;calendarId:objectUri"), or null when disabled/unavailable.
	 */
	public function onApproved(LeaveRequest $request): ?string {
		$backend = $this->backend();
		if ($backend === null) {
			return null;
		}
		$refs = [];
		try {
			if ($this->config->isCalDavPersonalEnabled()) {
				$ref = $this->writeEvent(
					$backend,
					'principals/users/' . $request->getEmployeeUid(),
					self::PERSONAL_CALENDAR_URI,
					$this->l->t('Absence'),
					$request,
					$this->personalTitle($request),
				);
				if ($ref !== null) {
					$refs[] = $ref;
				}
			}
			if ($this->config->isCalDavSharedEnabled()) {
				$ref = $this->writeEvent(
					$backend,
					self::SHARED_PRINCIPAL,
					self::SHARED_CALENDAR_URI,
					$this->l->t('Team absences'),
					$request,
					$this->sharedTitle($request),
				);
				if ($ref !== null) {
					$refs[] = $ref;
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Absence: calendar write failed', ['exception' => $e]);
		}
		return $refs === [] ? null : implode(';', $refs);
	}

	/**
	 * Remove any calendar events previously created for a request.
	 */
	public function onRemoved(LeaveRequest $request): void {
		$backend = $this->backend();
		$ref = $request->getCalendarEventUri();
		if ($backend === null || $ref === null || $ref === '') {
			return;
		}
		foreach (explode(';', $ref) as $entry) {
			[$calendarId, $objectUri] = array_pad(explode(':', $entry, 2), 2, null);
			if ($calendarId === null || $objectUri === null) {
				continue;
			}
			try {
				$backend->deleteCalendarObject((int)$calendarId, $objectUri);
			} catch (\Throwable $e) {
				$this->logger->debug('Absence: calendar delete failed', ['exception' => $e]);
			}
		}
	}

	private function writeEvent($backend, string $principalUri, string $calendarUri, string $displayName, LeaveRequest $request, string $title): ?string {
		$calendarId = $this->ensureCalendar($backend, $principalUri, $calendarUri, $displayName);
		if ($calendarId === null) {
			return null;
		}
		if ($this->config->isPrivateAbsencesEnabled()) {
			// Never add absence data to a calendar already shared or published.
			$qb = \OCP\Server::get(\OCP\IDBConnection::class)->getQueryBuilder();
			$qb->select($qb->func()->count('*'))->from('dav_shares')
				->where($qb->expr()->eq('resourceid', $qb->createNamedParameter($calendarId)))
				->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('calendar')));
			$result = $qb->executeQuery();
			$shared = (int)$result->fetchOne() > 0;
			$result->closeCursor();
			if ($shared) {
				return null;
			}
		}
		$objectUri = 'absence-' . $request->getId() . '-' . substr(md5($principalUri), 0, 8) . '.ics';
		$ics = $this->buildIcs($request, $objectUri, $title);
		$backend->createCalendarObject($calendarId, $objectUri, $ics);
		return $calendarId . ':' . $objectUri;
	}

	private function ensureCalendar($backend, string $principalUri, string $uri, string $displayName): ?int {
		try {
			foreach ($backend->getCalendarsForUser($principalUri) as $calendar) {
				if (($calendar['uri'] ?? null) === $uri) {
					return (int)$calendar['id'];
				}
			}
			return (int)$backend->createCalendar($principalUri, $uri, [
				'{DAV:}displayname' => $displayName,
				'{http://apple.com/ns/ical/}calendar-color' => '#0082c9',
			]);
		} catch (\Throwable $e) {
			$this->logger->debug('Absence: ensureCalendar failed', ['exception' => $e]);
			return null;
		}
	}

	private function buildIcs(LeaveRequest $request, string $uid, string $title): string {
		$vcalendar = new VCalendar();
		// All-day event: DTEND is exclusive, so add one day to the inclusive end date.
		$end = (new \DateTimeImmutable($request->getEndDate()))->modify('+1 day');
		$vevent = $vcalendar->add('VEVENT', [
			'UID' => $uid,
			'SUMMARY' => $title,
			'TRANSP' => 'OPAQUE',
		]);
		$vevent->add('DTSTART', new \DateTimeImmutable($request->getStartDate()), ['VALUE' => 'DATE']);
		$vevent->add('DTEND', $end, ['VALUE' => 'DATE']);
		return $vcalendar->serialize();
	}

	private function personalTitle(LeaveRequest $request): string {
		return $this->typeLabel($request) . ' — Absence';
	}

	private function sharedTitle(LeaveRequest $request): string {
		$name = $this->displayName($request->getEmployeeUid());
		if ($this->config->getSharedCalendarVisibility() === ConfigService::VISIBILITY_REVEAL) {
			return $name . ' — ' . $this->typeLabel($request);
		}
		return $name . ' — ' . $this->l->t('Absent');
	}

	private function typeLabel(LeaveRequest $request): string {
		try {
			return $this->l->t($this->leaveTypeMapper->find($request->getTypeId())->getLabel());
		} catch (DoesNotExistException) {
			return $this->l->t('Leave');
		}
	}

	private function displayName(string $uid): string {
		$user = $this->userManager->get($uid);
		return $user !== null ? $user->getDisplayName() : $uid;
	}

	private function backend(): mixed {
		try {
			return $this->container->get(\OCA\DAV\CalDAV\CalDavBackend::class);
		} catch (\Throwable $e) {
			$this->logger->debug('Absence: CalDavBackend unavailable', ['exception' => $e]);
			return null;
		}
	}
}
