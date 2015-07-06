<?php

class SynonymValidator extends Validator {
	/**
	 * @var array
	 */
	protected $fieldNames;

	/**
	 * @inheritdoc
	 *
	 * @param array $fieldNames
	 */
	public function __construct(array $fieldNames) {
		$this->fieldNames = $fieldNames;

		parent::__construct();
	}

	/**
	 * @inheritdoc
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public function php($data) {
		foreach($this->fieldNames as $fieldName) {
			if(empty($data[$fieldName])) {
				return;
			}

			$this->validateField($fieldName, $data[$fieldName]);
		}
	}

	/**
	 * Validate field values, raising errors if the values are invalid.
	 *
	 * @param string $fieldName
	 * @param mixed $value
	 */
	protected function validateField($fieldName, $value) {
		if(!$this->validateValue($value)) {
			$this->validationError(
				$fieldName,
				_t(
					'FullTextSearch.SynonymValidator.InvalidValue',
					'Synonyms cannot contain words separated by spaces'
				)
			);
		}
	}

	/**
	 * Check field values to see that they doesn't contain space-delimited synonyms.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	protected function validateValue($value) {
		// strip empty lines
		$lines = array_filter(
			explode("\n", $value)
		);

		// strip lines beginning with "#"
		$lines = array_filter($lines, function ($line) {
			$line = trim($line);

			return !empty($line) && $line[0] !== '#';
		});

		// validate each line
		foreach($lines as $line) {
			if(!$this->validateLine($line)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check each line to see that it doesn't contain space-delimited synonyms.
	 *
	 * @param string $line
	 *
	 * @return bool
	 */
	protected function validateLine($line) {
		$line = trim($line);
		$parts = explode(',', $line);

		foreach($parts as $part) {
			// allow spaces at the beginning and end of the synonym
			$part = trim($part);

			// does the part contain 1 or more whitespace characters?
			if(preg_match('/\s+/', $part)) {
				return false;
			}
		}

		return true;
	}
}
