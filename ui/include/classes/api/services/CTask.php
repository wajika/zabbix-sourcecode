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


/**
 * Class containing methods for operations with task.
 */
class CTask extends CApiService {

	/**
	 * @param array        $task             Task to create.
	 * @param string|array $task['itemids']  Array of item and LLD rule IDs to create tasks for.
	 *
	 * @return array
	 */
	public function create(array $tasks) {
		$this->validateCreate($tasks);

		$results = [];

		foreach ($tasks as $index => $task) {
			switch ($task['type']) {
			case ZBX_TM_TASK_CHECK_NOW:
				$results[] = (new CTaskCheckNow())->create($task['request'], $index.'/request');
				break;
			case ZBX_TM_TASK_DIAGINFO:
				$results[] = (new CTaskDiagInfo())->create($task['request'], $index.'/request', $task['proxy_hostid']);
				break;
			}
		}

		return array_merge(... array_column($results, 'taskids'));
	}

	protected function validateCreate(array &$task) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'type'         => ['type' => API_INT32, 'flags'  => API_REQUIRED, 'in' => implode(',', [ZBX_TM_TASK_CHECK_NOW, ZBX_TM_TASK_DIAGINFO])],
			'request'      => ['type' => API_OBJECT, 'flags' => API_REQUIRED|API_ALLOW_UNEXPECTED, 'fields' => []],
			'proxy_hostid' => ['type' => API_ID, 'default'   => 0]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $task, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}
}
