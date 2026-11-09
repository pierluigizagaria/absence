<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Absence\Controller;

use OCA\Absence\Exception\AbsenceException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\L10N\IFactory;

/**
 * Shared error handling: turns domain exceptions into clean JSON error responses.
 */
trait ApiControllerTrait {
	/**
	 * @param \Closure():mixed $fn
	 */
	protected function handle(\Closure $fn): DataResponse {
		try {
			return new DataResponse($fn());
		} catch (AbsenceException $e) {
			// Domain exceptions carry deliberate, user-safe messages.
			$l = \OCP\Server::get(IFactory::class)->get('absence');
			return new DataResponse(['message' => $e->getLocalizedMessage($l)], $e->getHttpStatus());
		} catch (\Throwable $e) {
			// Never leak internal exception text (DB errors, paths, class names) to the
			// client. Log the detail for operators and return a generic message.
			\OCP\Server::get(\Psr\Log\LoggerInterface::class)->error(
				'Absence: unhandled error in API request',
				['exception' => $e, 'app' => 'absence'],
			);
			$l = \OCP\Server::get(IFactory::class)->get('absence');
			return new DataResponse(['message' => (string)$l->t('An unexpected error occurred.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
