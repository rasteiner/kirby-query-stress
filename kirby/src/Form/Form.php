<?php

namespace Kirby\Form;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\ModelWithContent;
use Kirby\Data\Data;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Throwable;

/**
 * The main form class, that is being
 * used to create a list of form fields
 * and handles global form validation
 * and submission
 *
 * @package   Kirby Form
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://opensource.org/licenses/MIT
 */
class Form
{
	/**
	 * An array of all found errors
	 */
	protected array|null $errors = null;

	/**
	 * Fields in the form
	 */
	protected Fields|null $fields;

	/**
	 * All values of form
	 */
	protected array $values = [];

	/**
	 * Form constructor
	 */
	public function __construct(array $props)
	{
		$fields = $props['fields'] ?? [];
		$values = $props['values'] ?? [];
		$input  = $props['input']  ?? [];
		$strict = $props['strict'] ?? false;
		$inject = $props;

		// prepare field properties for multilang setups
		$fields = static::prepareFieldsForLanguage(
			$fields,
			$props['language'] ?? null
		);

		// lowercase all value names
		$values = array_change_key_case($values);
		$input  = array_change_key_case($input);

		unset($inject['fields'], $inject['values'], $inject['input']);

		$this->fields = new Fields();
		$this->values = [];

		foreach ($fields as $name => $props) {
			// inject stuff from the form constructor (model, etc.)
			$props = [...$inject, ...$props];

			// inject the name
			$props['name'] = $name = strtolower($name);

			// check if the field is disabled and
			// overwrite the field value if not set
			$props['value'] = match ($props['disabled'] ?? false) {
				true    => $values[$name] ?? null,
				default => $input[$name] ?? $values[$name] ?? null
			};

			try {
				$field = Field::factory($props['type'], $props, $this->fields);
			} catch (Throwable $e) {
				$field = static::exceptionField($e, $props);
			}

			if ($field->save() !== false) {
				$this->values[$name] = $field->value();
			}

			$this->fields->append($name, $field);
		}

		if ($strict !== true) {
			// use all given values, no matter
			// if there's a field or not.
			$input = [...$values, ...$input];

			foreach ($input as $key => $value) {
				$this->values[$key] ??= $value;
			}
		}
	}

	/**
	 * Returns the data required to write to the content file
	 * Doesn't include default and null values
	 */
	public function content(): array
	{
		return $this->data(false, false);
	}

	/**
	 * Returns data for all fields in the form
	 *
	 * @param false $defaults
	 */
	public function data($defaults = false, bool $includeNulls = true): array
	{
		$data = $this->values;

		foreach ($this->fields as $field) {
			if ($field->save() === false || $field->unset() === true) {
				if ($includeNulls === true) {
					$data[$field->name()] = null;
				} else {
					unset($data[$field->name()]);
				}
			} else {
				$data[$field->name()] = $field->data($defaults);
			}
		}

		return $data;
	}

	/**
	 * An array of all found errors
	 */
	public function errors(): array
	{
		if ($this->errors !== null) {
			return $this->errors;
		}

		$this->errors = [];

		foreach ($this->fields as $field) {
			if (empty($field->errors()) === false) {
				$this->errors[$field->name()] = [
					'label'   => $field->label(),
					'message' => $field->errors()
				];
			}
		}

		return $this->errors;
	}

	/**
	 * Shows the error with the field
	 */
	public static function exceptionField(
		Throwable $exception,
		array $props = []
	): Field {
		$message = $exception->getMessage();

		if (App::instance()->option('debug') === true) {
			$message .= ' in file: ' . $exception->getFile();
			$message .= ' line: ' . $exception->getLine();
		}

		return Field::factory('info', [
			...$props,
			'label' => 'Error in "' . $props['name'] . '" field.',
			'theme' => 'negative',
			'text'  => strip_tags($message),
		]);
	}

	/**
	 * Get the field object by name
	 * and handle nested fields correctly
	 *
	 * @throws \Kirby\Exception\NotFoundException
	 */
	public function field(string $name): Field|FieldClass
	{
		$form       = $this;
		$fieldNames = Str::split($name, '+');
		$index      = 0;
		$count      = count($fieldNames);
		$field      = null;

		foreach ($fieldNames as $fieldName) {
			$index++;

			if ($field = $form->fields()->get($fieldName)) {
				if ($count !== $index) {
					$form = $field->form();
				}

				continue;
			}

			throw new NotFoundException(
				message: 'The field "' . $fieldName . '" could not be found'
			);
		}

		// it can get this error only if $name is an empty string as $name = ''
		if ($field === null) {
			throw new NotFoundException(
				message: 'No field could be loaded'
			);
		}

		return $field;
	}

	/**
	 * Returns form fields
	 */
	public function fields(): Fields|null
	{
		return $this->fields;
	}

	public static function for(
		ModelWithContent $model,
		array $props = []
	): static {
		// get the original model data
		$original = $model->content($props['language'] ?? null)->toArray();
		$values   = $props['values'] ?? [];

		// convert closures to values
		foreach ($values as $key => $value) {
			if ($value instanceof Closure) {
				$values[$key] = $value($original[$key] ?? null);
			}
		}

		// set a few defaults
		$props['values']   = [...$original, ...$values];
		$props['fields'] ??= [];
		$props['model']    = $model;

		// search for the blueprint
		if (
			method_exists($model, 'blueprint') === true &&
			$blueprint = $model->blueprint()
		) {
			$props['fields'] = $blueprint->fields();
		}

		$ignoreDisabled = $props['ignoreDisabled'] ?? false;

		// REFACTOR: this could be more elegant
		if ($ignoreDisabled === true) {
			$props['fields'] = array_map(function ($field) {
				$field['disabled'] = false;
				return $field;
			}, $props['fields']);
		}

		return new static($props);
	}

	/**
	 * Checks if the form is invalid
	 */
	public function isInvalid(): bool
	{
		return $this->isValid() === false;
	}

	/**
	 * Checks if the form is valid
	 */
	public function isValid(): bool
	{
		return empty($this->errors()) === true;
	}

	/**
	 * Disables fields in secondary languages when
	 * they are configured to be untranslatable
	 */
	protected static function prepareFieldsForLanguage(
		array $fields,
		string|null $language = null
	): array {
		$kirby = App::instance(null, true);

		// only modify the fields if we have a valid Kirby multilang instance
		if ($kirby?->multilang() !== true) {
			return $fields;
		}

		$language ??= $kirby->language()->code();

		if ($language !== $kirby->defaultLanguage()->code()) {
			foreach ($fields as $fieldName => $fieldProps) {
				// switch untranslatable fields to readonly
				if (($fieldProps['translate'] ?? true) === false) {
					$fields[$fieldName]['unset']    = true;
					$fields[$fieldName]['disabled'] = true;
				}
			}
		}

		return $fields;
	}

	/**
	 * Converts the data of fields to strings
	 *
	 * @param false $defaults
	 */
	public function strings($defaults = false): array
	{
		return A::map(
			$this->data($defaults),
			fn ($value) => match (true) {
				is_array($value) => Data::encode($value, 'yaml'),
				default		     => $value
			}
		);
	}

	/**
	 * Converts the form to a plain array
	 */
	public function toArray(): array
	{
		$array = [
			'errors'  => $this->errors(),
			'fields'  => $this->fields->toArray(fn ($item) => $item->toArray()),
			'invalid' => $this->isInvalid()
		];

		return $array;
	}

	/**
	 * Returns form values
	 */
	public function values(): array
	{
		return $this->values;
	}
}
