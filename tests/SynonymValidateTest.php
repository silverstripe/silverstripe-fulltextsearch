<?php

class SynonymValidatorTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var SynonymValidator
	 */
	protected $validator;

	/**
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->validator = new SynonymValidator(array(
			'Synonyms',
		));
	}

	/**
	 * @inheritdoc
	 */
	public function tearDown() {
		$this->validator = null;

		parent::tearDown();
	}

	/**
	 * @dataProvider validValuesProvider
	 */
	public function testItAllowsValidValues($value) {
		$this->validator->php(array(
			'Synonyms' => $value,
		));

		$this->assertEmpty($this->validator->getErrors());
	}

	/**
	 * @return array
	 */
	public function validValuesProvider() {
		return array(
			array('foo'),
			array('foo,bar,baz'),
			array('foo, bar ,baz'),
			array('
				foo
				bar
				baz
			'),
			array('
				# this is a comment, it should be ignored!

				foo=>bar,baz

				# ...as should this.
			'),
		);
	}

	/**
	 * @dataProvider invalidValuesProvider
	 *
	 * @param string $value
	 */
	public function testItDisallowsInvalidValues($value) {
		$this->validator->php(array(
			"Synonyms" => $value,
		));

		$this->assertNotEmpty($this->validator->getErrors());
	}

	/**
	 * @return array
	 */
	public function invalidValuesProvider() {
		return array(
			array('foo, bar baz, qux'),
		);
	}
}
