<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testHostInterface extends CAPITest {

	const TEST_HOSTID = '72300';

	public static function hostInterface_Create() {
		return [
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '1',
					'port' => '10050',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '1',
					'port' => '10050',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => 'Host cannot have more than one default interface of the same type.'
			],
			[
				'interface' => [
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "hostid" is missing.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '',
					'main' => '0',
					'port' => '10050',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => 'IP and DNS cannot be empty for host interface.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '-1',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => 'Incorrect value "-1" for "/port" field: must be between 0 and 65535.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'useip' => '1'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "type" is missing.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '1',
					'useip' => '0'
				],
				'expected_error' => 'Interface with IP "127.0.0.1" cannot have empty DNS name while having "Use DNS" property on "API Host to test hostinterfaces".'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '接口',
					'ip' => '127.0.0.1',
					'main' => '1',
					'port' => '10050',
					'type' => '1',
					'useip' => '0'
				],
				'expected_error' => 'Incorrect interface DNS parameter "接口" provided.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '{$USERMACRO}',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '65{$USERMACRO}',
					'type' => '1',
					'useip' => '1'
				],
				'expected_error' => 'Invalid parameter "/port": an integer is expected.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "details" is missing.'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'ip' => '127.0.0.1',
					'dns' => '',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '1',
						'community' => '123',
						'bulk' => '0'
					]
				],
				'expected_error' => 'No default interface for "SNMP" type on "API Host to test hostinterfaces".'
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '1',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '1',
						'community' => '{$SNMP_COMMUNITY}',
						'bulk' => '0'
					]
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '2',
						'community' => '{$SNMP_COMMUNITY}',
						'bulk' => '1'
					]
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '3',
						'contextname' => '',
						'securityname' => '',
						'bulk' => '0',
						'securitylevel' => '0'
					]
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '3',
						'contextname' => 'contextname',
						'securityname' => 'securityname',
						'bulk' => '0',
						'securitylevel' => '1',
						'authprotocol' => '0',
						'authpassphrase' => 'authpassphrase'
					]
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '3',
						'contextname' => 'contextname',
						'securityname' => 'securityname',
						'bulk' => '0',
						'securitylevel' => '2',
						'authprotocol' => '1',
						'authpassphrase' => 'authpassphrase'
					]
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'hostid' => SELF::TEST_HOSTID,
					'dns' => '',
					'ip' => '127.0.0.1',
					'main' => '0',
					'port' => '10050',
					'type' => '2',
					'useip' => '1',
					'details' => [
						'version' => '3'
					]
				],
				'expected_error' => null
			]
		];
	}


	public static function hostInterface_Update() {
		return [
			[
				'interface' => [
					'port' => '54321'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "interfaceid" is missing.'
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'dns' => 'test.tld'
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'hostid' => '99999999999999999'
				],
				'expected_error' => 'Cannot switch host for interface.'
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'ip' => '127.0.0.2'
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'main' => '0'
				],
				'expected_error' => 'No default interface for "JMX" type on "API Host to test hostinterfaces".'
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'port' => '54321'
				],
				'expected_error' => null
			],
			[
				'interface' => [
					'interfaceid' => '99134',
					'ip' => '',
					'dns' => '',
					'useip' => '0',
				],
				'expected_error' => 'IP and DNS cannot be empty for host interface.'
			],
		];
	}

	/**
	* @dataProvider hostInterface_Create
	*/
	public function testHostInterface_Create($interface, $expected_error) {
		$result = $this->call('hostinterface.create', $interface, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['interfaceids'] as $id) {
				$this->assertEquals(1, CDBHelper::getCount('select * from interface where interfaceid='.zbx_dbstr($id)));
			}
		}
	}

	/**
	* @dataProvider hostInterface_Update
	*/
	public function testHostInterface_Update($interface, $expected_error) {
		$result = $this->call('hostinterface.update', $interface, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['interfaceids'] as $id) {
				$db_results = DBSelect('select * from interface where interfaceid='.zbx_dbstr($id));
				$db_results_interface = DBFetch($db_results);
				foreach ($interface as $key => $value) {
					$this->assertEquals($db_results_interface[$key], $value);
				}
			}
		}
	}
}
