<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Absence\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Keep schema defaults portable; seeded and user-defined icons stay unchanged. */
class Version1007Date20261109150000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('absence_leave_types')) {
			return null;
		}
		$column = $schema->getTable('absence_leave_types')->getColumn('icon');
		if ($column->getDefault() === '') {
			return null;
		}
		// MariaDB metadata exposes supplementary characters as "?", causing a
		// persistent false schema mismatch for the former emoji default.
		$column->setDefault('');
		return $schema;
	}
}
