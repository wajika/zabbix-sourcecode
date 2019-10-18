<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class DbBinder {

	private $data = [];

	public function dbConditionId($key, $fieldName, array $values, $notIn = false) {
		return $this->dbConditionInt($key, $fieldName, $values, $notIn, true);
	}

	public function dbConditionInt($key, $fieldName, array $values, $notIn = false, $zero_to_null = false) {
		global $DB;

		$MAX_EXPRESSIONS = 950;

		if (is_bool(reset($values))) {
			return '1=0';
		}

		$values = array_flip($values);

		$has_zero = false;

		if ($zero_to_null && array_key_exists(0, $values)) {
			$has_zero = true;
			unset($values[0]);
		}

		$values = array_keys($values);
		natsort($values);
		$values = array_values($values);

		foreach ($values as &$value) {
			if (!ctype_digit((string) $value) || bccomp($value, ZBX_MAX_UINT64) > 0) {
				$value = zbx_dbstr($value);
			}
		}
		unset($value);

		if ($DB['TYPE'] == ZBX_DB_ORACLE) {
			// Replace with named placeholders.
			$values = $this->bind($key, $values);
		}

		// concatenate conditions
		$condition = '';
		$operatorAnd = $notIn ? ' AND ' : ' OR ';

		$operatorNot = $notIn ? ' NOT' : '';
		$chunks = array_chunk($values, $MAX_EXPRESSIONS);
		$chunk_count = (int) $has_zero + count($chunks);

		foreach ($chunks as $chunk) {
			if (count($chunk) == 1) {
				$operator = $notIn ? '!=' : '=';

				$condition .= ($condition !== '' ? $operatorAnd : '').$fieldName.$operator.$chunk[0];
			}
			else {
				$chunkIns = '';

				foreach ($chunk as $value) {
					$chunkIns .= ','.$value;
				}

				$chunkIns = $fieldName.$operatorNot.' IN ('.substr($chunkIns, 1).')';

				$condition .= ($condition !== '') ? $operatorAnd.$chunkIns : $chunkIns;
			}
		}

		if ($has_zero) {
			$condition .= ($condition !== '') ? $operatorAnd : '';
			$condition .= $fieldName;
			$condition .= $notIn ? ' IS NOT NULL' : ' IS NULL';
		}

		return (!$notIn && $chunk_count > 1) ? '('.$condition.')' : $condition;
	}

	public function getBinds() {
		$binds = [];
		foreach ($this->data as $key => $values) {
			foreach ($values as $index => $value) {
				$binds[':'.$key.$index] = $value;
			}
		}

		return $binds;
	}

	private function bind($key, array $values) {
		$values = array_values($values);

		foreach ($values as &$value) {
			$value = (string) $value;
		}
		unset($value);

		$values_ret = [];

		if (array_key_exists($key, $this->data)) {
			$values_new = array_diff($values, $this->data[$key]);

			if ($values_new) {
				$this->data[$key] += array_combine(
					range(count($this->data[$key]), count($this->data[$key]) + count($values_new) - 1),
					$values_new
				);
			}

			$values_ret = array_intersect($this->data[$key], $values);
		}
		else {
			$this->data[$key] = $values;

			$values_ret = $values;
		}

		return array_map(function($index) use ($key) {
			return (':'.$key.$index);
		}, array_keys($values_ret));
	}
}
