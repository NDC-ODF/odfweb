<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Hemanth Kumar Veeranki <hems.india1997@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Göhler <somebody.here@gmx.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC\Setup;

use OC\DatabaseSetupException;
use OC\DB\MySqlTools;
use OCP\IDBConnection;
use OCP\ILogger;

class MySQL extends AbstractDatabase {
	public $dbprettyname = 'MySQL/MariaDB';

	public function setupDatabase($username) {
		//check if the database user has admin right
		$connection = $this->connect(['dbname' => null]);

		$result = $connection->query('SHOW VARIABLES LIKE "version" ;');
		$row = $result->fetch();
		$version = strtolower($row['Value']);

		if (strpos($version, 'mariadb') !== false) {
			if (version_compare($version, '10.4', '>=')) {
				throw new DatabaseSetupException(sprintf('Unsupported MariaDB version %s, Nextcloud 16 requires a version lower than 10.4', $row['Value']));
			}
		} else {
			if (version_compare($version, '8', '>=')) {
				throw new DatabaseSetupException(sprintf('Unsupported MySQL version %s, Nextcloud 16 requires a version lower than 8.0', $row['Value']));
			}
		}

		// detect mb4
		$tools = new MySqlTools();
		if ($tools->supports4ByteCharset($connection)) {
			$this->config->setValue('mysql.utf8mb4', true);
			$connection = $this->connect(['dbname' => null]);
		}

		$this->createSpecificUser($username, $connection);

		//create the database
		$this->createDatabase($connection);

		//fill the database if needed
		$query='select count(*) from information_schema.tables where table_schema=? AND table_name = ?';
		$connection->executeQuery($query, [$this->dbName, $this->tablePrefix.'users']);

		$connection->close();
		$connection = $this->connect();
		try {
			$connection->connect();
		} catch (\Exception $e) {
			$this->logger->logException($e);
			throw new \OC\DatabaseSetupException($this->trans->t('MySQL username and/or password not valid'),
				$this->trans->t('You need to enter details of an existing account.'));
		}
	}

	/**
	 * @param \OC\DB\Connection $connection
	 */
	private function createDatabase($connection) {
		try{
			$name = $this->dbName;
			$user = $this->dbUser;
			//we can't use OC_DB functions here because we need to connect as the administrative user.
			$characterSet = $this->config->getValue('mysql.utf8mb4', false) ? 'utf8mb4' : 'utf8';
			$query = "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET $characterSet COLLATE ${characterSet}_bin;";
			$connection->executeUpdate($query);
		} catch (\Exception $ex) {
			$this->logger->logException($ex, [
				'message' => 'Database creation failed.',
				'level' => ILogger::ERROR,
				'app' => 'mysql.setup',
			]);
			return;
		}

		try {
			//this query will fail if there aren't the right permissions, ignore the error
			$query="GRANT ALL PRIVILEGES ON `$name` . * TO '$user'";
			$connection->executeUpdate($query);
		} catch (\Exception $ex) {
			$this->logger->logException($ex, [
				'message' => 'Could not automatically grant privileges, this can be ignored if database user already had privileges.',
				'level' => ILogger::DEBUG,
				'app' => 'mysql.setup',
			]);
		}
	}

	/**
	 * @param IDBConnection $connection
	 * @throws \OC\DatabaseSetupException
	 */
	private function createDBUser($connection) {
		try{
			$name = $this->dbUser;
			$password = $this->dbPassword;
			// we need to create 2 accounts, one for global use and one for local user. if we don't specify the local one,
			// the anonymous user would take precedence when there is one.

			$query = "CREATE USER '$name'@'localhost' IDENTIFIED BY '$password'";
			// nc11 update: $query = "CREATE USER '$name'@'localhost' IDENTIFIED WITH mysql_native_password BY '$password'";

			$connection->executeUpdate($query);

			// nc11 update: $query = "CREATE USER '$name'@'%' IDENTIFIED WITH mysql_native_password BY '$password'";
			$query = "CREATE USER '$name'@'%' IDENTIFIED BY '$password'";

			$connection->executeUpdate($query);
		}
		catch (\Exception $ex){
			$this->logger->logException($ex, [
				'message' => 'Database user creation failed.',
				'level' => ILogger::ERROR,
				'app' => 'mysql.setup',
			]);
		}
	}

	/**
	 * @param $username
	 * @param IDBConnection $connection
	 * @return array
	 */
	private function createSpecificUser($username, $connection) {
		try {
			//user already specified in config
			$oldUser = $this->config->getValue('dbuser', false);

			//we don't have a dbuser specified in config
			if ($this->dbUser !== $oldUser) {
				//add prefix to the admin username to prevent collisions
				$adminUser = substr('oc_' . $username, 0, 16);

				$i = 1;
				while (true) {
					//this should be enough to check for admin rights in mysql
					$query = 'SELECT user FROM mysql.user WHERE user=?';
					$result = $connection->executeQuery($query, [$adminUser]);

					//current dbuser has admin rights
					if ($result) {
						$data = $result->fetchAll();
						//new dbuser does not exist
						if (count($data) === 0) {
							//use the admin login data for the new database user
							$this->dbUser = $adminUser;

							//create a random password so we don't need to store the admin password in the config file
							$this->dbPassword =  $this->random->generate(30);

							$this->createDBUser($connection);

							break;
						} else {
							//repeat with different username
							$length = strlen((string)$i);
							$adminUser = substr('oc_' . $username, 0, 16 - $length) . $i;
							$i++;
						}
					} else {
						break;
					}
				}
			}
		} catch (\Exception $ex) {
			$this->logger->logException($ex, [
				'message' => 'Can not create a new MySQL user, will continue with the provided user.',
				'level' => ILogger::INFO,
				'app' => 'mysql.setup',
			]);
		}

		$this->config->setValues([
			'dbuser' => $this->dbUser,
			'dbpassword' => $this->dbPassword,
		]);
	}
}
