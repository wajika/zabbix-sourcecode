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

	protected $tableName = 'task';
	protected $tableAlias = 't';
	protected $sortColumns = ['taskid'];

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

		/**
	 * Get results of requested ZBX_TM_TASK_DATA task.
	 *
	 * @param array         $options
	 * @param string|array  $options['output']
	 * @param string|array  $options['taskids']       Task IDs to select data about.
	 * @param bool          $options['preservekeys']  Use IDs as keys in the resulting array.
	 *
	 * @return array | boolean
	 */
	public function get(array $options): array {
		if (self::$userData['type'] < USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'taskids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_NULL, 'uniq' => true],
			// output
			'output' =>			['type' => API_OUTPUT, 'in' => implode(',', ['taskid', 'type', 'status', 'clock', 'ttl', 'proxy_hostid', 'request', 'response']), 'default' => API_OUTPUT_EXTEND],
			// flags
			'preservekeys' =>	['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options += [
			'sortfield' => 'taskid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit'		=> select_config()['search_limit']
		];

		$where = ['type' => 't.type='.ZBX_TM_TASK_DIAGINFO];

		if ($options['taskids'] !== null) {
			$where['taskid'] = dbConditionInt('t.taskid', $options['taskids']);
		}

		$sql_parts = [
			'select'	=> ['task' => 't.taskid'],
			'from'		=> ['task' => 'task t'],
			'where'		=> $where
		];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$output_request = $this->outputIsRequested('request', $options['output']);
		$output_response = $this->outputIsRequested('response', $options['output']);
		$tasks = [];

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($output_request) {
				$row['request']['data'] = json_decode($row['request_data']);
				unset($row['request_data']);
			}

			if ($output_response) {
				$row['result'] = [
					'data' => $row['response_info'] ? json_decode($row['response_info']) : [],
					'status' => $row['response_status']
				];
				unset($row['response_info'], $row['response_status']);
			}

			$tasks[$row['taskid']] = $row;
		}

		if ($tasks) {
			$tasks = $this->unsetExtraFields($tasks, ['taskid'], $options['output']);

			if (!$options['preservekeys']) {
				$tasks = array_values($tasks);
			}
		}

		return $tasks;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		if ($this->outputIsRequested('request', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'req', 'table' => 'task_data', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('req.data AS request_data', $sql_parts);
		}

		if ($this->outputIsRequested('response', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'resp', 'table' => 'task_result', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('resp.info AS response_info', $sql_parts);
			$sql_parts = $this->addQuerySelect('resp.status AS response_status', $sql_parts);
		}

		return $sql_parts;
	}
}
