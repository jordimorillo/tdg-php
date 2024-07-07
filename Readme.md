### README.md

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

- `CONTAINER_PROJECT_ROOT`: The root path of the project within the Docker container.
- `PHPUNIT_XML_PATH`: The path to the PHPUnit configuration file.
- `BASE_NAMESPACE`: The base namespace of the project.
- `TESTS_BASE_NAMESPACE`: The base namespace for the tests.
- `OPENAI_API_KEY`: The API key for the code generation service.
- `MAX_ATTEMPTS`: The maximum number of attempts to generate valid code.
- `PHP_DOCKER_CONTAINER_NAME`: The name of the Docker container that will run the tests.
- `PERMANENT_ATTACHMENTS`: Additional files that will always be attached in the code generation process.

### Example `.env` File

```env
CONTAINER_PROJECT_ROOT=/path/to/project
PHPUNIT_XML_PATH=/path/to/phpunit.xml
BASE_NAMESPACE=App
TESTS_BASE_NAMESPACE=Tests
OPENAI_API_KEY=your_openai_api_key
MAX_ATTEMPTS=5
PHP_DOCKER_CONTAINER_NAME=php-container
PERMANENT_ATTACHMENTS=src/Helper.php,src/Utils.php
```

## Development

### Requirements

- PHP 7.4 or higher
- Composer
- Docker
- PHP cURL extension

### Running Tests

To run the tests, use the following command:

```bash
composer test
```

### Project Structure

The project follows a hexagonal architecture. Below is a brief description of each component:

- `src/Application/Service/TestService.php`: Main service that orchestrates code generation and testing.
- `src/Domain/`: Contains the domain logic of the project.
- `src/Infrastructure/Persistence/CodeRepository.php`: Repository responsible for managing code files.
- `src/Infrastructure/Persistence/TestRepository.php`: Repository responsible for managing test files and running tests.
- `src/Console/Command/RunCommand.php`: Console command to run the tool.
- `bootstrap.php`: Bootstrap file to initialize and run the service.

## Contributing

Contributions are welcome. Please open an issue or submit a pull request to contribute to the project.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.
