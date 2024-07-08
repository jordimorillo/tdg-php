# TDG-PHP

TDG-PHP is a tool for generating and testing PHP code based on provided tests. It utilizes a code generation service and runs tests to ensure the generated code meets the requirements specified in the tests.

## Installation

To install TDG-PHP in your project, run the following command:

```bash
composer require jordimorillo/tdg-php
```

## Usage

Once installed, you can use the `tdg-php` command to generate and test code based on a PHP test file. The command is executed as follows:

```bash
./vendor/bin/tdg-php path/to/test/file.php
```

### Parameters

- `path/to/test/file.php`: The path to the PHP test file that will be used to generate the code.

### Example Usage

```bash
./vendor/bin/tdg-php tests/ExampleTest.php
```

## Configuration

To ensure the tool works correctly, certain environment variables need to be configured. Below are the necessary variables and their purposes:

- `PROJECT_ROOT`: The root path of the project.
- `PHPUNIT_XML_PATH`: The path to the PHPUnit configuration file.
- `BASE_NAMESPACE`: The base namespace of the project.
- `TESTS_BASE_NAMESPACE`: The base namespace for the tests.
- `API_KEY`: The API key for the code generation service.
- `MAX_ATTEMPTS`: The maximum number of attempts to generate valid code.
- `PERMANENT_ATTACHMENTS`: Additional files that will always be attached in the code generation process.

### Example `.env` File

```env
PROJECT_ROOT=/path/to/project
PHPUNIT_XML_PATH=/path/to/phpunit.xml
BASE_NAMESPACE=App
TESTS_BASE_NAMESPACE=Tests
API_KEY=your_api_key
MAX_ATTEMPTS=5
PERMANENT_ATTACHMENTS=src/Helper.php,src/Utils.php
```

## Development

### Requirements

- PHP 8.0 or higher
- Composer
- PHP cURL extension

### Running Tests

To run the tests, use the following command:

```bash
composer test
```

## Contributing

Contributions are welcome. Please open an issue or submit a pull request to contribute to the project.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.

### Additional Requirements

For the tool to work, it is necessary to install Llama3 locally.
