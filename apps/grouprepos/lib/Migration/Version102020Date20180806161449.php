<?php

namespace OCA\GroupRepos\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version102020Date20180806161449 extends SimpleMigrationStep
{
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options)
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_repos')) {
			$table = $schema->createTable('group_repos');
			$table->addColumn('folder_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_point', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('quota', 'bigint', [
				'notnull' => true,
				'length' => 6,
				'default' => -3,
			]);
			$table->setPrimaryKey(['folder_id']);
		}

		if (!$schema->hasTable('group_repos_groups')) {
			$table = $schema->createTable('group_repos_groups');
			$table->addColumn('applicable_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('permissions', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('group_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['applicable_id']);
			if (!$table->hasIndex('group_repo')) {
				$table->addIndex(['folder_id'], 'group_repo');
			}
			if (!$table->hasIndex('groups_repo_group')) {
				$table->addUniqueIndex(['folder_id', 'group_id'], 'groups_repo_group');
			}
			if (!$table->hasIndex('group_repo_value')) {
				$table->addIndex(['group_id'], 'group_repo_value');
			}
		}
		return $schema;
	}
}
