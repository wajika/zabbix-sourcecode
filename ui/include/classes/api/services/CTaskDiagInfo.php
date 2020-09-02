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


class CTaskDiagInfo extends CApiService {

	/*
	 * String constant to retrieve all possible fields for dyagnostic requested.
	 * Must be synchronized with server.
	 */
	const FIELD_ALL = 'all';

	public function create(array $task, string $path, int $proxy_hostid) {
		$this->validateCreate($task, $path, $proxy_hostid);

		$taskid = DB::reserveIds('task', 1);

		$ins_task = [
			'taskid' => $taskid,
			'type' => ZBX_TM_TASK_DIAGINFO,
			'status' => ZBX_TM_STATUS_NEW,
			'clock' => time(),
			'ttl' => SEC_PER_HOUR,
			'proxy_hostid' => $proxy_hostid
		];

		$ins_task_data = [
			'taskid' => $taskid,
			'type' => ZBX_TM_TASK_DIAGINFO,
			'data' => json_encode($task),
			'parent_taskid' => $taskid
		];

		DB::insert('task', [$ins_task], false);
		DB::insert('task_data', [$ins_task_data], false);

		return ['taskids' => [$taskid]];
	}

	protected function validateCreate(array &$task, string $path, int $proxy_hostid) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
			'historycache' =>	['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
				'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'items', 'values', 'memory', 'memory.data', 'memory.index'])],
				'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
					'values' =>			['type' => API_INT32]
				]]
			]],
			'valuecache' =>		['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
				'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'items', 'values', 'memory', 'mode'])],
				'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
					'values' =>			['type' => API_INT32, 'flags' => API_ALLOW_NULL],
					'request.values' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL]
				]]
			]],
			'preprocessing' =>	['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
				'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'values', 'preproc.values'])],
				'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
					'values' =>			['type' => API_INT32]
				]]
			]],
			'alerting' =>		['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
				'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'alerts'])],
				'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
					'media.alerts' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL],
					'source.alerts' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL]
				]]
			]],
			'lld' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
				'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'rules', 'values'])],
				'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
					'values' =>			['type' => API_INT32]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $task, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check if specified proxies exists.
		if ($proxy_hostid) {
			$proxies = API::Proxy()->get([
				'countOutput' => true,
				'proxyids' => $proxy_hostid
			]);

			// TODO: maybe batch many proxies..
			if (!$proxies) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}
}
