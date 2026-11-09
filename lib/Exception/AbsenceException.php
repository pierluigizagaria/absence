<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Absence\Exception;

use OCP\AppFramework\Http;
use OCP\IL10N;

/**
 * Base for domain errors that map cleanly onto HTTP status codes.
 */
class AbsenceException extends \RuntimeException {
	private string $translationTemplate;

	/** @param list<mixed> $parameters */
	public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, private array $parameters = []) {
		$this->translationTemplate = $message;
		parent::__construct($parameters === [] ? $message : vsprintf($message, $parameters), $code, $previous);
	}

	public function getLocalizedMessage(IL10N $l): string {
		return (string)$l->t($this->translationTemplate, $this->parameters);
	}

	public function getHttpStatus(): int {
		return Http::STATUS_BAD_REQUEST;
	}
}
