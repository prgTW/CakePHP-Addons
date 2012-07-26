<?php

App::uses('Multibyte', 'I18n');

/**
 *
 * @author prgTW
 */
class SerializableBehavior extends ModelBehavior {

	/**
	 *
	 * Settings:
	 * fields (default: array())
	 *   Array of fields that will be serialized
	 *
	 * format: php|json|custom (default: php)
	 *   php [default]
	 *     format used by serialize() and unserialize()
	 *   json
	 *     format used by json_encode()/json_decode()
	 *   custom
	 *     define Your own sprintf-compatible format. "%s" will be converted to
	 *     example:
	 *       format: |%s|
	 *       delimiter: -
	 *       model data: array('x', 'y', 'z')
	 *       result: |x-y-z|
	 *
	 * delimiter (default: ', '; custom format only!)
	 *   Delimiter used to implode parts of concatenated array elements
	 *
	 * unique (default: true)
	 *   Only unique array elements will be concatenated
	 *
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Default settings
	 *
	 * @var array
	 */
	protected $_defaults = array(
		'fields' => array(),
		'format' => 'php',
		'delimiter' => ', ',
		'unique' => true,
	);

	public function setup(Model $model, $config = array()) {
		if (!isset($this->settings[$model->alias])) {
			$this->settings[$model->alias] = $this->_defaults;
		}

		$this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array)$config);
	}

	private function __serialize($data, $settings) {
		$results = null;

		switch ($settings['format']) {
			case 'php':
				$results = serialize($data);
				break;

			case 'json':
				$results = json_encode($data);
				break;

			default:
				if (!is_array($data)) {
					$data = explode(is_array($settings['delimiter']) ? $settings['delimiter'][0] : $settings['delimiter'], $data);
				}
				$data = array_map(function($val) {
						return trim($val);
					}, $data);
				if ($settings['unique']) {
					$data = array_unique($data);
				}
				$results = sprintf($settings['format'], implode(is_array($settings['delimiter']) ? $settings['delimiter'][1] : $settings['delimiter'], $data));
				break;
		}

		return $results;
	}

	private function __unserialize($data, $settings) {
		$results = null;

		switch ($settings['format']) {
			case 'php':
				$results = unserialize($data);
				break;

			case 'json':
				$results = json_decode($data, true);
				break;

			default:
				$pos = Multibyte::strpos($settings['format'], '%s');
				if ($pos !== false) {
					$len = Multibyte::strlen($settings['format'] - $pos - 2);
					$data = Multibyte::substr($data, $pos, Multibyte::strlen($data) - $pos - $len + 1);
				}
				$results = array_map(function($val) {
						return trim($val);
					}, explode(is_array($settings['delimiter']) ? $settings['delimiter'][1] : $settings['delimiter'], $data));
				break;
		}

		return $results;
	}

	public function serialize(Model $Model, $data) {
		if (empty($data[$Model->alias])) {
			return $data;
		}

		if (!empty($data[$Model->alias][0]) && array_intersect_key($this->settings[$Model->alias]['fields'], array_keys($data[$Model->alias][0]))) {
			foreach ($data[$Model->alias] as $key => $model) {
				$model = $this->serialize($Model, array($Model->alias => $model));
				$data[$Model->alias][$key] = $model[$Model->alias];
			}
		} else {
			foreach ($this->settings[$Model->alias]['fields'] as $field) {
				if (isset($data[$Model->alias][$field]) && (is_array($data[$Model->alias][$field]) || is_string($data[$Model->alias][$field]))) {
					$data[$Model->alias][$field] = $this->__serialize($data[$Model->alias][$field], $this->settings[$Model->alias]);
				}
			}
		}

		return $data;
	}

	public function unserialize(Model $Model, $data) {
		if (empty($data[$Model->alias])) {
			return $data;
		}

		foreach ($this->settings[$Model->alias]['fields'] as $field) {
			if (!empty($data[$Model->alias][$field])) {
				if (is_string($data[$Model->alias][$field])) {
					$data[$Model->alias][$field] = $this->__unserialize($data[$Model->alias][$field], $this->settings[$Model->alias]);
				}
			} elseif (array_key_exists($field, $data[$Model->alias])) {
				$data[$Model->alias][$field] = array();
			}
		}

		return $data;
	}

	public function afterFind(Model $Model, $results, $primary) {
		if (!empty($results)) {
			foreach ($results as $key => $result) {
				$results[$key] = $this->unserialize($Model, $result);
			}
		}

		return $results;
	}

	public function beforeSave(Model $Model) {
		$Model->data = $this->serialize($Model, $Model->data);

		return true;
	}

}